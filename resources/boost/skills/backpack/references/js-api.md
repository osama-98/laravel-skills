# CrudField JavaScript Library (Create / Update forms)

Global `crud` object on form pages (also in InlineCreate modals and custom form operations).

## API
- Selectors: `crud.field('title')`, `crud.fields(['a', 'b'])` (array), `crud.field('testimonials').subfield('text')`, `crud.field('wish').subfield('body', rowNumber)`.
- Properties: `.name`, `.type`, `.input` (DOM element holding the value), `.value` (string!), `.rowNumber` (in subfield callbacks).
- Events: `.onChange(fn(field) {...})` (fires on every change/keystroke).
- Methods (chainable; each accepts an optional boolean): `.hide()`, `.show()`, `.disable()`, `.enable()`, `.require()` / `.unrequire()` (asterisk only — not validation), `.change()` (trigger now → run on page load), `.check()` / `.uncheck()` (checkboxes).
- `crud.action` → `"create"` or `"edit"`.
- Morph inputs aren't subfields: `crud.field('commentable[commentable_type]')`.

## Loading your script
```php
// in setupCreateOperation() / setupUpdateOperation()
Widget::add()->type('script')->content('assets/js/admin/forms/product.js');          // relative to public/
// Widget::add()->type('script')->content(asset('assets/js/admin/forms/product.js'));
// in the secondary entity for InlineCreate modals: ->inline()
```
Convention: one file per entity in `public/assets/js/admin/forms/{entity}.js`.

## Recipes
```js
// show a field when a checkbox is checked (and evaluate on page load)
crud.field('agree_to_terms').onChange(field => {
  crud.field('agree_to_marketing_email').show(field.value == 1);
}).change();

// show + enable together
crud.field('visible').onChange(f => crud.field('displayed_where').show(f.value == 1).enable(f.value == 1)).change();

// radio/select specific value
crud.field('type').onChange(f => crud.field('custom_type').show(f.value == 3)).change();

// auto-fill another input (slug) — or use the PRO `slug` field
crud.field('title').onChange(f => { crud.field('slug').input.value = slugify(f.value); });

// computed total (values are strings!)
function calc() {
  const full = Number(crud.field('full_price').value), disc = Number(crud.field('discounted_price').value);
  crud.field('discount_percentage').input.value = (full - disc) * 100 / full;
}
crud.fields(['full_price', 'discounted_price']).forEach(f => f.onChange(calc));

// repeatable: enable a subfield in the same row
crud.field('wish').subfield('country').onChange(f => {
  crud.field('wish').subfield('body', f.rowNumber).enable(f.value == '');
});

// hide a repeatable and disable all of its subfields
crud.field('visible').onChange(field => {
  const names = $(crud.field('wish').input).parent().find('[data-repeatable-holder]').data('subfield-names');
  names.forEach(n => crud.field('wish').subfield(n).enable(field.value == 1));
  crud.field('wish').show(field.value == 1);
}).change();

// only on edit
if (crud.action === 'edit') { crud.field('email').disable(); }
```

## Custom field types & the JS API
- Put a `data-init-function="bpFieldInitMyField"` attribute on your input (or wrapper) and define `function bpFieldInitMyField(element) {}` inside `@push('crud_fields_scripts')` (wrap with `@bassetBlock('unique/path.js') ... @endBassetBlock` so it loads once). `element` is the jQuery-wrapped node; locate related nodes relative to it (repeatable-safe).
- To support `crud.field(...).disable()/enable()/...` in custom fields, listen to `CrudField:disable`, `CrudField:enable`, `CrudField:delete` events on the element (see CKEditor custom build example in `fields.md`) and trigger `element.trigger('change')` when your widget changes the value.

## List page JS
- `crud.table` = DataTables instance → `crud.table.ajax.reload()`.
- `crud.checkedItems` = selected IDs for bulk actions.
- `crud.addFunctionToDataTablesDrawEventQueue('fnName')` → run a function after every draw (for JS inside row buttons/columns).
- Push list scripts with `@push('crud_list_scripts')` (filters) or `@push('after_scripts')`.
