# Architecture, Installation, Lifecycle & Core API

## Requirements & install
- Laravel 10/11/12, PHP 8.1+, MySQL/PostgreSQL/SQLite/SQL Server.
- Install: `composer require backpack/crud && php artisan backpack:install` (interactive; `--no-interaction` to skip; `--timeout=600 --debug` if Packagist is slow). The installer asks to create an admin user assuming `name,email,password` columns — answer NO if your users table differs and use `php artisan backpack:user`.
- PRO: `php artisan backpack:require:pro` (adds `repo.backpackforlaravel.com` to composer.json + asks for token). Credentials: `composer config http-basic.backpackforlaravel.com <token-username> <token-password>`.
- Manual install (if the installer fails):
  ```bash
  php artisan vendor:publish --provider="Backpack\CRUD\BackpackServiceProvider" --tag="minimum"
  php artisan migrate
  php artisan backpack:publish-middleware
  composer require --dev backpack/generators
  php artisan basset:install --no-check --no-interaction
  php artisan backpack:require:theme-tabler   # or theme-coreuiv4 / theme-coreuiv2
  php artisan basset:check
  ```

## Files Backpack adds to your app
| File | Purpose |
|---|---|
| `config/backpack/base.php` | routing prefix, middleware, auth/guard, user model, avatar, registration, `useDatabaseTransactions` |
| `config/backpack/ui.php` | theme `view_namespace` + fallback, project name/logo, date formats, `breadcrumbs`, global `styles`/`scripts` (also `mix_*`, `vite_*`), `html_direction` |
| `config/backpack/crud.php` | translatable `locales`, `view_namespaces` for buttons/columns/fields/filters, `uploaders`, `file_name_generator`, `allowed_upload_extensions` |
| `config/backpack/operations/*.php` | per-operation defaults (list, create, update, show, reorder, form) — publish/edit to change defaults for ALL CRUDs |
| `config/backpack/theme-tabler.php` | theme config (overrides `ui.php` while the theme is active) |
| `app/Http/Middleware/CheckIfAdmin.php` | decides who is an admin → edit `checkIfUserIsAdmin($user)` if you have non-admin users |
| `routes/backpack/custom.php` | all admin routes (prefix + `web` + `admin` middleware + `App\Http\Controllers\Admin` namespace) |
| `resources/views/vendor/backpack/ui/inc/menu_items.blade.php` | sidebar/top menu items |

Defaults to know (`base.php`): `route_prefix => 'admin'`, `middleware_key => 'admin'`, `middleware_class => [CheckIfAdmin, ConvertEmptyStringsToNull, AuthenticateSession]`, `guard => 'backpack'`, `passwords => 'backpack'`, `user_model_fqn => config('auth.providers.users.model')`, `authentication_column => 'email'`, `email_column => 'email'`, `avatar_type => 'gravatar'`, `registration_open => env('BACKPACK_REGISTRATION_OPEN', APP_ENV==='local')`, `setup_auth_routes`, `setup_dashboard_routes`, `setup_my_account_routes`, `setup_password_recovery_routes`, `setup_email_verification_routes => false`, `useDatabaseTransactions => false` (wrap create/update + relationships in a DB transaction when true; default in v7).

## Routes file
```php
Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge((array) config('backpack.base.web_middleware', 'web'), (array) config('backpack.base.middleware_key', 'admin')),
    'namespace'  => 'App\Http\Controllers\Admin',
], function () { // custom admin routes
    Route::crud('tag', 'TagCrudController');
    Route::get('example-page', 'PageController@example')->name('page.example');
}); // this should be the absolute last line of this file
```
- `Route::crud($segment, $controller)` calls every `setupXxxRoutes($segment, $routeName, $controller)` method on the controller. Route names = `{group name}{segment}.{action}`.
- Keep the last line intact — generators append before it (`php artisan backpack:add-custom-route "Route::crud(...)"`).
- Nested URL segments are fine: `Route::crud('pet-shop/invoice', 'PetShop\InvoiceCrudController')` + `setRoute(config('backpack.base.route_prefix').'/pet-shop/invoice')`.

### Routes registered by standard operations
| Operation | Method & URI | Action | Route name |
|---|---|---|---|
| List | GET `{seg}/` | `index` | `{seg}.index` |
| List | POST `{seg}/search` | `search` (DataTables AJAX) | `{seg}.search` |
| List (details row) | GET `{seg}/{id}/details` | `showDetailsRow` | `{seg}.showDetailsRow` |
| Create | GET `{seg}/create` / POST `{seg}` | `create` / `store` | `.create` / `.store` |
| Update | GET `{seg}/{id}/edit` / PUT `{seg}/{id}` | `edit` / `update` | `.edit` / `.update` |
| Show | GET `{seg}/{id}/show` | `show` | `.show` |
| Delete | DELETE `{seg}/{id}` | `destroy` | `.destroy` |
| BulkDelete (PRO) | AJAX to `{seg}/bulk-delete` | `bulkDelete` | |
| Clone / BulkClone (PRO) | POST `{seg}/{id}/clone`, POST `{seg}/bulk-clone` | `clone`, `bulkClone` | |
| Reorder | GET/POST `{seg}/reorder` | `reorder` / `saveReorder` | `.reorder` / `.save.reorder` |
| Fetch | POST `{seg}/fetch/{kebab-entity}` | `fetchEntity()` | |
| InlineCreate | POST `{seg}/inline/create/modal`, POST `{seg}/inline/create` | `getInlineCreateModal`, `storeInlineCreate` | |
| Trash | DELETE `{seg}/{id}/trash`, PUT `{seg}/{id}/restore`, DELETE `{seg}/{id}/destroy` | `trash`, `restore`, `destroy` | |

Set `protected $setupDetailsRowRoute = false;` on a controller to skip the details-row route.

## Controller lifecycle (from `CrudController::__construct` middleware)
```
$this->crud = app('crud'); $this->crud->setRequest($request);
setupDefaults();                         // every setupXxxDefaults() of used traits
setup();                                 // yours: runs for ALL operations
setupConfigurationForCurrentOperation(); // 1) CRUD::operation(...) closures  2) setupXxxOperation()
→ action method (index/store/…)
```
- Operation closures: `CRUD::operation('list', fn() => ...)`, `CRUD::operation(['create','update'], fn() => ...)`.
- Everything in `setup()` runs on every request of the controller (list, search AJAX, create, store, …) — keep it light; put per-op stuff in `setupXxxOperation()`.
- `setupXxxOperation` name = `'setup'.Str::studly($operationName).'Operation'` (e.g. operation `inlineCreate` → `setupInlineCreateOperation`).
- Titles/headings set in `setup()` are overwritten by actions — set them inside actions or via `CRUD::setHeading('x', 'create')`.

## CrudPanel "basic info"
```php
CRUD::setModel(\App\Models\Example::class);
CRUD::setRoute(config('backpack.base.route_prefix').'/example'); // or backpack_url('example')
CRUD::setRouteName('admin.example');  // alternative
CRUD::setEntityNameStrings('example', 'examples');
CRUD::setFromDb();   // auto columns/fields from DB (generators put this in); replace with explicit definitions
CRUD::with('relationship');  // eager load in every operation
CRUD::setQuery(Model::where(...)); // replace the base query
```

## Current operation / action
```php
CRUD::getOperation();          // 'list', 'create', ...
CRUD::setOperation('list');
CRUD::getCurrentOperation();
CRUD::getActionMethod();       // 'index', 'create', 'store', 'edit', ...
CRUD::actionIs('create');
CRUD::getCurrentEntry();       // entry in update/show/etc (false if none)
CRUD::getCurrentEntryId();
CRUD::getEntry($id); CRUD::getEntries();
CRUD::getFields(); CRUD::columns(); CRUD::getAllFieldNames();   // (no getColumns() on CrudPanel)
\Route::getCurrentRoute()->getAction()['operation'];
```

## Access API
```php
CRUD::allowAccess('list');  CRUD::allowAccess(['list','create']);
CRUD::denyAccess(['update','delete']);
CRUD::allowAccessOnlyTo(['list','show']);   // deny all others
CRUD::denyAllAccess();
CRUD::setAccessCondition(['update','delete'], fn ($entry) => $entry->user_id === backpack_user()->id);
CRUD::hasAccess('update', $entry);  CRUD::hasAccessOrFail('create'); // 403
CRUD::hasAccessToAll([...]); CRUD::hasAccessToAny([...]);
```
- Stored in `$crud->settings['<op>.access']`. Stock operations call `allowAccess('<op>')` in their Defaults. Deny in `setup()` (runs after defaults).
- Trash actions check `trash`, `restore`, `destroy`; bulk trash checks `bulkTrash`, `bulkRestore`, `bulkDestroy`.
- Quick-button access key = `Str::studly($buttonName)` with fallback to raw name.

## Settings API
```php
CRUD::setOperationSetting('showEntryCount', false);          // current operation
CRUD::setOperationSetting('contentClass', 'col-md-8', 'create');
CRUD::getOperationSetting('key', 'op'); CRUD::hasOperationSetting('key');
CRUD::set('create.view', 'admin.products.create'); CRUD::get('list.bulkActions'); CRUD::has('x.y');
$this->crud->loadDefaultOperationSettingsFromConfig();   // used in custom operations' defaults
```
Operation config defaults (v6.8): see bottom of this file.

## Custom views & content class per CRUD
```php
CRUD::setListView('admin.products.list');   CRUD::setCreateView(...); CRUD::setEditView(...);
CRUD::setShowView(...); CRUD::setReorderView(...); CRUD::setDetailsRowView(...);
// setRevisionsView()/setRevisionsTimelineView() are listed in docs but not in crud 6.8 core (revise add-on territory)
CRUD::set('create.view', 'crud::yourfolder.yourview');

CRUD::setListContentClass('col-md-12');   CRUD::setCreateContentClass('col-md-8 col-md-offset-2');
CRUD::setEditContentClass(...);  // alias setUpdateContentClass()
CRUD::setShowContentClass(...); CRUD::setReorderContentClass(...);
CRUD::set('create.contentClass', 'col-md-12');  // or config/backpack/operations/create.php 'contentClass'
```
Publish a stock operation view to change it globally: `php artisan backpack:publish crud/list` (→ `resources/views/vendor/backpack/crud/list.blade.php`).

## Titles, headings, subheadings
```php
CRUD::setTitle('Title', 'create'); CRUD::setHeading('Heading', 'create'); CRUD::setSubheading('Sub', 'create');
CRUD::getTitle('create'); CRUD::getHeading(); CRUD::getSubheading();
```

## Macros / extending
- CrudPanel is macroable: `$this->crud->macro('name', function () { /* $this = CrudPanel */ });`
- `CrudField::macro()` / `CrudColumn::macro()` for custom fluent methods (register in a ServiceProvider with `hasMacro` guard).
- Replace CrudPanel entirely: `$this->app->extend('crud', fn () => new \App\MyExtendedCrudPanel);` (upgrade risk).
- Share logic across all CRUDs: create `App\Http\Controllers\Admin\BaseCrudController extends CrudController` and extend it.

## Model traits provided by CRUD
- `Backpack\CRUD\app\Models\Traits\CrudTrait` (required; includes fake fields, enums `getEnumValuesAsAssociativeArray('col')`, identifiable attribute, relationship helpers, uploads helpers, translatable helpers).
- Identifiable attribute (what relationship selects show): `protected $identifiableAttribute = 'title';` or `public function identifiableAttribute() { return 'title'; }`. Accessors used as `attribute` need `$appends`.
- `Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations`, `...\Sluggable`, `...\SluggableScopeHelpers`.

## Default operation configs (crud 6.8.x)
- **list**: `contentClass col-md-12`, `responsiveTable true`, `persistentTable true`, `searchableTable true`, `searchDelay 400`, `persistentTableDuration false`, `defaultPageLength 10`, `pageLengthMenu [[10,25,50,100,-1],[10,25,50,100,'backpack::crud.all']]`, `actionsColumnPriority 1`, `lineButtonsAsDropdown false`, `lineButtonsAsDropdownMinimum 1`, `lineButtonsAsDropdownShowBefore 0`, `resetButton true`, `searchOperator 'like'`, `showEntryCount true`, `eagerLoadRelationships true`.
- **create**: `contentClass 'col-md-12 bold-labels'`, `tabsType horizontal|vertical`, `groupedErrors true`, `inlineErrors true`, `autoFocusOnFirstField true`, `defaultSaveAction 'save_and_back'`, `showSaveActionChange true`, `showCancelButton true`, `warnBeforeLeaving false`.
- **update**: same as create + `showDeleteButton false`, `showTranslationNotice true`, `eagerLoadRelationships false`.
- **show**: `contentClass col-md-12`, `setFromDb true`, `timestamps true`, `softDeletes false`, `tabsEnabled false`, `tabsType horizontal`.
- **reorder**: `contentClass`, `escaped false`.
- **form** (custom form operations): like create, `showSaveActionChange false`.
(Check your published copies in `config/backpack/operations/` — they may differ.)
