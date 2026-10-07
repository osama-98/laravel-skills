# Relationships in Forms & Columns

Rule: define the Eloquent relation properly on the model(s) first. Then name the field after the **relation method** and (with PRO) use the `relationship` field — Backpack infers `entity`, `model`, `attribute`, `multiple`, `pivot`, `relation_type`.

```php
CRUD::field('category');   // name == relation method → type inferred as `relationship`
```

## Decision table
| Relation | Recommended UI | Code |
|---|---|---|
| **belongsTo** (n-1, FK on this table) | 0–10 opts: `relationship`/`select`; 0–500: `relationship`/`select2`; 500+: `relationship` + `ajax` + Fetch (or `select2_from_ajax`) | `CRUD::field('user');` · `CRUD::field('user_id')->type('select')->entity('user')->attribute('name')->model(User::class);` · `CRUD::field('user')->ajax(true)->minimum_input_length(0);` + `fetchUser()` |
| **hasOne** (1-1, FK on other table) | (A) subform · (B) one field per attribute (dot notation) | (A) `CRUD::field('phone')->type('relationship')->subfields(['prefix', 'number', ['name' => 'type', 'type' => 'select_from_array', 'options' => [...]]]);` (B) `CRUD::field('phone.number')->type('number'); CRUD::field('phone.prefix');` |
| **hasMany** (1-n) | (A) pick existing children: `relationship` (select2_multiple) · (B) create/edit/delete children inline: `relationship` + `subfields` | (A) `CRUD::field('comments');` `->fallback_id(3)` (reassign on unselect) or `->force_delete(true)` (delete on unselect); default: FK set to null (must be nullable) · (B) `CRUD::field('items')->subfields([...])->reorder('order');` |
| **belongsToMany** (n-n) | `relationship` (select2_multiple); pivot extras → `subfields` | `CRUD::field('roles');` · extras: `->subfields([['name' => 'notes', 'type' => 'textarea']])` (+ `withPivot('notes')` on BOTH relations) |
| **morphOne** (1-1 poly) | subform or dot notation | `CRUD::field('video')->type('relationship')->subfields(['url', ['name' => 'description', 'type' => 'ckeditor']]);` · `CRUD::field('video.url');` |
| **morphMany** (1-n poly) | subform (a select doesn't make sense) — or ajax select of existing | `CRUD::field('comments')->subfields([['name' => 'comment_text']]);` |
| **morphToMany** (n-n poly) | like belongsToMany | `CRUD::field('tags');` / `->subfields([['name' => 'note']])` |
| **morphTo** (n-1 poly) | two selects: type + id | `CRUD::field('commentable')->addMorphOption(Video::class)->addMorphOption(Post::class, 'Posts', ['data_source' => backpack_url('comment/fetch/post'), 'minimum_input_length' => 2, 'method' => 'POST', 'attribute' => 'title']);` |
| hasOneThrough, hasManyThrough, hasOneOfMany, morphOneOfMany | ❌ read-only — show in columns only | |
| morphedByMany | ❌ not supported | |

Free alternatives (no PRO): `select` (belongsTo), `select_multiple` / `checklist` (belongsToMany), `select_grouped`, `checklist_dependency`.

## Relationship field options (PRO)
```php
CRUD::field([
    'name'        => 'category',          // relation method
    'type'        => 'relationship',
    'label'       => 'Category',
    'attribute'   => 'title',             // shown attribute (else identifiableAttribute guess)
    'placeholder' => 'Select a category',
    'options'     => fn ($query) => $query->where('active', 1)->orderBy('title')->get(), // non-ajax: limits options AND is enforced at save
    // AJAX
    'ajax'        => true,                // needs FetchOperation + fetchCategory() on THIS controller
    'data_source' => backpack_url('article/fetch/category'),   // auto-guessed from entity; set for multi-word names (kebab)
    'minimum_input_length' => 2, 'delay' => 500, 'method' => 'POST',
    'dependencies' => ['country'],        // reset when these fields change
    'include_all_form_fields' => true,    // send the whole form in the AJAX request (use POST)
    // InlineCreate (secondary controller must use CreateOperation + InlineCreateOperation)
    'inline_create' => true,              // or ['entity' => 'category', 'force_select' => true, 'modal_class' => 'modal-dialog modal-xl', 'add_button_label' => 'New', 'include_main_form_fields' => ['x']]
]);
```

### Pivot extra columns (belongsToMany / morphToMany)
```php
// Company model:  return $this->belongsToMany(Person::class)->withPivot('job_title', 'job_description');
// Person model:   return $this->belongsToMany(Company::class)->withPivot('job_title', 'job_description');
CRUD::field('companies')->type('relationship')->subfields([
    ['name' => 'job_title',       'type' => 'text', 'wrapper' => ['class' => 'form-group col-md-3']],
    ['name' => 'job_description', 'type' => 'text', 'wrapper' => ['class' => 'form-group col-md-9']],
])->pivotSelect([                          // configure the auto-generated related-entry select
    'placeholder' => 'Pick a company',
    'wrapper' => ['class' => 'col-md-6'],
    'options' => fn ($model) => $model->where('type', 'primary'),
    'ajax' => true, 'data_source' => backpack_url('person/fetch/company'),
]);
```
Allow selecting the same related entry twice: pivot table needs its own key (e.g. `id`), add it to `withPivot(..., 'id')`, set `'allow_duplicate_pivots' => true` (and `'pivot_key_name' => 'uuid'` if not `id`). Don't add the key as a subfield.

Uploads in pivot subfields: create a Pivot model (`extends Pivot` / `MorphPivot` for morphToMany), `->withPivot('picture')->using(ArticleCategory::class)`, and do **not** cast uploader attributes.

### hasMany subform (manage children in the parent form)
```php
// invoices: id, number...; invoice_items: id, invoice_id, order, description, quantity, unit_price
CRUD::field('items')->subfields([
    ['name' => 'description', 'type' => 'text',   'wrapper' => ['class' => 'form-group col-md-8']],
    ['name' => 'quantity',    'type' => 'number', 'attributes' => ['step' => 'any'], 'wrapper' => ['class' => 'form-group col-md-2']],
    ['name' => 'unit_price',  'type' => 'number', 'attributes' => ['step' => 'any'], 'wrapper' => ['class' => 'form-group col-md-2']],
])->reorder('order');   // hidden 'order' subfield updated on move
```
Backpack creates/updates/deletes the child rows on save. Subforms are repeatable under the hood → no repeatable inside them. Child model attributes must be fillable.

### morphTo details
```php
CRUD::field('commentable')
    ->addMorphOption('App\Models\Video')                         // model FQN or morph-map alias
    ->addMorphOption('App\Models\Post', 'Posts', [...options])  // label + select options (data_source → ajax)
    ->morphTypeField(['wrapper' => ['class' => 'form-group col-md-4']])
    ->morphIdField(['wrapper' => ['class' => 'form-group col-md-8']]);
// array syntax: 'morphOptions' => [['App\Models\Owner', 'Owners'], ['monster', 'Monsters', ['placeholder' => '...']]]
```
JS: target the inputs as `crud.field('commentable[commentable_type]')`.

## Dependent selects (second select filtered by the first)
```php
CRUD::field(['name' => 'category', 'type' => 'select', 'entity' => 'category', 'attribute' => 'name']);
CRUD::field([
    'name' => 'articles', 'type' => 'select2_from_ajax_multiple', 'entity' => 'articles', 'attribute' => 'title',
    'data_source' => url('api/article'), 'method' => 'POST',
    'include_all_form_fields' => true, 'minimum_input_length' => 0, 'dependencies' => ['category'],
    'relation_options_query' => fn ($q) => $q->where('published', 1),   // save-time guard for custom endpoint
]);
```
Endpoint:
```php
public function index(Request $request)
{
    $search = $request->input('q');
    $form = backpack_form_input();           // parsed form inputs (original: request('form'))
    // $request->input('triggeredBy') → ['fieldName' => ..., 'rowNumber' => ...] inside repeatables
    if (empty($form['category'])) return [];
    $q = Article::where('category_id', $form['category']);
    if ($search) $q->where('title', 'LIKE', "%{$search}%");
    if ($request->has('keys')) return Article::findMany($request->input('keys')); // needed inside repeatable
    return $q->paginate(10);
}
```
With the FetchOperation you can read `backpack_form_input()` inside the `query` closure too.

## Save-time authorization guard (IDOR protection, crud 6.8+)
- Non-AJAX selects: the `options` closure defines allowed keys.
- AJAX + FetchOperation with conventional naming (entity `tag` → `fetchTag()`): the fetch `query` closure is reused automatically — only when the installed backpack/pro `FetchOperation` defines `getRelationFetchQuery()` (pro 2.2.36 doesn't). Otherwise set `relation_options_query` to the same query, or out-of-scope keys are saved.
- Manual `data_source` not matching the name → `'relation_options_query_source' => 'fetchProductCategory'`.
- Custom endpoint → `'relation_options_query' => fn ($query) => $query->where(...)`.
- Out-of-scope keys are silently dropped for HasMany/MorphMany/BelongsToMany/MorphToMany; BelongsTo aborts with a 422 validation error.

## Relationship columns (List/Show)
```php
CRUD::column('category');                                 // PRO relationship column (inferred)
CRUD::column('tags')->type('relationship')->attribute('name');
CRUD::column('category')->linkToShow();                   // link to related show page
CRUD::column('parent.title')->label('Parent');            // dot notation on 1-1 / belongsTo (text column)
$this->crud->query->withCount('tags'); CRUD::column('tags_count')->label('Tags')->suffix(' tags'); // fast count
```
Free: `select` (1-n), `select_multiple` (n-n), `relationship_count` (loads all related — slow on big tables).
Searching/ordering relation columns: see `columns.md` (`searchLogic`, `orderLogic`).

## Identifiable attribute
If `attribute` isn't set, Backpack guesses (name, title, ...). Force it on the related model:
```php
protected $identifiableAttribute = 'title';
// or public function identifiableAttribute() { return 'full_name'; }  // accessor → add to $appends
```
