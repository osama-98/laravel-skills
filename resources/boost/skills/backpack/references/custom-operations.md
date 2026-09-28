# Custom Operations

An operation is just a trait (or methods on a CrudController) following three conventions:

| Method | Called by | Purpose |
|---|---|---|
| `setupXxxRoutes($segment, $routeName, $controller)` | `Route::crud()` | register routes; **set `'operation' => 'xxx'`** on each route |
| `setupXxxDefaults()` | `CrudController::setupDefaults()` on every request | `allowAccess('xxx')`, add buttons to list/show, load config |
| public action(s) | the route | do the work / return a view / JSON |
| `setupXxxOperation()` (optional, in the controller) | when operation `xxx` runs | per-controller configuration (fields, validation…) |

Generators (`backpack/generators`, installed as dev dependency):
```bash
php artisan backpack:crud-operation Moderate            # empty operation → app/Http/Controllers/Admin/Operations/ModerateOperation.php
php artisan backpack:crud-form-operation Comment        # operation with a Backpack form (id in URL)
php artisan backpack:crud-form-operation Import --no-id # form without {id} (for top/bottom stack buttons)
```

## 1) Operation without interface (AJAX action on a row)
```php
namespace App\Http\Controllers\Admin\Operations;

use Illuminate\Support\Facades\Route;

trait PublishOperation
{
    protected function setupPublishRoutes($segment, $routeName, $controller)
    {
        Route::post($segment.'/{id}/publish', [
            'as'        => $routeName.'.publish',
            'uses'      => $controller.'@publish',
            'operation' => 'publish',
        ]);
    }

    protected function setupPublishDefaults()
    {
        $this->crud->allowAccess('publish');

        $this->crud->operation('publish', function () {
            $this->crud->loadDefaultOperationSettingsFromConfig();
        });

        $this->crud->operation(['list', 'show'], function () {
            // simplest: quick button with AJAX
            $this->crud->button('publish')->stack('line')->view('crud::buttons.quick')->meta([
                'icon'  => 'la la-check',
                'label' => 'Publish',
                // default href = {crud route}/{id}/{kebab(button name)} → .../{id}/publish (override with 'wrapper' => ['href' => fn ($entry, $crud) => ...])
                'ajax' => ['method' => 'POST', 'refreshCrudTable' => true,
                           'success_title' => 'Published', 'success_message' => 'Entry published.'],
            ]);
            // or a blade button: $this->crud->addButton('line', 'publish', 'view', 'crud::buttons.publish', 'beginning');
        });
    }

    public function publish($id)
    {
        $this->crud->hasAccessOrFail('publish');
        $entry = $this->crud->getEntry($id);   // getEntryWithLocale($id) for translatable models
        $entry->update(['published_at' => now()]);

        return response()->json(['message' => 'Published!']);   // quick-button shows `message`
    }
}
```
Use it: `use \App\Http\Controllers\Admin\Operations\PublishOperation;` in any CrudController. Control per-entry: `CRUD::setAccessCondition('publish', fn ($e) => ! $e->published_at);`.


## 2) Operation with its own page (GET form + POST handler) — manual
```php
protected function setupModerateRoutes($segment, $routeName, $controller)
{
    Route::get($segment.'/{id}/moderate',  ['as' => $routeName.'.getModerate',  'uses' => $controller.'@getModerateForm',  'operation' => 'moderate']);
    Route::post($segment.'/{id}/moderate', ['as' => $routeName.'.postModerate', 'uses' => $controller.'@postModerateForm', 'operation' => 'moderate']);
}

protected function setupModerateDefaults()
{
    $this->crud->allowAccess('moderate');
    $this->crud->operation('list', fn () => $this->crud->addButtonFromView('line', 'moderate', 'moderate', 'beginning'));
}

public function getModerateForm($id)
{
    $this->crud->hasAccessOrFail('moderate');
    $this->crud->setOperation('moderate');
    $this->data['entry'] = $this->crud->getEntry($id);
    $this->data['crud']  = $this->crud;
    $this->data['title'] = 'Moderate '.$this->crud->entity_name;
    return view('vendor.backpack.crud.operations.moderate', $this->data);
}

public function postModerateForm($id)
{
    $this->crud->hasAccessOrFail('moderate');
    // ... logic using $this->crud->getRequest()
    \Alert::success('Moderation saved.')->flash();
    return redirect($this->crud->route);
}
```
View skeleton: `@extends(backpack_view('blank'))`, set `$breadcrumbs`, `@section('header')` + `@section('content')` using theme cards. Button view `resources/views/vendor/backpack/crud/buttons/moderate.blade.php`:
```blade
@if ($crud->hasAccess('moderate', $entry))
  <a href="{{ url($crud->route.'/'.$entry->getKey().'/moderate') }}" class="btn btn-sm btn-link"><i class="la la-gavel"></i> Moderate</a>
@endif
```

## 3) Operation with a Backpack form (recommended: HasForm)
```php
namespace App\Http\Controllers\Admin\Operations;

use Backpack\CRUD\app\Http\Controllers\Operations\Concerns\HasForm;

trait CommentOperation
{
    use HasForm;

    protected function setupCommentRoutes(string $segment, string $routeName, string $controller): void
    {
        $this->formRoutes(
            operationName: 'comment',
            routesHaveIdSegment: true,     // false for top/bottom stack buttons (no entry)
            segment: $segment,
            routeName: $routeName,
            controller: $controller
        );
    }

    protected function setupCommentDefaults(): void
    {
        $this->formDefaults(
            operationName: 'comment',
            buttonStack: 'line',          // 'top' | 'bottom' require routesHaveIdSegment: false
            // buttonMeta: ['icon' => 'la la-comment', 'label' => 'Comment', 'wrapper' => ['target' => '_blank']],
        );

        // fields that ALWAYS exist for this operation (option b)
        // $this->crud->operation('comment', fn () => $this->crud->field('message')->type('textarea'));
    }

    public function getCommentForm(int $id)
    {
        $this->crud->hasAccessOrFail('comment');
        return $this->formView($id);
    }

    public function postCommentForm(int $id)
    {
        $this->crud->hasAccessOrFail('comment');

        return $this->formAction(id: $id, formLogic: function ($inputs, $entry) {
            // $inputs already validated if setValidation() was called in setupCommentOperation()
            $entry->comments()->create(['body' => $inputs['message']]);
            \Alert::success('Comment added!')->flash();
        });
    }
}
```
Per-controller fields (option a):
```php
public function setupCommentOperation(): void
{
    CRUD::field('message')->type('textarea');
    CRUD::field('rating')->type('number');
    CRUD::setValidation(CommentRequest::class);
}
```
Form operation defaults come from `config/backpack/operations/form.php`.

## 4) Bulk operation (acts on checked rows)
```php
protected function setupBulkArchiveRoutes($segment, $routeName, $controller)
{
    Route::post($segment.'/bulk-archive', ['as' => $routeName.'.bulkArchive', 'uses' => $controller.'@bulkArchive', 'operation' => 'bulkArchive']);
}
protected function setupBulkArchiveDefaults()
{
    $this->crud->allowAccess('bulkArchive');
    $this->crud->operation('list', function () {
        $this->crud->enableBulkActions();
        $this->crud->addButton('bottom', 'bulk_archive', 'view', 'bulk_archive', 'end');
    });
}
public function bulkArchive()
{
    $this->crud->hasAccessOrFail('bulkArchive');
    $ids = $this->crud->getRequest()->input('entries', []);
    $this->crud->model->whereIn('id', $ids)->update(['archived' => true]);
    return response()->json(['count' => count($ids)]);
}
```
Button view (`resources/views/vendor/backpack/crud/buttons/bulk_archive.blade.php`): show only `@if ($crud->hasAccess('bulkArchive') && $crud->get('list.bulkActions'))`, give it class `bulk-button`, read `crud.checkedItems` in JS, confirm with `swal({...})`, POST `{ entries: crud.checkedItems }` via `$.ajax`, then `crud.checkedItems = []; crud.table.ajax.reload();` and notify with `new Noty({type:'success', text:'...'}).show();`. Wrap JS in `@push('after_scripts') ... @endpush` and guard with `if (typeof bulkArchiveEntries != 'function') { ... }`.

## Access patterns for custom ops
- Tie to an existing op: `hasAccessOrFail('update')`.
- Or own key: `allowAccess('publish')` in Defaults, deny in `setup()` per role, `setAccessCondition('publish', fn($e) => ...)` per entry.

## Using features from other operations
Anything added while operation `print` is active is stored under `print.*` (e.g. `CRUD::column()` inside `setupPrintOperation()` → `print.columns`). Render them yourself in your view (`$crud->columns()`).

## Settings & macros for your operation
- Store config in `$this->crud->settings` via `setOperationSetting()`; load defaults from `config/backpack/operations/<name>.php` with `loadDefaultOperationSettingsFromConfig()` inside `operation('<name>', ...)`.
- Register CrudPanel macros inside `setupXxxDefaults()` within an operation closure so they exist only for that operation.

## Packaging an operation as an add-on
Use `jeroen-g/laravel-packager` with Backpack's skeleton:
```bash
composer require jeroen-g/laravel-packager --dev
php artisan packager:new --i --skeleton="https://github.com/Laravel-Backpack/addon-skeleton/archive/master.zip"
```
The skeleton's `AddonServiceProvider` auto-loads routes/views/config/lang/migrations from Laravel-like folders (`src/Http/...`, `resources/views`, `routes`, `config`, `database/migrations`). Namespaced views: `view('vendor-name.package-name::path')`. Themes use `https://github.com/Laravel-Backpack/theme-skeleton/archive/master.zip`.
