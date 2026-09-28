# Filters (PRO)

Shown above the List table; changing a filter reloads DataTables (search respects filters). Define in `setupListOperation()`. Filters contain **no default logic** — you must provide it in `whenActive()`.

## Fluent API
```php
CRUD::filter('name')
    ->type('text')                          // simple | text | date | date_range | dropdown | select2 | select2_multiple | select2_ajax | range | view
    ->label('The name')
    ->whenActive(function ($value) {        // aliases: logic(), ifActive()
        CRUD::addClause('where', 'name', 'LIKE', "%{$value}%");
    })
    ->whenInactive(function () {            // aliases: else(), fallbackLogic(), whenNotActive(), ifInactive(), ifNotActive()
        // optional
    })
    ->apply();                              // optional: run now (normally List applies all filters after setup)

CRUD::filter('name')->remove(); ->forget('attr'); ->before('x'); ->after('x'); ->makeFirst(); ->makeLast();
CRUD::filter('name')->debounce(1000);       // ms, any type
CRUD::filters(); CRUD::removeAllFilters();
```
Inside closures: filter value is the parameter; other inputs via `CRUD::getRequest()`; you can modify `$this->crud->query` directly. Logic runs only on `index()`/`search()`. For OR logic start with `where` then `orWhere` (wrap in a closure group to not break other constraints).

Legacy array syntax (still supported): `CRUD::addFilter($options, $values, $logic)`, `CRUD::modifyFilter($name, [...])`, `CRUD::removeFilter($name)`.

## Types
```php
// simple (toggle)
CRUD::filter('active')->type('simple')->whenActive(fn () => CRUD::addClause('active'));   // scope

// text
CRUD::filter('description')->type('text')->whenActive(fn ($v) => CRUD::addClause('where', 'description', 'LIKE', "%$v%"));

// date
CRUD::filter('birthday')->type('date')->whenActive(fn ($v) => CRUD::addClause('whereDate', 'birthday', $v));

// date_range — value is JSON {from, to}
CRUD::filter('created_between')->type('date_range')->label('Created')
    ->date_range_options(['timePicker' => true])          // daterangepicker.com options
    ->whenActive(function ($value) {
        $dates = json_decode($value);
        CRUD::addClause('where', 'created_at', '>=', $dates->from);
        CRUD::addClause('where', 'created_at', '<=', $dates->to.' 23:59:59');
    });

// dropdown
CRUD::filter('status')->type('dropdown')->values([1 => 'In stock', 2 => 'Out of stock'])
    ->whenActive(fn ($v) => CRUD::addClause('where', 'status', $v));

// select2 (values may be a closure — evaluated lazily)
CRUD::filter('category_id')->type('select2')->label('Category')
    ->values(fn () => \App\Models\Category::pluck('name', 'id')->toArray())
    ->whenActive(fn ($v) => CRUD::addClause('where', 'category_id', $v));

// select2_multiple — value is a JSON array
CRUD::filter('tags')->type('select2_multiple')
    ->values(fn () => \App\Models\Tag::pluck('name', 'id')->toArray())
    ->whenActive(function ($values) {
        CRUD::addClause('whereHas', 'tags', fn ($q) => $q->whereIn('tags.id', json_decode($values)));
    });

// select2_ajax with FetchOperation (method must be POST)
CRUD::filter('category_id')->type('select2_ajax')->label('Category')->placeholder('Pick a category')
    ->values(backpack_url('product/fetch/category'))->method('POST')
    // ->select_attribute('name')->select_key('id')
    ->whenActive(fn ($v) => CRUD::addClause('where', 'category_id', $v));

// select2_ajax with custom endpoint (GET by default; `term` param; return [id => text] or paginator)
Route::get('product/ajax-category-options', 'ProductCrudController@categoryOptions');  // above Route::crud()
public function categoryOptions(Request $request) {
    return \App\Models\Category::where('name', 'like', '%'.$request->input('term').'%')->pluck('name', 'id');
}

// range — value is JSON {from, to}
CRUD::filter('price')->type('range')->label_from('min')->label_to('max')
    ->whenActive(function ($value) {
        $range = json_decode($value);
        if ($range->from) CRUD::addClause('where', 'price', '>=', (float) $range->from);
        if ($range->to)   CRUD::addClause('where', 'price', '<=', (float) $range->to);
    });

// view (custom blade)
CRUD::filter('custom')->type('view')->view('package::filters.custom')->whenActive(fn ($v) => ...);
```

## Common recipes
```php
// MySQL ENUM values
CRUD::filter('published')->type('select2')
    ->values(fn () => \App\Models\Article::getEnumValuesAsAssociativeArray('published'))
    ->whenActive(fn ($v) => CRUD::addClause('where', 'published', $v));

// show soft-deleted (without TrashOperation)
CRUD::filter('trashed')->type('simple')->whenActive(fn () => $this->crud->query->onlyTrashed());

// role filter for users (PermissionManager does this)
CRUD::filter('role')->type('dropdown')->values(\Backpack\PermissionManager\app\Models\Role::pluck('name', 'id')->toArray())
    ->whenActive(fn ($v) => CRUD::addClause('whereHas', 'roles', fn ($q) => $q->where('role_id', $v)));
```

## addClause vs addBaseClause
- `addClause()` → "Showing 1 to 10 of 140 entries (filtered from 998)". Use in 99% of cases.
- `addBaseClause()` → applied before counting; totals hide the unfiltered count. Use to scope data the user must never know about (e.g. only own records).

## Custom filter type
Create `resources/views/vendor/backpack/crud/filters/<type>.blade.php` (`php artisan backpack:filter my_filter` or copy a PRO filter from `vendor/backpack/pro/resources/views/filters`). Variables: `$filter` (`->name`, `->type`, `->label`, `->currentValue`, `->values`, `->options`), `$crud`. Pattern: an `<li filter-name=... filter-type=...>` navbar dropdown; JS in `@push('crud_list_scripts')` updates the DataTables AJAX URL with `addOrUpdateUriParameter(url, name, value)`, reloads `crud.table`/`$('#crudTable').DataTable()`, marks the `li` active, and listens for `filter:clear`. Start from the stock `text` filter and adapt.
Load from another folder with `view_namespace`.
