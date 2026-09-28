# Columns (List & Show)

Every field type has a matching column type (v6). Mandatory in array syntax: `name`, `label`, `type` (label/type are guessed when using `CRUD::column('name')`).

## Columns API
```php
CRUD::column('name');                                   // add or modify
CRUD::column('price')->type('number')->prefix('$');
CRUD::column(['name' => 'price', 'type' => 'number'])->label('Price');
CRUD::column('price')->before('name'); ->after('name'); ->makeFirst(); ->makeLast();
CRUD::column('price')->remove(); ->forget('prefix');
CRUD::columns();   // collection
CRUD::group(CRUD::column('price'), CRUD::column('discount'))->prefix('$');
CRUD::addColumn([...]); CRUD::addColumns([...]); CRUD::setColumns(['name', 'description']); // replace all
CRUD::modifyColumn('name', [...]); CRUD::setColumnDetails('name', [...]); CRUD::setColumnsDetails(['a','b'], [...]);
CRUD::removeColumn('x'); CRUD::removeColumns([...]);
CRUD::addColumn([...])->beforeColumn('name'); ->afterColumn('name'); ->makeFirstColumn();
CRUD::setFromDb(); // guess all
```
Macros: `CrudColumn::macro('badge', function (...) { /** @var CrudColumn $this */ return $this; });`

## Common optional attributes
`prefix`, `suffix`, `limit` (chars, default 32/50), `escaped` (default true), `value` (closure `fn ($entry) => ...` or static — replaces deprecated `closure` column), `default`, `wrapper`, `linkTo`, `searchLogic`, `orderable`, `orderLogic`, `visibleInTable`, `visibleInModal`, `visibleInExport`, `visibleInShow` (bool|closure), `exportOnlyColumn`, `priority`, `key` (to reuse a name), `tab` (Show), `disk`, `withFiles`/`withMedia` (read uploaded file URLs).

## FREE column types
| Type | Notes / extra attributes |
|---|---|
| `text` | default. Dot notation `parent.title` for related attribute. `limit`, `prefix`, `suffix`. |
| `textarea` | `limit`, `escaped`. |
| `email` | mailto link; `limit`. |
| `phone` | tel link; `limit`. |
| `url` | link; `target` (`_blank` default), `element`, `rel`. |
| `number` | `decimals`, `dec_point`, `thousands_sep` (→ `number_format`), `prefix`, `suffix`. |
| `boolean` | Yes/No; `options => [0 => 'Inactive', 1 => 'Active']`. |
| `check` | check/x icon. |
| `switch` | like check. |
| `checkbox` | bulk-action checkbox column (added by `CRUD::enableBulkActions()`). |
| `date` / `datetime` | `format` uses **ISO (Carbon isoFormat)** tokens, defaults from `ui.default_date_format` / `default_datetime_format`. |
| `time` | `format` default `H:mm`. |
| `month` | `format` default `MMMM Y`. |
| `week` | "Week 25 2023". |
| `color` | swatch + hex; `showColorHex`. |
| `range` | progress bar; `attributes => ['min' => 0, 'max' => 100]` (top-level min/max from docs are ignored), `showMaxValue`, `showValue`, `progressColor`, `striped`. |
| `password` | asterisks; `limit`. |
| `hidden` | outputs text value. |
| `enum` | DB/PHP enum; `options` relabel; `enum_class` + `enum_function`. |
| `select` | 1-n: `name => 'category_id'`, `entity`, `attribute`, `model`, `limit`. |
| `select_multiple` | n-n: `name => 'tags'`, `entity`, `attribute`, `model`, `separator`. |
| `select_grouped` | like select. |
| `select_from_array` | `options => [...]`. |
| `radio` | `options => [0 => 'Draft', 1 => 'Published']`. |
| `checklist` | 1-n like select. |
| `checklist_dependency` | `name => 'roles,permissions'`, `subfields => ['primary' => [...], 'secondary' => [...]]`. |
| `relationship_count` | `name` = relation, `suffix`; loads all related items (slow) → prefer `withCount` + text. Orderable only with custom `orderLogic` on `{rel}_count`. |
| `row_number` | row index; `orderable => false`; `->makeFirstColumn()`. Not searchable. |
| `model_function` | `function_name => 'getSlugWithLink'`, `function_parameters`, `limit`, `escaped`. |
| `model_function_attribute` | + `attribute` on the returned object. |
| `json` | pretty JSON. |
| `multidimensional_array` | `visible_key => 'name'`. |
| `image` | thumbnail; `prefix`, `disk`, `height`, `width` (25px default). |
| `upload` | file link; `disk`. |
| `upload_multiple` | list of links; `disk`. |
| `summernote` | unescaped HTML. |
| `custom_html` | `value => '<span>..</span>'`; **not escaped** by default (`escaped => true` to escape). |
| `view` | `view => 'package::columns.x'` or path. |
| `closure` | DEPRECATED → use `value` closure on any column. |

## PRO column types
`relationship` (any relation; `attribute`, `entity`, `model`), `select2`, `select2_multiple`, `select2_nested`, `select2_grouped`, `select2_from_array`, `select2_from_ajax`, `select2_from_ajax_multiple`, `select_and_order`, `array` (enumerate JSON array), `array_count` (`suffix`), `table` (`columns => [...]`), `repeatable` (`subfields`), `markdown` (**not escaped**), `easymde`, `ckeditor`, `tinymce`, `wysiwyg` (unescaped), `date_picker` (= date), `datetime_picker` (= datetime), `date_range` (`name => 'start,end'`), `address_google`, `base64_image`, `browse`, `browse_multiple`, `dropzone` (`disk`), `icon_picker` (`iconset`), `slug`, `video`.

```php
CRUD::column('status')->type('select_from_array')->options(['draft' => 'Draft', 'published' => 'Published']);
CRUD::column('published_at')->type('datetime')->format('DD MMM YYYY, HH:mm');
CRUD::column('full_name')->type('text')->value(fn ($entry) => $entry->first_name.' '.$entry->last_name)
    ->searchLogic(fn ($query, $column, $term) => $query->orWhere('first_name', 'like', "%$term%")->orWhere('last_name', 'like', "%$term%"));
CRUD::column('features')->type('table')->columns(['name' => 'Name', 'price' => 'Price']);
```

## Search logic
```php
'searchLogic' => function ($query, $column, $searchTerm) { $query->orWhere('title', 'like', "%$searchTerm%"); },
'searchLogic' => false,     // not searchable
'searchLogic' => 'text',    // search like a text column
// relation:
'searchLogic' => function ($query, $column, $term) {
    $query->orWhereHas('cruise_ship', fn ($q) => $q->where('name', 'like', "%$term%"));
},
```
Custom column types are NOT searchable unless you give them `searchLogic`. Always use `orWhere...` in search closures.

## Order logic
```php
CRUD::column([
    'name' => 'category_id', 'type' => 'select', 'entity' => 'category', 'attribute' => 'name',
    'orderable' => true,
    'orderLogic' => fn ($query, $column, $dir) => $query->leftJoin('categories', 'categories.id', '=', 'articles.category_id')
                                                      ->orderBy('categories.name', $dir)->select('articles.*'),
]);
```

## Wrapper (links, badges)
```php
CRUD::column('category_id')->type('select')->entity('category')->attribute('name')->wrapper([
    // 'element' => 'a' (default)
    'href' => fn ($crud, $column, $entry, $related_key) => backpack_url('category/'.$related_key.'/show'),
    'target' => '_blank',
]);
CRUD::column('published')->type('boolean')->wrapper([
    'element' => 'span',
    'class' => fn ($crud, $column, $entry, $related_key) => $column['text'] == 'Yes' ? 'badge bg-success' : 'badge bg-secondary',
]);
```
Every wrapper attribute can be a string or `fn ($crud, $column, $entry, $related_key)`. Bootstrap 5 (Tabler) uses `bg-*` badge classes (`badge-success` is BS4/CoreUIv2).

## linkTo helpers
```php
CRUD::column('category')->linkTo('category.show');                     // route name (related key used)
CRUD::column('category')->linkTo(fn ($entry, $related_key) => backpack_url("category/$related_key/show"));
CRUD::column('x')->linkTo('my.route', ['param' => fn ($entry, $related_key) => $entry->slug]);
CRUD::column('category')->linkToShow()->linkTarget('_blank');
// array: 'linkTo' => 'category.show' | ['route' => 'category.show', 'parameters' => [...]] | closure
```
Route names of CRUDs are `{segment}.show` etc. (e.g. `category.show`).

## Visibility & responsive priority
```php
CRUD::column('description')->visibleInTable(false)->visibleInModal(false)->visibleInExport(false)->visibleInShow(true);
CRUD::column('obs')->priority(3);          // lower = more important; first & actions columns have 1
CRUD::setActionsColumnPriority(10000);
```
Tricks: hidden-but-exported, hidden-but-searchable columns.

## Same name twice
Give one of them a unique `key`:
```php
CRUD::column(['name' => 'parent_id', 'key' => 'parent_last_name', 'type' => 'select', 'entity' => 'parent', 'attribute' => 'last_name']);
```

## Escaping
All columns escape output (`{{ }}`) except `custom_html`, `markdown` (+ wysiwyg-type columns). Set `escaped => false` only for trusted/purified values (purify in an accessor, e.g. `mews/purifier` or `CleanHtmlOutput` cast).

## Override / create column types
- Override: `php artisan backpack:column --from=text` → `resources/views/vendor/backpack/crud/columns/text.blade.php`.
- Create: `php artisan backpack:column status_badge` → use `->type('status_badge')`. Variables: `$entry`, `$crud`, `$column`.
```blade
@php
    $column['value'] = $column['value'] ?? data_get($entry, $column['name']);
    if ($column['value'] instanceof \Closure) { $column['value'] = $column['value']($entry); }
    $column['escaped'] = $column['escaped'] ?? true;
    $column['text'] = $column['value'] ?? ($column['default'] ?? '-');
@endphp
<span>
    @includeWhen(!empty($column['wrapper']), 'crud::columns.inc.wrapper_start')
        <span class="badge {{ $column['value'] === 'paid' ? 'bg-success' : 'bg-warning' }}">
            @if($column['escaped']) {{ $column['text'] }} @else {!! $column['text'] !!} @endif
        </span>
    @includeWhen(!empty($column['wrapper']), 'crud::columns.inc.wrapper_end')
</span>
```
(Mirrors the stock `crud::columns.text` structure; the `wrapper_start/end` includes give you `wrapper`/`linkTo` support.)
