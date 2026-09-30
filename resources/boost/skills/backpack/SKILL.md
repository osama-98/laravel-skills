---
name: backpack
description: "Expert guide for building admin panels with Backpack for Laravel v6 (backpack/crud 6.x, backpack/pro 2.x, backpack/permissionmanager 7.x, backpack/theme-tabler 1.x). Activate whenever the task touches CrudControllers, CRUD fields, columns, filters or buttons, operations (List, Create, Update, Show, Delete, Reorder, Fetch, InlineCreate, Clone, Bulk*, Trash, custom operations), uploads, widgets, the admin menu, themes, roles and permissions, or anything under app/Http/Controllers/Admin, routes/backpack, resources/views/vendor/backpack or config/backpack."
license: MIT
metadata:
  author: osama-98
---

# Backpack for Laravel v6

Targets this stack (verified against backpack/crud 6.8.17, permissionmanager 7.3.1, theme-tabler 1.2.19):

| Package | Constraint | Notes |
|---|---|---|
| `backpack/crud` | `^6.7` | Free core (MIT). |
| `backpack/pro` | `^2.2` | Paid add-on: PRO fields, columns, filters, operations, chart widget. |
| `backpack/permissionmanager` | `^7.2` | Users / Roles / Permissions UI on top of `spatie/laravel-permission`. |
| `backpack/theme-tabler` | `^1.2` | Default v6 theme (Bootstrap 5, dark mode, 9 layouts). |

First check `composer.json`: **if `backpack/pro` is required, every feature labeled PRO is available** — use it without hedging. If it is not, stick to FREE features (fields/columns marked FREE, no filters, no Fetch/InlineCreate/Clone/Bulk*/Trash) or tell the user the feature needs PRO. Never suggest Backpack v7 syntax on a v6 project.

## How to use this skill

1. Read this file fully — it holds the mental model and the rules that prevent 90% of Backpack bugs.
2. Then open only the reference(s) you need from `references/` (index at the bottom).
3. Before editing, inspect the project: the target CrudController, its Model (`$fillable`/`$guarded`, `$casts`, relationships, `CrudTrait`), its FormRequest, `routes/backpack/custom.php`, `resources/views/vendor/backpack/ui/inc/menu_items.blade.php`, and `config/backpack/*`.
4. When unsure whether an API exists in this exact version, check `vendor/backpack/crud/src` (and `vendor/backpack/pro/src`) — it's the source of truth. Useful places:
   - `vendor/backpack/crud/src/app/Library/CrudPanel/Traits/*.php` (CrudPanel API)
   - `vendor/backpack/crud/src/app/Http/Controllers/Operations/*.php` (operation traits)
   - `vendor/backpack/crud/src/resources/views/crud/{fields,columns,buttons}` (free views)
   - `vendor/backpack/pro/resources/views/{fields,columns,filters}` (PRO views)
   - `vendor/backpack/crud/src/config/backpack/` (default configs)

## Mental model (memorize)

- **One CRUD Panel = one Eloquent model = one `XxxCrudController extends CrudController`** + one line `Route::crud('xxx', 'XxxCrudController')` in `routes/backpack/custom.php` + one `<x-backpack::menu-item>` in `menu_items.blade.php` + (optional) a FormRequest.
- The Model **must** `use \Backpack\CRUD\app\Models\Traits\CrudTrait;` and must have editable attributes in `$fillable` (or not in `$guarded`).
- **No operations are enabled by default.** An operation = a trait on the controller (`use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;`). Each trait brings: `setupXxxRoutes()` (routes, read by `Route::crud`), `setupXxxDefaults()` (access + buttons + config), and public action methods.
- `CRUD::` (facade `Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD`) and `$this->crud` are **the same singleton** CrudPanel object.
- **Request lifecycle** (inside the controller's middleware closure):
  1. `setupDefaults()` → runs every `setupXxxDefaults()` of used operations (allowAccess, default buttons, `loadDefaultOperationSettingsFromConfig()`).
  2. `setup()` → your code that applies to ALL operations (`setModel`, `setRoute`, `setEntityNameStrings`, global access rules, model events).
  3. Operation closures registered with `CRUD::operation('list', fn() => ...)` for the current operation.
  4. `setupXxxOperation()` for the current operation only (e.g. `setupListOperation()`, `setupCreateOperation()`).
  5. The action method runs (`index()`, `search()`, `create()`, `store()`, `edit()`, `update()`, `show()`, `destroy()` ...).
- Fields, columns, buttons, filters are **stored per operation** in `$crud->settings` (e.g. `list.columns`, `create.fields`). Defining a field in `setupCreateOperation()` does NOT define it for Update — that's why generated code does `protected function setupUpdateOperation() { $this->setupCreateOperation(); }`.
- Settings API: `CRUD::setOperationSetting($key, $value, ?$operation)`, `getOperationSetting()`, `hasOperationSetting()`, or dotted `CRUD::set('create.contentClass', ...)`, `CRUD::get(...)`. Global defaults live in `config/backpack/operations/{list,create,update,show,reorder,form}.php`.
- Views resolve with a fallback chain: your `resources/views/vendor/backpack/crud/...` override → package. Theme views: `resources/views/vendor/backpack/theme-tabler/...` → theme package → `resources/views/vendor/backpack/ui/...` → crud `ui`. Always use `backpack_view('blank')` (never `backpack::` namespace, removed in v6).

## Canonical CrudController (v6 style)

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ProductRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ProductCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\FetchOperation;        // PRO (proxy trait)
    use \Backpack\CRUD\app\Http\Controllers\Operations\BulkDeleteOperation;   // PRO (proxy trait)

    public function setup()
    {
        CRUD::setModel(\App\Models\Product::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/product');
        CRUD::setEntityNameStrings('product', 'products');
    }

    protected function setupListOperation()
    {
        CRUD::column('name');
        CRUD::column('category');                      // relationship name → PRO `relationship` column
        CRUD::column('price')->type('number')->prefix('$')->decimals(2);
        CRUD::column('active')->type('boolean');

        CRUD::filter('active')->type('simple')        // PRO filter
            ->whenActive(fn () => CRUD::addClause('where', 'active', 1));

        CRUD::enableExportButtons();                   // PRO
    }

    protected function setupCreateOperation()
    {
        CRUD::setValidation(ProductRequest::class);

        CRUD::field('name')->size(6);
        CRUD::field('price')->type('number')->prefix('$')->size(6)->attributes(['step' => 'any']);
        CRUD::field('category')->type('relationship')->ajax(true)->inline_create(true); // PRO
        CRUD::field('tags')->type('relationship');                                        // n-n
        CRUD::field('photo')->type('image')->withFiles(['disk' => 'public', 'path' => 'products']);
        CRUD::field('description')->type('summernote')->tab('Details');
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation()
    {
        $this->setupListOperation();   // or $this->autoSetupShowOperation(); then tweak
    }

    public function fetchCategory()
    {
        return $this->fetch(\App\Models\Category::class);
    }
}
```

Route (`routes/backpack/custom.php`, inside the existing group — keep the file's last line intact):
```php
Route::crud('product', 'ProductCrudController');
```
Menu (`resources/views/vendor/backpack/ui/inc/menu_items.blade.php`):
```blade
<x-backpack::menu-item title="Products" icon="la la-box" :link="backpack_url('product')" />
```
Icons are Line Awesome (`la la-*`).

## Golden rules & gotchas (read before writing code)

1. **Field `name` = DB column or relationship method.** If a field name equals a relationship method name, Backpack infers `entity/model/attribute/multiple/pivot` automatically. For relationships prefer the **relationship name** (`category`), not the FK (`category_id`), with the PRO `relationship` field. Use `'entity' => false` to disable inference.
2. **Only fields defined for the current operation are saved** (the request is stripped to field names) AND they must be fillable. To save extra computed values use model events, a hidden field, or the `strippedRequest` operation setting — see `references/operations.md`.
3. **There are no callbacks.** Use Eloquent events (`Model::saving(...)` in `setup()`), field events (`CRUD::field('x')->on('saving', fn($entry) => ...)`), or override `store()/update()` via `use CreateOperation { store as traitStore; }`.
4. **Passwords are not hashed by the password field.** Hash in a model mutator/cast, or override store/update (pattern in `references/operations.md`).
5. **Validation via field attributes**: call `CRUD::setValidation()` with no args **after** defining the fields.
6. **Casts required** for JSON-storing fields: `repeatable`, `table`, `select_and_order`, `checklist` (no pivot), `select_from_array`/`select2_from_array` with `allows_multiple`, `browse_multiple`, `address_google` (store_as_json), `video`, `dropzone`, `upload_multiple`. Cast to `array`. **Exception:** do NOT cast attributes handled by Uploaders (`withFiles`) inside relationships/subfields.
7. **Uploads**: use `->withFiles()` (not `hasFiles()` — that name in the release notes is wrong). Run `php artisan storage:link`. To delete files on entry delete, also define the upload field inside `setupDeleteOperation()`. `svg/html` are not allowed by default (allow-list since crud 6.8.17).
8. **Custom column/field HTML is escaped** except `custom_html`, `markdown`, `summernote`, `ckeditor`, `tinymce`, `wysiwyg` columns. Set `'escaped' => false` only for trusted data; purify input for WYSIWYG fields.
9. **List ordering**: wrap custom order in `if (! CRUD::getRequest()->has('order')) { CRUD::orderBy(...); }` so column-sorting still works. Clauses added in `setup()` apply to ALL operations (and can't be reset).
10. **`addClause` vs `addBaseClause`**: `addBaseClause` hides the "filtered from N" total — use it to scope data per tenant/user.
11. **Access** is per operation key: `allowAccess/denyAccess/setAccessCondition(['update','delete'], fn($entry) => ...)`. Buttons auto-hide when access is denied. For custom buttons check `$crud->hasAccess('op', $entry)`.
12. **Relationship select security (v6.8+)**: options closures / fetch `query` closures are also enforced at save time. For custom AJAX endpoints, set `relation_options_query`; if `data_source` doesn't match `fetchXxx` naming, set `relation_options_query_source`.
13. **Bulk actions** put the checkbox in the first column: keep the first column visible and without `<a>` links.
14. **Fetch + InlineCreate**: the main controller uses `FetchOperation` + `fetchEntity()`; the secondary controller uses `CreateOperation` **then** `InlineCreateOperation` (order matters). Multi-word entities need `data_source => backpack_url('main/fetch/kebab-name')`.
15. **Translatable models**: use `Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations` (not Spatie's), columns JSON/TEXT, and do NOT cast translatable string columns.
16. **Reorder** needs integer `parent_id` (nullable), `lft`, `rgt`, `depth` (default 0) or `reorderColumnNames` setting.
17. **Update route is `PUT {segment}/{id}`**, create is `POST {segment}`, delete is `DELETE {segment}/{id}`. Route names: `{segment}.index|search|showDetailsRow|create|store|edit|update|destroy|show|reorder|save.reorder`.
18. **Admin helpers**: in admin code use `backpack_user()`, `backpack_auth()`, `backpack_url()`, `backpack_view()`, `backpack_middleware()`, `backpack_pro()` instead of Laravel's `auth()`/`url()`.
19. **Basset** serves CSS/JS: load custom assets with `@basset(...)` in blade or `Widget::add()->type('script'|'style')`. After deploy: `php artisan basset:cache` (it only pre-caches literal `@basset('…')` strings; `@bassetBlock` output and `@basset($variable)` are written at first render). Never `basset:clear` a disk shared by several servers. Never put per-request values inside a `@bassetBlock`: it is saved once per name. Your own CSS/JS belongs in Vite (`vite_scripts`/`vite_styles`, in the theme config — it wins over `ui.php`): local files loaded through Basset are copied once and never refreshed. Use `BASSET_DEV_MODE=true` locally. Multi-server rules in `references/cli-deploy.md`.
20. **Don't over-publish vendor views**: prefer a new custom field/column/button/filter type over overriding a stock one (overrides stop receiving updates).

## Task playbooks

**New CRUD for an existing model** → `php artisan backpack:crud product` (singular) → add `CrudTrait` to the model if missing → replace `CRUD::setFromDb()` with explicit columns/fields → set validation → check route + menu item → optionally `setupShowOperation`.

**Relationship field choice** → see `references/relationships.md` (decision table for every Eloquent relation, incl. subforms, pivot extras, morphTo).

**Custom action button** (e.g. "Send email") → quick button: `CRUD::button('email')->stack('line')->view('crud::buttons.quick')->meta([...])` + route via `setupEmailRoutes()` + `CRUD::allowAccess('email')`. For a full form → `php artisan backpack:crud-form-operation Email`. See `references/custom-operations.md` and `references/buttons.md`.

**Restrict by role/permission** → see `references/permission-manager.md` (CrudPermissionTrait pattern + guard/`@can` notes).

**Dashboard / custom page** → `php artisan backpack:page Reports` or publish `ui/dashboard` and use widgets. See `references/ui-widgets-themes.md`.

**Conditional form logic (show/hide fields)** → CrudField JS API in `references/js-api.md`, loaded via `Widget::add()->type('script')->content('assets/js/admin/forms/product.js')`.

## References index

| File | Read when |
|---|---|
| `references/architecture.md` | Install, files Backpack adds, routing, lifecycle, settings API, config files, access API, titles/headings, custom views & content classes |
| `references/operations.md` | Any built-in operation: List (details row, export, custom query/order, persistent, large tables, custom views), Create/Update (validation, events, stripped request, translatable, delete button), Show (tabs, auto setup), Delete/BulkDelete, Clone/BulkClone, Reorder, Revise, Fetch, InlineCreate, Trash/BulkTrash, save actions |
| `references/custom-operations.md` | Creating operations (with/without UI, bulk, with Backpack form via HasForm), macros, reusing features |
| `references/fields.md` | Fields API + every FREE and PRO field type with its definition; fake fields, tabs, wrappers, custom & overridden field types |
| `references/relationships.md` | Picking the right field per relation type, subfields, pivot extras, fallback/force delete, morphTo, dependent selects, security guard |
| `references/columns.md` | Columns API + every FREE and PRO column type; search/order logic, wrappers, linkTo, visibility, priority, escaping, custom columns |
| `references/filters.md` | PRO filters: fluent API, every filter type, examples, custom filters, debounce |
| `references/buttons.md` | Stacks, API, quick buttons (AJAX), custom buttons (view/model function), ordering, dropdown line buttons |
| `references/uploads-validation.md` | Uploaders (`withFiles`, options, allowed extensions, naming, subfields, pivot), MediaLibrary (`withMedia`), dropzone, validation rules (`ValidUpload`, `ValidUploadMultiple`, `ValidDropzone`) |
| `references/ui-widgets-themes.md` | Widgets, alerts (PHP & JS), breadcrumbs, menu components, dashboard, custom pages, themes & Tabler config, CSS hooks, auth customization, helpers, translations |
| `references/js-api.md` | CrudField JavaScript library and custom-field JS conventions |
| `references/permission-manager.md` | PermissionManager 7.x install/config, guards & `@can`, extending UserCrudController, permission-based access |
| `references/cli-deploy.md` | All artisan commands (crud + generators), basset, deploy, updating, PRO install/auth errors |
| `references/gotchas-errata.md` | Collected pitfalls + places where the official docs are wrong/outdated (verified against v6 source) |
| `templates/` | Copy-ready stubs (replace `{{placeholders}}`): `CrudController.php.stub`, `CrudRequest.php.stub`, `Operation.php.stub` (AJAX quick-button op), `FormOperation.php.stub` (HasForm), `field.blade.php.stub`, `column.blade.php.stub`, `button.blade.php.stub` |
