# Buttons (List & Show)

Stacks: `top` (next to Add), `line` (per row: Edit/Delete/Preview), `bottom` (below table — bulk buttons). Positions: `beginning` | `end` (default `beginning` for line, `end` for others). Operations add their own buttons in `setupXxxDefaults()`: `create` (top), `update`, `delete`, `show` (line), `reorder` (top). Buttons hide automatically when access is denied (`CRUD::denyAccess('delete')`).

## API
```php
// fluent
CRUD::button('export')->stack('top')->view('crud::buttons.quick');                 // stack aliases: to(), group(), section()
CRUD::button('open_google')->stack('line')->type('model_function')->content('openGoogle')->makeFirst();
CRUD::button('open_google')->modelFunction('openGoogle');                          // helper: type model_function + content (+ line stack); name is required
CRUD::button('name')->position('end'); ->before('x'); ->after('x'); ->makeFirst(); ->makeLast(); ->remove(); ->forget('attr');
CRUD::button('name')->meta(['label' => '...', 'icon' => 'la la-x', ...]);         // read as $button->meta in views

// classic
CRUD::addButton($stack, $name, $type /* view|model_function */, $content, $position);
CRUD::addButtonFromView('line', 'moderate', 'moderate', 'beginning');   // resources/views/vendor/backpack/crud/buttons/moderate.blade.php
CRUD::addButtonFromModelFunction('line', 'open_google', 'openGoogle', 'beginning');
CRUD::modifyButton('update', ['content' => 'admin.buttons.edit']);
CRUD::removeButton('delete'); CRUD::removeButtons(['a','b'], 'line'); CRUD::removeButtonFromStack('create', 'top');
CRUD::removeAllButtons(); CRUD::removeAllButtonsFromStack('line');
CRUD::orderButtons('line', ['update', 'delete', 'show']);
CRUD::moveButton('show', 'after', 'delete');
CRUD::buttons(); // collection
```
Stock button view names: `crud::buttons.create`, `crud::buttons.update`, `crud::buttons.delete`, `crud::buttons.show`, `crud::buttons.reorder`, `crud::buttons.quick` (+ PRO: clone, bulk_clone, bulk_delete, trash, restore, destroy, bulk_trash/restore/destroy).

Line buttons as dropdown: `CRUD::setOperationSetting('lineButtonsAsDropdown', true)` (+ `lineButtonsAsDropdownMinimum`, `lineButtonsAsDropdownShowBefore`) or globally in `config/backpack/operations/list.php`.

## Quick button (no blade file)
```php
CRUD::button('email')->stack('line')->view('crud::buttons.quick');
// → label "Email", icon none, href {crud route}/{id}/email (kebab of name), access key 'Email' (studly) or 'email'

CRUD::button('email')->stack('line')->view('crud::buttons.quick')->meta([
    'access'  => true,                   // or an access key string; default Str::studly(name) fallback name
    'label'   => 'Email',
    'icon'    => 'la la-envelope',
    'wrapper' => [
        'element' => 'a',
        'href'    => fn ($entry, $crud) => backpack_url("invoice/{$entry->id}/email"),
        'target'  => '_blank',
        'title'   => 'Send a new email to this user',
        // 'class' default: line 'btn btn-sm btn-link', top 'btn btn-outline-primary', bottom 'btn btn-sm btn-secondary'
    ],
    'ajax' => [                           // or simply 'ajax' => true
        'method' => 'POST',
        'refreshCrudTable' => true,
        'success_title' => 'Payment Reminder Sent', 'success_message' => 'Sent successfully.',
        'error_title' => 'Error', 'error_message' => 'Please try again.',
    ],
]);
CRUD::allowAccess('email');                                        // or per entry:
CRUD::setAccessCondition('email', fn ($entry) => $entry->hasVerifiedEmail());
```
AJAX endpoint can return `response()->json(['message' => '...'])` (shown as notification) or `abort(400, 'User already paid.')` (exception message shown). Register the route via a `setupEmailRoutes()` method or `routes/backpack/custom.php`.

## Custom blade button
`php artisan backpack:button approve` → `resources/views/vendor/backpack/crud/buttons/approve.blade.php`. Variables: `$entry` (line stack only), `$crud`, `$button`, `$button->meta`.
```blade
@if ($crud->hasAccess('approve', $entry))
  <a href="{{ url($crud->route.'/'.$entry->getKey().'/approve') }}" class="btn btn-sm btn-link" bp-button="approve">
    <i class="la la-thumbs-up"></i> <span>Approve</span>
  </a>
@endif
```
```php
CRUD::addButtonFromView('line', 'approve', 'approve', 'beginning');
CRUD::setAccessCondition('approve', fn ($entry) => $entry->status === 'pending');
```

## Model-function button
```php
CRUD::addButtonFromModelFunction('line', 'open_google', 'openGoogle', 'beginning');
// Model:
public function openGoogle($crud = false)
{
    return '<a class="btn btn-sm btn-link" target="_blank" href="https://google.com?q='.urlencode($this->name).'"><i class="la la-search"></i> Google it</a>';
}
```

## Top-stack button with JS (not bound to an entry)
Push JS to the end of the page:
```blade
@if ($crud->hasAccess('create'))
  <a href="javascript:void(0)" onclick="importTransactions(this)" data-route="{{ url($crud->route.'/import') }}" class="btn btn-outline-primary" data-button-type="import">
    <i class="la la-file-import"></i> Import
  </a>
@endif
@push('after_scripts')
<script>
  if (typeof importTransactions != 'function') {
    function importTransactions(button) {
      $.ajax({ url: $(button).attr('data-route'), type: 'POST',
        success: () => { new Noty({ type: 'success', text: 'Imported' }).show(); crud.table.ajax.reload(); },
        error:   () => new Noty({ type: 'warning', text: 'Import failed' }).show() });
    }
  }
</script>
@endpush
```
For line buttons used both in List (AJAX-rendered rows) and Show, wrap JS as: `@push('after_scripts') @if (request()->ajax()) @endpush @endif <script>...</script> @if (!request()->ajax()) @endpush @endif`, and guard with `typeof fn != 'function'`. Use `crud.addFunctionToDataTablesDrawEventQueue('fnName')` to re-run after each table draw.

## Override a stock button
Place a file with the same name in `resources/views/vendor/backpack/crud/buttons/` (`php artisan backpack:button --from=delete`).
