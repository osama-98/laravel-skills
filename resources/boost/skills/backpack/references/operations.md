# Built-in Operations (FREE + PRO)

Operation traits and namespaces (v6):

| Operation | Trait | Tier |
|---|---|---|
| List | `\Backpack\CRUD\app\Http\Controllers\Operations\ListOperation` | FREE |
| Create | `...\Operations\CreateOperation` | FREE |
| Update | `...\Operations\UpdateOperation` | FREE |
| Show | `...\Operations\ShowOperation` | FREE |
| Delete | `...\Operations\DeleteOperation` | FREE |
| Reorder | `...\Operations\ReorderOperation` | FREE |
| Revise | `\Backpack\ReviseOperation\ReviseOperation` (package `backpack/revise-operation`) | FREE add-on |
| BulkDelete | `...\Operations\BulkDeleteOperation` (proxy → `\Backpack\Pro\Http\Controllers\Operations\BulkDeleteOperation`) | PRO |
| Clone | `...\Operations\CloneOperation` (proxy → Pro) | PRO |
| BulkClone | `...\Operations\BulkCloneOperation` (proxy → Pro) | PRO |
| Fetch | `...\Operations\FetchOperation` (proxy → Pro) | PRO |
| InlineCreate | `...\Operations\InlineCreateOperation` (proxy → Pro) | PRO |
| Trash | `\Backpack\Pro\Http\Controllers\Operations\TrashOperation` | PRO |
| BulkTrash | `\Backpack\Pro\Http\Controllers\Operations\BulkTrashOperation` | PRO |
| CustomView | `\Backpack\Pro\Http\Controllers\Operations\CustomViewOperation` | PRO |
| Dropzone | `\Backpack\Pro\Http\Controllers\Operations\DropzoneOperation` (needed by the `dropzone` field) | PRO |

`...` = `\Backpack\CRUD\app\Http\Controllers\Operations`. The proxy traits in CRUD throw `BackpackProRequiredException` if PRO isn't installed; either namespace works. Namespace case matters: `Backpack\Pro`, not `Backpack\PRO`.

Overriding any action: alias the trait method and call it.
```php
use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation { store as traitStore; }
public function store() { /* before */ $response = $this->traitStore(); /* after */ return $response; }
```
Publishing stock buttons: `php artisan backpack:button --from=delete` (also `clone`, `bulk_clone`, `bulk_delete`, `reorder`, `trash`, `restore`, `destroy`, `bulk_trash`, `bulk_restore`, `bulk_destroy`, `create`, `update`, `show`).

Every operation page supports widgets in `before_content` / `after_content` — add them inside `setupXxxOperation()` (see `ui-widgets-themes.md`).

---

## List

Actions: `index()` (renders `crud::list`) and `search()` (DataTables AJAX: runs on load, search, filter, paginate). Configure in `setupListOperation()`; minimum = define columns (`columns.md`). Buttons: `buttons.md`. Filters: `filters.md`.

### Custom query / scope
```php
CRUD::addClause('active');                 // local scope
CRUD::addClause('type', 'car');            // dynamic scope
CRUD::addClause('where', 'name', '=', 'car');
CRUD::addClause('whereHas', 'posts', fn ($q) => $q->activePosts());
CRUD::addBaseClause('where', 'tenant_id', backpack_user()->tenant_id); // hides "filtered from N"
CRUD::groupBy(...); CRUD::limit(...);
CRUD::setQuery(Product::where('status', 'active'));
$this->crud->query->withCount('tags');      // then CRUD::column('tags_count')
```
Clauses in `setup()` apply to all operations and can't be reset by the Reset button.

### Default order (keep column sorting working)
```php
if (! CRUD::getRequest()->has('order')) {
    CRUD::orderBy('updated_at', 'desc');
}
```
Default order = primary key desc.

### Details row (PRO)
```php
CRUD::enableDetailsRow();                          // "+" per row → AJAX GET {seg}/{id}/details → showDetailsRow($id)
Widget::add()->to('details_row')->type('progress')->value(135)->description('Progress')->progress(50); // widgets get $entry,$crud
CRUD::setDetailsRowView('admin.articles_details_row');   // custom view with $entry, $crud
CRUD::disableDetailsRow();
```
Global template override: `resources/views/vendor/backpack/crud/details_row.blade.php` (default view is `crud::details_row`). Or override `showDetailsRow($id)` in the controller. Disable the route: `protected $setupDetailsRowRoute = false;`.

### Export buttons (PRO)
`CRUD::enableExportButtons();` → Copy/Excel/CSV/PDF/Print + column visibility. Exports **only visible columns of the current page** (user can pick "All" in page length).
Column flags: `visibleInExport` (true = always export, false = never), `visibleInTable` (true = can't hide; false = starts hidden), `exportOnlyColumn => true` (not in table, always exported). CSV separator: publish `crud/inc/export_buttons` and set `fieldSeparator: ';'` on `csvHtml5`.

### Line buttons as dropdown
```php
CRUD::setOperationSetting('lineButtonsAsDropdown', true);
CRUD::setOperationSetting('lineButtonsAsDropdownMinimum', 5);    // below this count → inline
CRUD::setOperationSetting('lineButtonsAsDropdownShowBefore', 3); // first N stay inline
```

### Other list settings
```php
CRUD::disableResponsiveTable(); CRUD::enableResponsiveTable();
CRUD::enablePersistentTable(); CRUD::disablePersistentTable();
CRUD::setOperationSetting('persistentTableDuration', 120);   // minutes; false = forever
CRUD::setOperationSetting('showEntryCount', false);          // huge tables: no COUNT(*), simple prev/next pagination
CRUD::setDefaultPageLength(25);
CRUD::setPageLengthMenu([[25, 50, 100, -1], [25, 50, 100, 'All']]);   // never use 0; -1 = all
CRUD::setActionsColumnPriority(10000);   // let actions column hide first on small screens
CRUD::setOperationSetting('searchOperator', 'ilike');   // Postgres
CRUD::setOperationSetting('searchableTable', false);
CRUD::setOperationSetting('resetButton', false);
CRUD::enableBulkActions();   // adds checkbox column (bulk ops call this for you)
```

### Custom Views for List (PRO)
```php
use \Backpack\Pro\Http\Controllers\Operations\CustomViewOperation;

public function setupListOperation()
{
    // ... columns, filters, buttons
    $this->runCustomViews([                // or runCustomViews() to use method names as titles
        'setupLast12MonthsView' => __('Last 12 months'),
        'setupLast6MonthsView'  => __('Last 6 months'),
    ]);
}
public function setupLast6MonthsView() { CRUD::addClause('where', 'created_at', '>=', now()->subMonths(6)); CRUD::column('secret')->remove(); }
```
Views apply on top of the current query; use `CRUD::setQuery()` for a fresh one. A dropdown appears next to "Add".

### Debugging
AJAX errors show in a modal. Use laravel-debugbar and select the latest AJAX (`/search`) request to inspect queries.

---

## Create & Update

Create: GET `{seg}/create` → `create()`; POST `{seg}` → `store()`. Update: GET `{seg}/{id}/edit` → `edit()`; PUT `{seg}/{id}` → `update()`. Only fields defined for the operation AND fillable get saved.

```php
protected function setupCreateOperation()
{
    CRUD::setValidation(ProductRequest::class);
    CRUD::field('name');
}
protected function setupUpdateOperation()
{
    $this->setupCreateOperation();
    CRUD::setValidation(UpdateProductRequest::class);  // different rules for update
    // $entry = CRUD::getCurrentEntry(); if ($entry->locked) { CRUD::field('price')->attributes(['readonly'=>'readonly']); }
    CRUD::setOperationSetting('eagerLoadRelationships', true);  // avoid N+1 in relation fields
    CRUD::setOperationSetting('showDeleteButton', true);        // delete from edit form (or a redirect URL string); needs DeleteOperation + delete access
}
```

### Validation — three ways
```php
// (A) FormRequest (authorize() usually: return backpack_auth()->check();)
CRUD::setValidation(TagRequest::class);

// (B) rules array (+ optional messages)
CRUD::setValidation(['name' => 'required|min:2'], ['name.required' => 'Name please']);

// (C) on fields — then call setValidation() with NO args AFTER all fields
CRUD::field('email')->validationRules('required|email|unique:users,email')
                    ->validationMessages(['required' => 'Email is required']);
CRUD::setValidation();
```
- Required asterisks are derived from the FormRequest (`setRequiredFields()` is called by `setValidation`).
- Subfields: nested rules (`'items.*.name' => 'required'`).
- Upload fields: `ValidUpload`, `ValidUploadMultiple`, `ValidDropzone` (see `uploads-validation.md`).
- Manual: `CRUD::validateRequest()` returns the validated request; `CRUD::unsetValidation()` to skip re-validation.

### "Callbacks" (Backpack has none) — use:
```php
// 1) Model events registered in the controller (only fire for requests through this controller)
public function setup() {
    Product::saving(fn ($entry) => $entry->updated_by = backpack_user()->id);  // create + update
}
public function setupCreateOperation() {
    Product::creating(fn ($entry) => $entry->author_id = backpack_user()->id);
}
// 2) Field events (registered only in operations where the field is defined)
CRUD::field('name')->on('saving', fn ($entry) => $entry->author_id = backpack_user()->id);
CRUD::field('name')->events(['saving' => fn ($e) => ..., 'saved' => fn ($e) => ...]);
// 3) Override store()/update() (see top of file)
// 4) App-wide logic → Observers / mutators on the model
```
A `deleting` field event only registers if the field is also defined in `setupDeleteOperation()`.

### Saving values that aren't fields (stripped request)
By default the request is stripped to defined field names. Options:
```php
// a) hidden field
CRUD::field('author_id')->type('hidden')->value(backpack_user()->id);  // user can tamper → prefer (b)/(c)
// b) strippedRequest closure (setup or setupXxxOperation)
CRUD::setOperationSetting('strippedRequest', function ($request) {
    $input = $request->only(CRUD::getAllFieldNames());
    $input['updated_by'] = backpack_user()->id;
    return $input;
});
// c) invokable class for config/backpack/operations/update.php 'strippedRequest' => App\Http\Requests\StripBackpackRequest::class
// NOTE: the `saveAllInputsExcept` setting mentioned in older docs comments is NOT read by crud 6.8 —
// use strippedRequest (b/c) to keep extra inputs, e.g. return $request->except(['_token', '_method', '_http_referrer', '_current_tab', '_save_action']);
```
In a FormRequest's `prepareForValidation()` you can also do `\CRUD::set('update.strippedRequest', fn($r) => ...)`.

### Password hashing pattern (from PermissionManager)
```php
use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation { store as traitStore; }
use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; }

public function store()  { $this->handlePassword(); return $this->traitStore(); }
public function update() { $this->handlePassword(); return $this->traitUpdate(); }

protected function handlePassword(): void
{
    CRUD::setRequest(CRUD::validateRequest());
    $request = CRUD::getRequest();
    $request->request->remove('password_confirmation');
    $request->input('password')
        ? $request->request->set('password', Hash::make($request->input('password')))
        : $request->request->remove('password');   // keep old password on update
    CRUD::setRequest($request);
    CRUD::unsetValidation(); // already validated
}
```
(Or simply `protected $casts = ['password' => 'hashed'];` on Laravel 10+, and remove empty password on update.)

### Translatable models (spatie/laravel-translatable)
1. MySQL 5.7+/Postgres JSON; translatable columns JSON or TEXT.
2. `use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;` (Backpack's, not Spatie's) + `protected $translatable = ['name', 'options'];`
3. Do NOT cast translatable strings; do cast array-valued translations (e.g. `extras`).
4. Locales in `config/backpack/crud.php` → `locales`.
5. Create stores in the current app locale; Edit shows a language switcher (and "copy from" when empty). Show also shows the switcher.
6. Translatable slugs: `Backpack\CRUD\app\Models\Traits\SpatieTranslatable\Sluggable` + `SluggableScopeHelpers` (requires `cviebrock/eloquent-sluggable`); non-translatable slugs → use cviebrock's traits directly.
7. Translatable fake fields: put `extras` in `$translatable`, remove from `$casts`.

### Form UX settings
`tabsType` (`horizontal|vertical`), `groupedErrors`, `inlineErrors`, `autoFocusOnFirstField`, `defaultSaveAction`, `showSaveActionChange`, `showCancelButton`, `warnBeforeLeaving`, `contentClass` — via `CRUD::setOperationSetting()` or config.

### Save actions
Defaults: `save_and_back`, `save_and_edit`, `save_and_new` (+ `save_and_preview`, added by ShowOperation when used).
```php
CRUD::addSaveAction([
    'name' => 'save_and_publish',
    'redirect' => fn ($crud, $request, $itemId) => $crud->route,
    'visible' => fn ($crud) => backpack_user()->can('publish'),
    'referrer_url' => fn ($crud, $request, $itemId) => $crud->route,
    'button_text' => 'Save & Publish',
    'order' => 1,
]);
CRUD::addSaveActions([...]); CRUD::replaceSaveActions([...]); CRUD::setSaveActions([...]); // alias of replace
CRUD::removeSaveAction('save_and_preview'); CRUD::removeSaveActions([...]);
CRUD::orderSaveAction('save_and_edit', 1); CRUD::orderSaveActions(['save_and_edit', 'save_and_back']);
CRUD::setOperationSetting('showSaveActionChange', false);   // no toast when switching
CRUD::setOperationSetting('defaultSaveAction', 'save_and_edit');
```

---

## Show
GET `{seg}/{id}/show` → `show($id)`. Without `setupShowOperation()` it auto-guesses columns from the DB (`setFromDb`, removes sensitive/fk/timestamps per config). Defining `setupShowOperation()` = blank slate.
```php
protected function setupShowOperation()
{
    $this->autoSetupShowOperation();        // keep the guessing, then tweak
    CRUD::column('total')->type('number');
    CRUD::column('secret')->remove();
    CRUD::column('name')->tab('General');
    CRUD::setOperationSetting('tabsEnabled', true);  // REQUIRED: config show.tabsEnabled ships false and columns don't auto-enable tabs
    // or CRUD::setTabsType('vertical');             // enables tabs + sets type
}
```
Or `$this->setupListOperation();` to mirror List. Use `visibleInShow` (bool/closure) per column. Buttons on Show use stacks too.

---

## Delete & BulkDelete (PRO)
- Delete: AJAX DELETE `{seg}/{id}` → `destroy($id)`; soft-deletes if the model uses `SoftDeletes`.
```php
use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation { destroy as traitDestroy; }
public function destroy($id) { CRUD::hasAccessOrFail('delete'); /* ... */ return CRUD::delete($id); }
protected function setupDeleteOperation() { CRUD::field('photo')->type('upload')->withFiles(); } // so files get deleted
```
- BulkDelete: `use ...\BulkDeleteOperation;` → checkbox column + "Delete selected" bottom button. Override `bulkDelete()`. First column must be visible and not a link.

## Clone & BulkClone (PRO)
- POST `{seg}/{id}/clone` → `clone($id)` using `replicate()->push()`. Does NOT copy 1-1, n-1, n-n relations (1-n stays via FK).
```php
use \Backpack\CRUD\app\Http\Controllers\Operations\CloneOperation;
CRUD::set('clone.redirect_after_clone', true);   // → edit page of the clone
CRUD::set('clone.redirect_after_clone', fn ($entry) => backpack_url('product/'.$entry->id.'/show'));
// exclude unique attributes on the model:
public function replicate(?array $except = null) { return parent::replicate(['sku']); }
```
- BulkClone: `use ...\BulkCloneOperation;` → POST `{seg}/bulk-clone` → `bulkClone()`.

## Reorder (FREE)
DB: integer `parent_id` (nullable), `lft`, `rgt`, `depth` defaulting to 0, fillable.
```php
use \Backpack\CRUD\app\Http\Controllers\Operations\ReorderOperation;
protected function setupReorderOperation()
{
    CRUD::set('reorder.label', 'name');
    CRUD::set('reorder.max_level', 2);    // 0 = infinite; 1 = flat ordering
    CRUD::set('reorder.escaped', true);   // escape labels
    CRUD::setOperationSetting('reorderColumnNames', ['parent_id' => 'custom_parent_id', 'lft' => 'left', 'rgt' => 'right', 'depth' => 'deep']);
}
```
Adds a "Reorder" top button. Override `reorder()` / `saveReorder()`. `CRUD::disableReorder()`, `CRUD::isReorderEnabled()`. Pair with PRO `select2_nested` field (needs `children()` relation).

## Revise (FREE add-on)
```bash
composer require backpack/revise-operation
cp vendor/venturecraft/revisionable/src/migrations/2013_04_09_062329_create_revisions_table.php database/migrations/ && php artisan migrate
```
Model: `use \Venturecraft\Revisionable\RevisionableTrait;` + `public function identifiableName() { return $this->name; }` (if another bootable trait: `public static function boot() { parent::boot(); }`). Controller: `use \Backpack\ReviseOperation\ReviseOperation;`. Adds a line button to see/undo changes.

## Fetch (PRO) — AJAX source for relationship/select2_from_ajax fields & select2_ajax filter
```php
use \Backpack\CRUD\app\Http\Controllers\Operations\FetchOperation;

public function fetchTag()      // route: POST {seg}/fetch/tag   (method name → kebab: fetchProductCategory → fetch/product-category)
{
    return $this->fetch(\App\Models\Tag::class);
    // or configure:
    return $this->fetch([
        'model' => \App\Models\Tag::class,        // required
        'searchable_attributes' => ['name', 'description'], // [] = don't guess (use your own query)
        'paginate' => 10,
        'searchOperator' => 'LIKE',
        'query' => fn ($model) => $model->where('active', 1),   // ALSO enforced at save time
        'append_attributes' => ['full_label'],   // accessors added only for fetch results
    ]);
}
```
- Custom search with `searchable_attributes => []` and `request()->input('q')` inside `query`.
- Global operator: `config/backpack/operations/fetch.php` → `['searchOperator' => 'ILIKE']`; per controller: `CRUD::setOperationSetting('searchOperator', 'ILIKE')` inside the fetch operation's setup method (documented as `setupFetchOperationOperation()`).
- Security: out-of-scope IDs are dropped (HasMany/MorphMany/BelongsToMany/MorphToMany) or rejected with 422 (BelongsTo). If `data_source` is manual and the name doesn't match, set `'relation_options_query_source' => 'fetchProductCategory'` on the field; for non-Fetch endpoints use `relation_options_query`.
- Customize all fetch calls of a controller: `use FetchOperation { fetch as traitFetch; } public function fetch($arg) { ...; return $this->traitFetch($arg); }`.
- With `select2_ajax` filter: `->values(backpack_url('product/fetch/category'))->method('POST')` (+ `select_attribute`, `select_key`).

## InlineCreate (PRO) — create related entries from a modal
Main controller (e.g. Article): relationship field with `'ajax' => true, 'inline_create' => true` + `FetchOperation` + `fetchCategory()`.
Secondary controller (e.g. Category): `use CreateOperation; use InlineCreateOperation;` (**InlineCreate after Create**). Optional `setupInlineCreateOperation()` to change fields/validation for the modal.
```php
CRUD::field('category')->type('relationship')->ajax(true)->inline_create(true);          // belongsTo → /admin/category/inline/create
CRUD::field('tags')->type('relationship')->ajax(true)->inline_create(['entity' => 'tag']); // n-n: entity singular
CRUD::field('tags')->inline_create([
    'entity' => 'tag',
    'force_select' => true,                       // select the new entry
    'modal_class' => 'modal-dialog modal-xl',
    'modal_route' => route('tag-inline-create'),
    'create_route' => route('tag-inline-create-save'),
    'add_button_label' => 'New tag',
    'include_main_form_fields' => ['field1'],     // read with request('main_form_fields')
]);
```
Works for BelongsTo, BelongsToMany, MorphToMany (hasMany needs nullable FK/default). Multi-word names → set `data_source` (kebab). Script widgets for the secondary form must use `Widget::add()->type('script')->inline()->content(...)`.

## Trash & BulkTrash (PRO)
Model must use `SoftDeletes` (`$table->softDeletes()` migration). Replace `DeleteOperation` with TrashOperation (use it last).
```php
use \Backpack\Pro\Http\Controllers\Operations\TrashOperation;
use \Backpack\Pro\Http\Controllers\Operations\BulkTrashOperation;

public function setupTrashOperation()
{
    CRUD::setOperationSetting('withTrashFilter', false);          // show trashed rows mixed in
    CRUD::setAccessCondition(['update', 'show'], fn ($e) => ! $e->trashed()); // needed if filter disabled
    CRUD::setOperationSetting('canDestroyNonTrashedItems', true); // allow permanent delete directly
    if (! backpack_user()->hasRole('superadmin')) CRUD::denyAccess('destroy');
}
```
Routes: DELETE `{id}/trash`, PUT `{id}/restore`, DELETE `{id}/destroy`; bulk: `bulk-trash`, `bulk-restore`, `bulk-destroy`. Adds Trash button + "Trashed" filter. Override `trash()`, `restore()`, `destroy()`, `bulkTrash()`...
