{{-- Rendered by Laravel Boost. Only emitted when the application requires backpack/crud; empty output = guideline skipped. --}}
@if ($assist->hasPackage('backpack/crud'))
# Backpack for Laravel

This application's admin panel is built with Backpack for Laravel v6. Always activate the `backpack`
skill before creating or changing CrudControllers, fields, columns, filters, buttons, operations,
widgets, the admin menu, themes or roles & permissions. Do not fetch the live Backpack docs first —
the skill is an offline, source-verified reference.

@if ($assist->hasPackage('backpack/pro'))
- `backpack/pro` is installed: PRO fields, columns, filters and operations are available — use them.
@else
- `backpack/pro` is NOT installed: use FREE features only, or tell the user the feature needs PRO.
@endif
@if ($assist->hasPackage('backpack/permissionmanager'))
- `backpack/permissionmanager` is installed: roles/permissions come from spatie/laravel-permission; mind the `backpack` guard vs `@can`.
@endif

## Standing constraints

- One CRUD panel = one Eloquent model (with `CrudTrait`) + one `XxxCrudController extends CrudController`
  + one `Route::crud('xxx', 'XxxCrudController')` line in `routes/backpack/custom.php` + one menu item.
- No operation is enabled by default; each is a trait on the controller. Fields/columns are stored per
  operation — Update does not inherit Create's fields unless `setupUpdateOperation()` calls `setupCreateOperation()`.
- Only fields defined for the current operation, and fillable on the model, are saved. There are no
  callbacks: use model events, field `->on('saving', ...)` events, or override `store()`/`update()`.
- Name relationship fields after the relation method (`category`, not `category_id`).
- JSON-storing fields (repeatable, table, upload_multiple, dropzone, multiple selects-from-array) need an
  `array` cast — except attributes handled by uploaders inside relationships/subfields.
- Uploads use `->withFiles()`; the password field never hashes on its own.
- In admin code use `backpack_user()`, `backpack_url()`, `backpack_view('blank')` — never the removed
  `backpack::` view namespace. Load assets with Basset.
- Default list ordering must be wrapped in `if (! CRUD::getRequest()->has('order'))` so column sorting keeps working.
@endif
