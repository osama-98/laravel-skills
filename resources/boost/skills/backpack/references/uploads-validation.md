# Uploads (Uploaders, MediaLibrary, Dropzone) & Validation

## Uploaders (`withFiles`)
Works on `upload`, `upload_multiple`, `image` (PRO), `dropzone` (PRO) fields — and on their columns to read URLs.
```php
CRUD::field('avatar')->type('upload')->withFiles();                       // disk 'public', path '/'
CRUD::field('avatar')->type('upload')->withFiles([
    'disk' => 'public',                       // any config/filesystems.php disk
    'path' => 'uploads/avatars',              // maps to field `prefix`
    'deleteWhenEntryIsDeleted' => true,       // needs field also in setupDeleteOperation()
    'temporaryUrl' => false, 'temporaryUrlExpirationTime' => 1, // s3 signed URLs (minutes)
    'uploader' => null,                       // custom class implementing UploaderInterface
    'fileNamer' => fn ($file, $uploader) => 'name.pdf', // or FileNameGeneratorInterface class
    'allowedExtensions' => ['pdf'],           // replaces config list for this field
]);
CRUD::field(['name' => 'photos', 'type' => 'upload_multiple', 'withFiles' => ['path' => 'photos']]); // array syntax
CRUD::column('avatar')->type('image')->withFiles(['disk' => 'public']);   // column reads URL via uploader
```
- Run `php artisan storage:link` for the `public` disk.
- If you set `disk`/`prefix` on the field, don't repeat them inside `withFiles()`.
- Default uploader classes (config `crud.uploaders.withFiles`): `upload` → `SingleFile`, `upload_multiple` → `MultipleFiles`, `image` → `SingleBase64Image`, `dropzone` → Pro `AjaxUploader`. Namespace `Backpack\CRUD\app\Library\Uploaders\...`.
- Custom field types: `withFiles(['uploader' => SingleFile::class])` or `app('UploadersRepository')->addUploaderClasses(['custom_upload' => SingleFile::class], 'withFiles');` in a provider's `boot()`.

### Delete files when an entry is deleted
```php
protected function setupDeleteOperation()
{
    CRUD::field('photo')->type('upload')->withFiles();
    // or $this->setupCreateOperation();
}
```
Soft-deleted models keep files. Or do it app-wide in the model: `static::deleted(fn ($m) => Storage::disk('public')->delete($m->photo));`.

### Allowed file types (crud ≥ 6.8.17)
Uploaders only store files whose **content-detected** extension is allow-listed (default: common images, docs, archives, audio, video, `bin` for unidentified). `svg`, `html`, `xml` are NOT allowed by default; server-executable extensions (`php`, `phtml`, `phar`, `sh`, `exe`, `htaccess`, ... in any part of the name) are always rejected.
```php
use Backpack\CRUD\app\Library\Uploaders\Support\FileExtensions;
// config/backpack/crud.php
'allowed_upload_extensions' => [...FileExtensions::DEFAULT_ALLOWED, 'dwg'],
// per field
CRUD::field('logo')->type('upload')->withFiles(['allowedExtensions' => [...FileExtensions::DEFAULT_ALLOWED, 'svg']]);
```
Rejections come back as a validation error on the field (subfield errors like `gallery.2.photos`); all files are checked before anything is stored/deleted. Keep proper validation rules anyway.

### Naming
Defaults: `upload`/`upload_multiple`/`dropzone` → slugged original name + 4 random chars + detected extension (`my-file-aY5x.pdf`); `image` → random hash + image extension (jpeg/png/gif/webp/avif only). Global namer: `file_name_generator` in `config/backpack/crud.php`. Custom uploaders must name files with `$this->getFileName($file)` before deleting old ones.

### Subfields & relationships
```php
'subfields' => [
    ['name' => 'avatar', 'type' => 'upload', 'withFiles' => true],
    ['name' => 'attachments', 'type' => 'upload_multiple', 'withFiles' => ['path' => 'attachments']],
],
```
- **Do NOT cast uploader attributes** in models used through relationships; create another accessor if you need a cast.
- BelongsToMany/MorphToMany pivot uploads: `->withPivot('picture')->using(ArticleCategory::class)` with a `Pivot` (or `MorphPivot`) model.

## Spatie MediaLibrary (`backpack/medialibrary-uploaders`)
Install `spatie/laravel-medialibrary` (^10 for this add-on v1), publish/run its migration, `storage:link`, add `InteractsWithMedia` + `implements HasMedia` to the model, then `composer require backpack/medialibrary-uploaders`.
```php
CRUD::field('avatar')->type('image')->withMedia();
CRUD::column('avatar')->type('image')->withMedia();
CRUD::field('gallery')->type('repeatable')->subfields([['name' => 'main_image', 'type' => 'image', 'withMedia' => true]]);
CRUD::field('main_image')->type('image')->withMedia([
    'collection' => 'product_images',       // may be defined in registerMediaCollections()
    'disk' => 'products', 'mediaName' => 'main_image',
    'fileNamer' => fn ($file, $uploader) => 'x.jpg',
    'allowedExtensions' => ['jpg', 'png'],
    'displayConversions' => ['thumb'],       // shown if ready, else original
    'whenSaving' => fn ($spatieMedia, $backpackMedia) => $spatieMedia->withResponsiveImages()->withCustomProperties(['k' => 'v']),
]);
```
Don't call `toMediaCollection()`, `setName()`, `usingName()`, `setOrder()`, `toMediaCollectionFromRemote()`, `toMediaLibrary()` inside `whenSaving`; custom property keys `name`, `repeatableContainerName`, `repeatableRow` are reserved.

## Dropzone (PRO)
1. Column TEXT/JSON + `$casts = ['photos' => 'array']`.
2. Controller: `use \Backpack\Pro\Http\Controllers\Operations\DropzoneOperation;`
3. Field: `CRUD::field(['name' => 'photos', 'type' => 'dropzone', 'withFiles' => true, 'configuration' => ['parallelUploads' => 2]]);` (array syntax so the upload endpoint sees your `withFiles` options).
4. Validate: `'photos' => ValidDropzone::field('required|min:2|max:5')->file('file|mimes:jpeg,png,jpg,gif|max:2048')` (`use Backpack\Pro\Uploads\Validation\ValidDropzone;`).
5. Temp files: publish `php artisan vendor:publish --provider="Backpack\Pro\AddonServiceProvider" --tag="dropzone-config"` → `config/backpack/operations/dropzone.php` (`temporary_disk` 'local', `temporary_folder` 'backpack/temp', `purge_temporary_files_older_than` 72h) or per CRUD in `setupDropzoneOperation()` via `CRUD::setOperationSetting(...)`. Clean: `php artisan backpack:purge-temporary-files --older-than=24 --disk=public --path="backpack/temp"` (schedule hourly).

## Legacy (v5-style) manual uploads
Without `withFiles`, handle files yourself with a model mutator (`setImageAttribute`) or model events — only if you need something the uploaders can't do.

---

## Validation

### Ways to validate (Create/Update/InlineCreate/form ops)
```php
CRUD::setValidation(ProductRequest::class);                                  // FormRequest
CRUD::setValidation(['name' => 'required|min:2'], ['name.required' => '…']); // rules array
CRUD::field('email')->validationRules('required|email')->validationMessages(['required' => '…']);
CRUD::setValidation();                                                       // field rules — call AFTER fields
```
FormRequest skeleton:
```php
class ProductRequest extends FormRequest
{
    public function authorize() { return backpack_auth()->check(); }
    public function rules()
    {
        $id = $this->get('id') ?? request()->route('id');
        return [
            'name'     => 'required|min:2|max:255|unique:products,name,'.$id,
            'price'    => 'required|numeric|min:0',
            'category' => 'required',                 // relationship field name
            'items.*.description' => 'required',      // subfields / repeatable
            'image'    => ValidUpload::field('required')->file('file|mimes:jpeg,png,jpg,gif,webp|max:2048'),
        ];
    }
    public function attributes() { return []; }
    public function messages()  { return []; }
}
```
Update-unique trick: the edit form posts a hidden `id`; also available as `request()->route('id')`.

### Backpack upload rules (handle create vs update "sometimes" for you)
```php
use Backpack\CRUD\app\Library\Validation\Rules\ValidUpload;
use Backpack\CRUD\app\Library\Validation\Rules\ValidUploadMultiple;
use Backpack\Pro\Uploads\Validation\ValidDropzone;

'avatar'      => ValidUpload::field('required')->file('mimes:jpg,png|max:2048'),
'attachments' => ValidUploadMultiple::field(['min:2', 'max:5'])->file('mimes:pdf|max:10000'),
'photos'      => ValidDropzone::field('min:2|max:5')->file('file|mimes:jpg,png,gif|max:10000'),
```
`::field()` = rules on the input as a whole; `->file()` = rules per file. If you allow `mimes:svg`, also allow `svg` in the uploader's `allowedExtensions`.

### Other validation notes
- Required-field asterisks come from the FormRequest (`CRUD::setRequiredFields(Request::class)` is called by `setValidation`).
- Different rules for create/update → two requests, set each in its setup method.
- Validation errors: `groupedErrors` (top) and `inlineErrors` (under fields) settings.
- Translate Laravel validation messages with `laravel-lang/lang` (Backpack doesn't ship them).
