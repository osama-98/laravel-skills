# Gotchas, Debugging & Documentation Errata (verified against backpack/crud 6.8.17 source)

## Documentation errata — trust these corrections
| Docs say | Reality (v6 source) |
|---|---|
| Fluent syntax page: `use Backpack\CRUD\app\Library\CrudPanel\CrudPanel as CRUD;` | Facade is `use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;` |
| Release notes: `->hasFiles()` | Method is `->withFiles()` (macro in `crud/src/macros.php`) |
| Update docs: "POST to `/entity/{id}/edit`" | Update route is `PUT {segment}/{id}` (name `{segment}.update`); edit form is GET `{segment}/{id}/edit` |
| Some pages show PRO ops under `\Backpack\CRUD\app\Http\Controllers\Operations\...` | That works for Clone, BulkClone, BulkDelete, Fetch, InlineCreate (CRUD proxy traits that `use` the Pro trait). Trash, BulkTrash, CustomView, Dropzone exist ONLY as `\Backpack\Pro\Http\Controllers\Operations\...` |
| hasMany notes: "delete related entry entirely if you define `'force_delete' => false`" | It's `'force_delete' => true` |
| Quick button AJAX example nests `'ajax'` inside `'wrapper'` | `'ajax'` is a sibling key of `'wrapper'` inside `meta` |
| Filters select2_multiple fluent example uses `function($value)` then `json_decode($values)` | Name the closure parameter consistently (`$values`) |
| Details row not labelled PRO in the List page | Details Row and Export Buttons are PRO (see Free vs Paid table) |
| List docs: persistent table off by default | Shipped `config/backpack/operations/list.php` has `'persistentTable' => true` — check your published config |
| Columns `relationship`: "add `public $identifiableAttribute`" / Fields: `protected $identifiableAttribute` | Either works (`property_exists` check); or define method `identifiableAttribute()` |
| Old examples use `backpack::` view namespace / `@extends('backpack::layout')` | Removed in v6 → `@extends(backpack_view('blank'))` |
| Old examples use `sidebar_content.blade.php`, `resources/views/vendor/backpack/base` | v6: `menu_items.blade.php` and `.../vendor/backpack/ui` |
| Old examples use `fa fa-*` icons, `badge-success`, `PNotify` | v6 + Tabler: Line Awesome `la la-*`, Bootstrap 5 `bg-success`, Noty |
| Old examples `'name' => ['start', 'end']` for date_range / checklist_dependency | v6: comma string `'start_date,end_date'` |
| `color_picker`, `address_algolia` fields | Removed in v6 → `color`, `address_google`/`google_map` |
| Content class setter `setUpdateContentClass` vs `setEditContentClass` | Both exist |
| Widgets page custom widget: `@includeWhen(..., 'backpack::widgets.inc.wrapper_start')` | No `backpack::` namespace in v6 → `backpack_view('widgets.inc.wrapper_start')` |
| Details row global override at `crud/inc/details_row.blade.php` | Default view is `crud::details_row` → override `resources/views/vendor/backpack/crud/details_row.blade.php` |
| `saveAllInputsExcept` operation setting (store/update comments) | Not read by crud 6.8 — use the `strippedRequest` setting |
| Cheat sheet `CRUD::getColumns()` | Method is `CRUD::columns()` |
| `range` column: top-level `max`/`min` | View reads `attributes.max` / `attributes.min` |
| Show tabs: "adding `tab` to a column displays tabs unless tabsEnabled is false" | Shipped `show.tabsEnabled` is `false` → call `CRUD::setOperationSetting('tabsEnabled', true)` or `CRUD::setTabsType(...)` |
| `progressClass` on `progress` widget | Only `progress_white` reads it |
| `CRUD::button()->...` without name | `button()` requires a name |

## Things that silently don't work (and why)
- **Field not saved** → not in `$fillable` / in `$guarded`; or not defined for this operation (Update needs its own fields); or name is a relation but the relation method is missing; or you added an input manually in a view without a field (stripped request).
- **Relationship field shows IDs** → set `attribute` or `$identifiableAttribute` on the related model (accessors need `$appends`).
- **Relationship field empty on edit** → wrong relation name/return type (relations must declare return types or be detectable; use `entity` explicitly), or custom AJAX endpoint doesn't answer `keys`.
- **AJAX select returns nothing** → Fetch route is POST; field `method` must be POST for custom endpoints registered as POST; multi-word entity needs kebab `data_source`; FetchOperation must be on the SAME controller as the field.
- **InlineCreate button missing / 404** → secondary controller lacks `InlineCreateOperation` or it's listed before `CreateOperation`; `inline_create` entity must be singular route segment.
- **Validation from field attributes ignored** → `setValidation()` called before defining fields.
- **Repeatable/table/select_and_order data weird** → missing `array` cast; `table` inside repeatable double-encoded.
- **Upload not stored** → missing `withFiles()`; `storage:link`; extension not allow-listed (svg!); attribute cast in a relationship subform; `enctype` handled automatically — don't write your own form.
- **Files not deleted with entry** → define upload field in `setupDeleteOperation()` (or model `deleted` event); soft deletes keep files.
- **Custom order breaks column sorting / can't reset** → wrap `orderBy` in `if (! CRUD::getRequest()->has('order'))`; don't put clauses in `setup()` unless intended for every operation.
- **Bulk checkboxes missing** → first column hidden/not rendered, or first column contains a link.
- **`@can` always false in admin** → guard mismatch (see `permission-manager.md`).
- **"Trait not found" for Trash** → namespace `Backpack\Pro` (not `Backpack\PRO`), or PRO not installed/authorized.
- **Assets/styles broken after deploy** → `APP_URL`, `storage:link`, `basset:clear && basset:cache`, hard refresh.
- **Slow List** → N+1: `CRUD::with('relation')` / `eagerLoadRelationships`, use `withCount` instead of `relationship_count`, `showEntryCount => false` for millions of rows, `ajax` relationship fields in forms.
- **Title/heading set in setup() not applied** → set via `setHeading('x', 'list')` or inside actions (actions overwrite).
- **Events defined in controller don't fire elsewhere** → they're only registered when that controller runs; use Observers for app-wide logic.
- **`deleting` field event not firing** → field must also be defined in `setupDeleteOperation()`.

## Debugging tips
- List AJAX errors appear in a modal; inspect `/search` requests with Laravel Debugbar or the browser Network tab.
- `dd(CRUD::getFields())`, `dd(CRUD::columns())`, `dd($this->crud->settings())`-style dumps inside setup methods; `Widget::name('x')->dd()`.
- `php artisan route:list --path=admin` to confirm operation routes & names.
- `php artisan backpack:version` for exact versions; look at `vendor/backpack/crud/src/app/Http/Controllers/Operations/<Op>.php` for behaviour.

## Security checklist
- Gate admins in `CheckIfAdmin`; never leave "all users are admins" when the users table has customers.
- FormRequest `authorize()` → `backpack_auth()->check()` (plus permission checks if needed).
- Access per operation + `setAccessCondition()` for ownership; hide sensitive fields/columns by role.
- Purify WYSIWYG/markdown input; keep `escaped => true` for untrusted columns.
- Restrict relationship options (`options`, fetch `query`, `relation_options_query`) — enforced at save time.
- Keep upload allow-lists tight; never allow `svg/html` from untrusted uploaders on the same domain.
- Keep `registration_open` false in production.
