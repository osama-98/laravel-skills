# CLI, Generators, Basset, Updating & Deployment

`php artisan backpack` lists everything. Generator commands come from `backpack/generators` (dev dependency, v4 for Backpack 6).

## Generate CRUDs & classes
| Command | Does |
|---|---|
| `backpack:crud {name}` | model (if missing) + controller + request + route + menu item. Singular: `backpack:crud product` |
| `backpack:build` | CRUDs for all models without one |
| `backpack:crud-controller {name}` / `backpack:crud-model {name}` / `backpack:crud-request {name}` | single pieces |
| `backpack:model {name} [--softdelete]`, `backpack:request {name}` | plain model/request |
| `backpack:crud-operation {Name}` | empty operation trait in `app/Http/Controllers/Admin/Operations` |
| `backpack:crud-form-operation {Name} [--no-id]` | operation with Backpack form (HasForm) |
| `backpack:button {name?} [--from=x]` | new or published button view |
| `backpack:column {name?} [--from=x]` | new or published column view |
| `backpack:field {name?} [--from=x]` | new or published field view |
| `backpack:filter {name?} [--from=x]` | new or published filter view |
| `backpack:widget {name?} [--from=x]` | new or published widget view |
| `backpack:page {name}` / `backpack:page-controller {name}` | custom admin page |
| `backpack:chart {name}` / `backpack:chart-controller {name}` | PRO chart controller (+ route) |
| `backpack:config {name}` | config file |
| `backpack:view {name} [--plain]` | blade view |
| `backpack:add-custom-route "Route::get(...)"` | append into `routes/backpack/custom.php` |
| `backpack:add-menu-content "<x-backpack::menu-item ... />"` | append into `menu_items.blade.php` |
| `backpack:publish {view}` | copy a package view to your app (e.g. `crud/list`, `ui/dashboard`) |

## Install / maintenance (in backpack/crud)
| Command | Does |
|---|---|
| `backpack:install [--timeout=600] [--debug] [--no-interaction]` | full install |
| `backpack:require:pro` / `backpack:require:devtools` / `backpack:require:editablecolumns` | install paid add-ons (asks for token) |
| `backpack:require:theme-tabler` / `theme-coreuiv4` / `theme-coreuiv2` | install & activate a theme |
| `backpack:publish-middleware` | publish `CheckIfAdmin` |
| `backpack:publish-header-metas` | favicons & mobile meta tags |
| `backpack:user` | create an admin user |
| `backpack:fix` | fix known issues (e.g. escape old error views) |
| `backpack:version` | PHP + Backpack package versions (ask users for this when debugging) |
| `backpack:upgrade` | guided upgrade command (targets v7 — don't run on this v6 project unless upgrading) |
| `backpack:purge-temporary-files [--older-than=24] [--disk=] [--path=]` | PRO dropzone temp cleanup |
| `backpack:filemanager:install` | after `composer require backpack/filemanager` |

## Basset (asset loader used by Backpack v6)
Backpack v6 uses `backpack/basset` 1.x (`backpack/crud` 6 requires `^1.1.1|^1.3.2`). Everything below is for 1.x — verified against basset 1.3.10 source and its bundled `vendor/backpack/basset/readme.md`. The online README on the `main` branch describes a newer Basset (a `basset` default disk, the map at `storage/app/basset/.basset`, `basset:cache --stale`, named assets, "`basset:cache` alone is sufficient"); don't apply it to a v6 project.
- Blade: `@basset('https://cdn.../lib.js')`, `@basset(public_path('css/x.css'))`, `@basset(base_path('vendor/pkg/x.js'))`, `@bassetBlock('unique/name.js') <script>..</script> @endBassetBlock`. Assets are downloaded/copied once and served from the Basset disk (`storage/app/public/basset` on the default `public` disk).
- Commands: `basset:install`, `basset:check`, `basset:cache` (pre-caches literal `@basset('…')`, `@bassetArchive(…)`, `@bassetDirectory(…)` found in the Blade files of `view_paths` — see below for what it skips), `basset:clear` (deletes the whole `basset/` folder on the disk, cache map included), `basset:fresh` (clear + cache; the 1.x readme recommends it after each deploy on a single server), `basset:internalize` (alias of `basset:cache`).
- Env (`config/backpack/basset.php`): `BASSET_DEV_MODE` (defaults to true when `APP_ENV=local` → no caching while editing), `BASSET_DISK` (default `public`), `BASSET_VERIFY_SSL_CERTIFICATE`, `BASSET_CACHE_MAP`, `BASSET_RELATIVE_PATHS`. First page loads are slow until cached.
- Any Laravel disk works for `BASSET_DISK` (the readme's remote example is `BASSET_DISK=s3` + `BASSET_CACHE_MAP=false` on Vapor). The cache map `.basset` is always read/written with the local `File` facade at `$disk->path('basset/.basset')`: on a remote disk either turn the map off (one remote `exists()` per asset per page) or give the disk a local `root` (Laravel's `path()` uses it; the object keys don't) and leave its `url` unset (with `url` set, `root` would end up in the links).
- Needs correct `APP_URL` and `php artisan storage:link` (default public disk).
- v6 no longer uses `public/packages` — custom assets there must be moved & loaded with `@basset`.

### What `basset:cache` does NOT pre-cache
- **`@bassetBlock` output.** The scanner regex (`BassetCache.php`) only matches `basset(`, `@bassetArchive(`, `@bassetDirectory(`. A block file is written the first time a page renders it, on that server's disk.
- **`@basset($variable)`.** Arguments are `eval`ed from the Blade source, so a variable fails silently. Backpack PRO's chart widget does this for Chart.js (`ChartController::getLibraryFilePath()`); name the file literally in one of your views (e.g. the dashboard) so it gets cached at deploy.
- **A block is looked up by name before its content is hashed** (`BassetManager::bassetBlock()`). Same name + different content (two views sharing a name, or Blade values like `{{ $org->color }}` / `auth()->id()` / `$field['name']` inside the block) → every request gets whatever was rendered first. Give each block a unique, app-prefixed name (`app/fields/x.js`, not `backpack/...`), and keep per-request values out of it (data attributes on the element, or a plain `<script>`/`<style>` next to the block). Translations are fine only when the locale is part of the name (Backpack's own `'backpack/crud/buttons/delete-button-'.app()->getLocale().'.js'`).
- **Small one-off inline CSS/JS on a single page** gains little from a block (an extra request instead of a few hundred bytes) — plain inline code is simpler. Blocks earn their place in field/column/button views that render several times per page (printed once) and in large static scripts.

### Several servers / pods
- With a per-server disk, block files (and variable `@basset`s) exist only on the server that first rendered them; a request that lands on another server gets 403/404 (Laravel's `storage/{path}` fallback answers as `text/html`, so the browser refuses the script). Use a disk every server shares (object storage, or a shared volume).
- On a shared disk run only `basset:cache` at deploy — `basset:clear` / `basset:fresh` delete files the other servers are still serving.
- **After a Backpack update, delete the package copies from the shared disk.** Files Basset copies from local paths (`@basset(base_path('vendor/backpack/…'))`, theme `styles` such as `public_path('vendor/backpack/theme-tabler/css/colors.css')`) keep a fixed path, and `BassetManager::basset()` only checks `exists()`, so `basset:cache` never replaces them. When `composer update` changes `backpack/crud` or `backpack/theme-tabler`, delete their copies (`basset/vendor/backpack/…`, `basset/public/vendor/backpack/…`) right before the deploy that ships the update; the new servers' `basset:cache` uploads the new files. Until the new servers are up, pages from old servers miss those files. CDN copies need nothing: their URLs carry the version. To see whether an update touched the assets at all: `diff -rq` the package's `src/resources/assets` (crud) or `resources/assets` (theme) between the old and new version.
- **Basset sets no `Cache-Control`.** If you add a long-lived one on the Basset disk (`max-age=31536000, immutable`), browsers that already cached a package file keep it after the update above — deleting the copy fixes the disk, not those browsers. Fine while the package versions are fixed (e.g. no license for newer PRO); otherwise use a shorter `max-age`.
- **On a remote disk, create the cache-map folder before `basset:cache`.** `CacheMap::save()` writes `.basset` with `File::put($disk->path($path.'.basset'))` and never creates the folder; on object storage no asset write creates it either, so the save fails with `file_put_contents(…): Failed to open stream` and every page falls back to one remote `exists()` per asset. Run `mkdir -p storage/app/public/basset` (the disk's `root` + the Basset `path`) before `basset:cache`. The readme's alternative for remote disks is `BASSET_CACHE_MAP=false` (its Vapor setup), which costs that remote `exists()` per asset on every page.

### Your own CSS/JS: Vite, not local `@basset` files
- **Local files are copied once, under a fixed path, and never re-read.** `@basset(base_path('…'))`, `@basset(public_path('…'))` and local paths in the `styles`/`scripts` config (`ui.php` or the theme config) are copied into the Basset disk; while that copy exists, `BassetManager::basset()` serves it (`IN_CACHE`) without comparing content. The `?…` Basset appends is a hash of the `composer.lock` *path*, not of any content, so it never changes either. On a per-server disk that is reset on deploy this goes unnoticed; on a **shared** disk (bucket, shared volume) edits never reach users.
- **A relative path is not copied at all** (`'js/admin/x.js'` in `scripts`): Basset prints it as-is, but first checks the disk for a copy on every render — one remote call per asset per page on a remote disk.
- CDN URLs are versioned and blocks are content-hashed, so both are safe.
- **For your own assets, use Vite:** add the file to `vite.config.js` `input` and to `vite_scripts` / `vite_styles` (see `ui-widgets-themes.md` for which config file wins). Vite gives content-hashed file names and the page's CSP nonce. Keep only package/theme files on Basset (they change only with a package update — after updating, delete their copies from a shared disk; see *Several servers / pods*).

## Updating
```bash
composer update backpack/crud backpack/pro backpack/theme-tabler backpack/permissionmanager
php artisan basset:cache        # basset:fresh (clear + cache) only on a single server with its own disk
php artisan config:clear && php artisan view:clear && php artisan cache:clear
```
If the table looks broken after an update → hard refresh (browser cache).

## PRO / paid add-on download errors
Composer pulls `dist` from `repo.backpackforlaravel.com` (never `source` — you don't have GitHub access). Error codes: 400 wrong credentials · 401 missing credentials · **401 or 402 for a version newer than your license covers** (with valid credentials, an older version still downloads) → cap the constraint below the first version you can't download, e.g. `"backpack/pro": "^2.2 <2.2.37"` (a space means AND). `2.2.x` still resolves to the newest 2.2 release, and an exact version (`"2.2.36"`) fails `composer validate --strict` · 404 unknown package · 429 rate-limited. Set credentials with `composer config http-basic.backpackforlaravel.com <user> <pass>` (store in `auth.json`, not committed; in CI use `COMPOSER_AUTH`).

## Deployment checklist
```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan basset:cache        # basset:fresh on a single server; never clear a disk shared by several servers
php artisan optimize:clear && php artisan optimize        # config/route/view cache
```
- `APP_URL` must be the real URL (Basset builds URLs from it); `APP_DEBUG=false`.
- Writable `storage/` & `bootstrap/cache`.
- Schedule `backpack:purge-temporary-files` if using dropzone; cron `* * * * * php artisan schedule:run`.
- Closures can't live in cached config (e.g. `strippedRequest` in config must be an invokable class).
- Registration is closed outside `local` by default (`BACKPACK_REGISTRATION_OPEN`).

## Uninstall (reverse of install)
Remove `app/Http/Middleware/CheckIfAdmin.php`, `config/backpack`, `resources/views/vendor/backpack`, `routes/backpack`, your Admin controllers/requests, then `composer remove` add-ons (pro, devtools, editable-columns...) and finally `backpack/crud`.
