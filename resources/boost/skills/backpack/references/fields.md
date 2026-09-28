# Fields (Create / Update / any form operation)

## Fields API
Call inside `setupCreateOperation()`, `setupUpdateOperation()`, `setupInlineCreateOperation()`, a form operation's setup, or `CRUD::operation([...], fn () => ...)`. Calls apply to the **current operation**.
```php
CRUD::field('price');                                   // add or modify (addOrModify semantics)
CRUD::field('price')->type('number')->prefix('$');      // any chained method becomes an attribute
CRUD::field(['name' => 'price', 'type' => 'number'])->label('Price'); // array + fluent (v6)
CRUD::field('price')->size(6);                          // wrapper class form-group col-md-6
CRUD::field('price')->tab('Pricing');
CRUD::field('price')->before('name'); ->after('name'); ->makeFirst(); ->makeLast();
CRUD::field('price')->remove(); ->forget('prefix');
CRUD::group(CRUD::field('price'), CRUD::field('discount'))->prefix('$');  // same attribute on many
CRUD::addField([...]); CRUD::addFields([[...], [...]]); CRUD::modifyField('name', [...]);
CRUD::removeField('x'); CRUD::removeFields([...]); CRUD::removeAllFields();
CRUD::field('x')->on('saving', fn ($entry) => ...);      // Eloquent event bound to this field
CRUD::field('x')->validationRules('required')->validationMessages(['required' => '...']);
CRUD::field('x')->withFiles([...]);                      // uploaders (see uploads-validation.md)
```
Macros: `CrudField::macro('customThing', function (...) { /** @var CrudField $this */ return $this; });` (register in a ServiceProvider, guard with `hasMacro`).

## Attributes
- **Mandatory**: `name` (unique per form; DB column, relationship method, dotted `relation.column` for hasOne/morphOne, or comma list for multi-input fields like `start_date,end_date`).
- **Recommended**: `label` (guessed from name), `type` (guessed from DB column type or relationship).
- **Presentation**: `prefix`, `suffix`, `default` (create only), `value` (force value), `hint` (HTML ok), `attributes` (HTML attrs on input: `placeholder`, `class`, `readonly`, `disabled`, `step`, `aria-label`, `data-init-function`...), `wrapper` (attrs of wrapping div, e.g. `['class' => 'form-group col-md-6']`; `wrapper => false` removes it — useful for `custom_html`/`view`), `tab`.
- **Relationship**: `entity` (relation method; `false` disables inference), `model`, `attribute` (shown attribute), `multiple`, `pivot`, `relation_type` (don't override), `options` (closure `fn ($query) => $query->where(...)->get()`), `relation_options_query`, `relation_options_query_source`.
- **Validation**: `validationRules`, `validationMessages`.
- **Events**: `events => ['saving' => fn ($entry) => ...]`.
- **Uploads**: `withFiles`, `withMedia` (add-on), `disk`, `prefix` (path).
- **Fake**: `fake => true`, `store_in => 'extras'`.
- **View**: `view_namespace` (load a custom type from another folder/package).

### Fake fields (store several inputs as JSON in one column)
```php
CRUD::field('meta_title')->fake(true)->store_in('metas');
CRUD::field('meta_description')->type('textarea')->fake(true)->store_in('metas');
```
Model: add `metas` to `$fillable`, `protected $fakeColumns = ['metas'];`, `protected $casts = ['metas' => 'array'];` (default column is `extras`). Translatable fakes → put column in `$translatable`, remove from `$casts`.

### Tabs
Fields with `->tab('Name')` get grouped; fields without a tab render above the tabs. Vertical tabs: `CRUD::setOperationSetting('tabsType', 'vertical')`.

### Accessibility
Labels aren't programmatically bound to inputs; add `'attributes' => ['aria-label' => '...']` where needed.

---

## FREE field types

| Type | Definition essentials / notes |
|---|---|
| `text` | basic; `prefix`, `suffix`, `attributes`, `hint`. Dotted name `parent.title` edits a hasOne/morphOne attribute. |
| `textarea` | |
| `email` | |
| `number` | `attributes => ['step' => 'any']` for decimals; `prefix`/`suffix`. |
| `password` | **does not hash** — hash via cast/mutator/store override. |
| `hidden` | `value => 'x'` (or `default`). |
| `checkbox` | boolean. |
| `switch` | boolean toggle; `color`, `onLabel`, `offLabel`; CSS var `--bg-switch-checked-color`. |
| `boolean` | boolean input (exists in source; like checkbox). |
| `radio` | `options => [0 => 'Draft', 1 => 'Published']`, `inline => true/false`. |
| `select_from_array` | `options => ['one' => 'One']`, `allows_null`, `default`, `allows_multiple` (cast array). |
| `select` | 1-n (belongsTo): `name => 'category_id'`, optional `entity => 'category'`, `model`, `attribute`, `options` closure. |
| `select_multiple` | n-n: `name => 'tags'`, `entity`, `model`, `attribute`, `pivot => true`, `options`. |
| `select_grouped` | `name => 'article_id'`, `entity => 'article'`, `attribute`, `group_by => 'category'`, `group_by_attribute => 'name'`, `group_by_relationship_back => 'articles'`. |
| `checklist` | n-n checkboxes: `name/entity => 'roles'`, `attribute`, `model`, `pivot => true`, `show_select_all => true`, `number_of_columns => 3`. Without pivot → cast column to array. |
| `checklist_dependency` | two linked checklists (roles→permissions). `name => 'roles,permissions'`, `field_unique_name`, `subfields => ['primary' => [label,name,entity,entity_secondary,attribute,model,pivot,number_columns,options], 'secondary' => [..., entity_primary]]`. v6: name is comma string, not array. |
| `date` | HTML5 date. |
| `datetime` | HTML5 datetime-local. If the attribute is cast to datetime add mutator `setXAttribute($v) { $this->attributes['x'] = \Carbon\Carbon::parse($v); }`. |
| `time`, `month`, `week` | HTML5 inputs (month/week not supported in all browsers — PRO `date_picker` with `minViewMode: months` as alternative). |
| `color` | `default => '#000000'` (replaces removed `color_picker`). |
| `range` | `attributes => ['min' => 0, 'max' => 10]`. |
| `url` | |
| `enum` | DB ENUM (MySQL only) or PHP enum: cast `'status' => StatusEnum::class` (BackedEnum auto) or `enum_class`, `enum_function` for labels; `options` to relabel. |
| `summernote` | WYSIWYG; `options => ['toolbar' => [['font', ['bold','underline','italic']]]]`. Not sanitized — purify. |
| `upload` | single file; `withFiles => true` (or `->withFiles([...])`); validate with `ValidUpload`. |
| `upload_multiple` | JSON array of paths (use TEXT/JSON column, cast array); `withFiles`; `ValidUploadMultiple`; sends `clear_{name}` — add it to `$guarded` if you use guarded. |
| `custom_html` | `value => '<hr>'` (use `wrapper => false` for fieldsets). Not saved. |
| `view` | `view => 'partials.custom-ajax-button'`; `wrapper => false` supported. |

```php
CRUD::field(['name' => 'status', 'label' => 'Status', 'type' => 'radio', 'options' => [0 => 'Draft', 1 => 'Published'], 'inline' => true]);
CRUD::field(['name' => 'template', 'type' => 'select_from_array', 'options' => ['one' => 'One', 'two' => 'Two'], 'allows_null' => false, 'default' => 'one']);
CRUD::field(['label' => 'Category', 'type' => 'select', 'name' => 'category_id', 'entity' => 'category', 'attribute' => 'name',
             'options' => fn ($query) => $query->orderBy('name')->where('depth', 1)->get()]);
CRUD::field(['label' => 'Roles', 'type' => 'checklist', 'name' => 'roles', 'entity' => 'roles', 'attribute' => 'name',
             'model' => \Backpack\PermissionManager\app\Models\Role::class, 'pivot' => true, 'show_select_all' => true]);
CRUD::field('status')->type('enum')->enum_class(\App\Enums\StatusEnum::class)->enum_function('readableStatus');
```

---

## PRO field types

| Type | Definition essentials / notes |
|---|---|
| `relationship` | The universal relation field (see `relationships.md`). `name` = relation method. Options: `attribute`, `placeholder`, `ajax => true` (+ FetchOperation), `data_source`, `minimum_input_length`, `delay`, `dependencies`, `method`, `include_all_form_fields`, `inline_create`, `subfields`, `pivotSelect`, `allow_duplicate_pivots`, `pivot_key_name`, `fallback_id`, `force_delete`, morph: `addMorphOption()`, `morphTypeField()`, `morphIdField()`, `morphOptions`. |
| `select2` | like `select` but select2; `default`. |
| `select2_multiple` | like `select_multiple`; `select_all => true`. |
| `select2_nested` | hierarchical (Reorder-style model with `children()` + lft/rgt/depth). `name => 'category_id'`, `entity`, `attribute`. |
| `select2_grouped` | like `select_grouped`. |
| `select2_from_array` | `options`, `allows_null`, `default`, `allows_multiple` (cast array), `sortable` (with multiple). |
| `select2_from_ajax` | 1-n via AJAX: `name => 'category_id'`, `entity`, `attribute`, `data_source` (FetchOperation route or custom), `placeholder`, `minimum_input_length`, `delay`, `model`, `dependencies`, `method` (`POST` for Fetch), `include_all_form_fields`. Custom endpoints must honour `q`, pagination, and `keys` (for repeatable). |
| `select2_from_ajax_multiple` | n-n via AJAX: as above + `pivot => true`. |
| `select2_json_from_api` | select any JSON object from an API: `data_source`, `method`, `multiple`, `attribute` (label key), `attributes_to_store` (keys saved as JSON), `placeholder`, `minimum_input_length`, `delay`. Store only id: `attribute => 'id'`, `attributes_to_store => ['id']` + `saving`/`retrieved` events to (de)serialize. |
| `select_and_order` | drag&drop choose+order; `options` like select_from_array; cast array. |
| `repeatable` | groups of `subfields` (alias `fields`); stores JSON → **cast array/json**. `new_item_label`, `init_rows`, `min_rows`, `max_rows`, `reorder` (`false`/`true`/`'order'`/subfield def). Subfields must be complete definitions. No repeatable inside repeatable (relationship subfields count as repeatable). Relationship fields inside repeatable save as JSON, not relations. Validate `'testimonials.*.name' => 'required'`. `upload`/`upload_multiple` subfields → use `withFiles` (or `dropzone`). |
| `table` | rows of simple inputs saved as JSON array of objects; `columns => ['name' => 'Name', 'price' => 'Price']`, `entity_singular`, `min`, `max`. Cast array. Inside repeatable: decode before save (pattern below). |
| `date_picker` | Bootstrap datepicker; `date_picker_options => ['todayBtn' => 'linked', 'format' => 'dd-mm-yyyy', 'language' => 'fr']`. Cast date. |
| `datetime_picker` | `datetime_picker_options => ['format' => 'DD/MM/YYYY HH:mm', 'language' => 'pt', 'tooltips' => [...]]`, `allows_null`. Cast + Carbon::parse mutator. |
| `date_range` | `name => 'start_date,end_date'`, `default => ['2019-03-28 01:01', '2019-04-05 02:00']`, `date_range_options => ['drops' => 'down', 'timePicker' => true, 'locale' => ['format' => 'DD/MM/YYYY HH:mm']]`. |
| `image` | upload + crop: `crop => true`, `aspect_ratio => 1` (0 = free); `withFiles` (uses `SingleBase64Image` uploader; accepts jpeg/png/gif/webp/avif). Big images: check apcu/opcache. |
| `base64_image` | stores base64 in DB (LONGBLOB): `filename`, `aspect_ratio`, `crop`, `src`. |
| `dropzone` | AJAX multi-upload: add `DropzoneOperation` to the controller, cast array (TEXT/JSON column), `withFiles => true` (AjaxUploader), validate with `ValidDropzone`, `configuration => [...]` (dropzone options). Works as subfield. Temp dir config: see `uploads-validation.md`. |
| `browse` / `browse_multiple` | elFinder via `backpack/filemanager` (`composer require backpack/filemanager && php artisan backpack:filemanager:install`). `browse_multiple`: `multiple`, `sortable`, `mime_types`; cast array (unless `multiple => false`). |
| `ckeditor` | CKEditor 5: `options`, `extra_plugins`, `custom_build => [public_path('...ckeditor.js'), public_path('...init.js')]` + `attributes => ['data-init-function' => 'bpFieldInitCustomCkeditorElement']`, `elfinderOptions => true|[]` (pro 2.2.1+/filemanager 3.0.8+). |
| `wysiwyg` | CKEditor alias: `options`, `elfinderOptions`. |
| `tinymce` | `options => ['toolbar' => 'undo redo | styleselect | bold italic | ...', 'plugins' => '...']`. |
| `easymde` | Markdown editor: `easymdeAttributes => ['promptURLs' => true, 'status' => false, 'spellChecker' => false, 'forceSync' => true]`, `easymdeAttributesRaw`. Sanitize output. |
| `slug` | `target => 'title'` (live slugify), `locale`, `separator`, `trim`, `lower`, `strict`, `remove`. Stop auto-update on edit: `CRUD::field('slug')->target('')->attributes(['readonly' => 'readonly']);` |
| `phone` | intl-tel-input: `config => ['onlyCountries' => [...], 'initialCountry' => 'cl', 'separateDialCode' => true, 'nationalMode' => true, 'autoHideDialCode' => false, 'placeholderNumberType' => 'MOBILE']`. |
| `icon_picker` | `iconset => 'fontawesome'` (glyphicon, ionicon, weathericon, mapicon, octicon, typicon, elusiveicon, materialdesign); stores class name. |
| `address_google` | Google Places autocomplete; `store_as_json => true` (cast array, TEXT column). Key in `config/services.php`: `'google_places' => ['key' => env('GOOGLE_PLACES_KEY')]` (Maps JS + Places + Geocoding APIs; restrict key in prod). |
| `google_map` | map picker storing `{lat,lng,formatted_address}` JSON; `map_options => ['default_lat', 'default_lng', 'locate' => false, 'height' => 400]`. Split into columns via an `Attribute` accessor/mutator. |
| `video` | YouTube/Vimeo link → JSON `{id,title,image,url,provider}`; `youtube_api_key` (use your own); cast array. |

```php
// repeatable
CRUD::field([
    'name' => 'testimonials', 'type' => 'repeatable', 'new_item_label' => 'Add testimonial',
    'init_rows' => 1, 'min_rows' => 0, 'max_rows' => 10, 'reorder' => true,
    'subfields' => [
        ['name' => 'name',     'type' => 'text', 'wrapper' => ['class' => 'form-group col-md-4']],
        ['name' => 'position', 'type' => 'text', 'wrapper' => ['class' => 'form-group col-md-4']],
        ['name' => 'quote',    'type' => 'ckeditor'],
    ],
]);

// dropzone (controller also: use \Backpack\Pro\Http\Controllers\Operations\DropzoneOperation;)
CRUD::field(['name' => 'photos', 'type' => 'dropzone', 'withFiles' => true, 'configuration' => ['parallelUploads' => 2]]);

// date_range
CRUD::field(['name' => 'start_date,end_date', 'label' => 'Event period', 'type' => 'date_range',
             'date_range_options' => ['timePicker' => true, 'locale' => ['format' => 'DD/MM/YYYY HH:mm']]]);
```

`table` inside `repeatable` — avoid double-encoding:
```php
use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation { store as traitStore; }
use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; }
public function store()  { $this->decodeTableFields(); return $this->traitStore(); }
public function update() { $this->decodeTableFields(); return $this->traitUpdate(); }
private function decodeTableFields(): void
{
    $request = CRUD::getRequest();
    $rows = $request->get('repeatable_name');
    if (is_array($rows)) {
        $rows = array_map(function ($row) { $row['table_name'] = json_decode($row['table_name'] ?? '', true); return $row; }, $rows);
        $request->request->set('repeatable_name', $rows);
        CRUD::setRequest($request);
    }
}
```

---

## Overriding a stock field type
Copy into `resources/views/vendor/backpack/crud/fields/<type>.blade.php`: `php artisan backpack:field --from=number`. (For PRO fields the file comes from `vendor/backpack/pro/resources/views/fields`.) You stop receiving updates for that file — prefer a new custom type.

## Creating a custom field type
```bash
php artisan backpack:field address              # new blank type
php artisan backpack:field address --from=text  # start from an existing type
```
File: `resources/views/vendor/backpack/crud/fields/address.blade.php`. Use: `CRUD::field('address')->type('address');` (or `view_namespace` for package views).
Available variables: `$field` (all attributes incl. `value`), `$crud`, `$entry` (update only).
```blade
@include('crud::fields.inc.wrapper_start')
    <label>{!! $field['label'] !!}</label>
    @include('crud::fields.inc.translatable_icon')
    <input type="text" name="{{ $field['name'] }}"
           value="{{ old_empty_or_null($field['name'], '') ?? $field['value'] ?? $field['default'] ?? '' }}"
           data-init-function="bpFieldInitAddressElement"
           @include('crud::fields.inc.attributes')>
    @if (isset($field['hint'])) <p class="help-block">{!! $field['hint'] !!}</p> @endif
@include('crud::fields.inc.wrapper_end')

@push('crud_fields_styles')
    @basset('https://cdn.example.com/lib.css')
@endpush

@push('crud_fields_scripts')
    @basset('https://cdn.example.com/lib.js')
    @bassetBlock('backpack/crud/fields/address-field.js')
    <script>
        function bpFieldInitAddressElement(element) {
            // element = jQuery-wrapped input with data-init-function; find siblings relative to it, not by id
            element.on('CrudField:disable', () => element.prop('disabled', true));
            element.on('CrudField:enable',  () => element.prop('disabled', false));
        }
    </script>
    @endBassetBlock
@endpush
```
Conventions: put JS in a uniquely named `bpFieldInit*` function referenced by `data-init-function` (works in repeatable/modals); load assets with `@basset`/`@bassetBlock` (loaded once per page). If the custom type uploads files, register an uploader: `->withFiles(['uploader' => \Backpack\CRUD\app\Library\Uploaders\SingleFile::class])` or globally `app('UploadersRepository')->addUploaderClasses(['custom_upload' => SingleFile::class], 'withFiles');`.

## Filtering options of a select
1. Few options → `select_from_array`/`select2_from_array` with your own array.
2. Many options → `select2_from_ajax`/`relationship` + Fetch with a `query` closure.
3. Or `options` closure on select/select2/relationship; or a model subclass with a global scope.
