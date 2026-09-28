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
- Blade: `@basset('https://cdn.../lib.js')`, `@basset(public_path('css/x.css'))`, `@basset(base_path('vendor/pkg/x.js'))`, `@bassetBlock('unique/name.js') <script>..</script> @endBassetBlock`. Assets are downloaded/copied once and served from `storage/app/public/basset`.
- Commands: `basset:install`, `basset:check`, `basset:cache` (pre-cache everything), `basset:clear`, `basset:fresh`, `basset:internalize`.
- Env (`config/backpack/basset.php`): `BASSET_DEV_MODE` (defaults to true when `APP_ENV=local` → no caching while editing), `BASSET_DISK` (default `public`), `BASSET_VERIFY_SSL_CERTIFICATE`, `BASSET_CACHE_MAP`, `BASSET_RELATIVE_PATHS`. First page loads are slow until cached.
- Needs correct `APP_URL` and `php artisan storage:link` (default public disk).
- v6 no longer uses `public/packages` — custom assets there must be moved & loaded with `@basset`.

## Updating
```bash
composer update backpack/crud backpack/pro backpack/theme-tabler backpack/permissionmanager
php artisan basset:clear && php artisan basset:cache
php artisan config:clear && php artisan view:clear && php artisan cache:clear
```
If the table looks broken after an update → hard refresh (browser cache).

## PRO / paid add-on download errors
Composer pulls `dist` from `repo.backpackforlaravel.com` (never `source` — you don't have GitHub access). Error codes: 400 wrong credentials · 401 missing credentials · **402 version newer than your license covers** → require the last version you're entitled to (e.g. `composer require backpack/pro:"2.2.x"`; the email/Tokens page tells you) · 404 unknown package · 429 rate-limited. Set credentials with `composer config http-basic.backpackforlaravel.com <user> <pass>` (store in `auth.json`, not committed; in CI use `COMPOSER_AUTH`).

## Deployment checklist
```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan basset:clear && php artisan basset:cache
php artisan optimize:clear && php artisan optimize        # config/route/view cache
```
- `APP_URL` must be the real URL (Basset builds URLs from it); `APP_DEBUG=false`.
- Writable `storage/` & `bootstrap/cache`.
- Schedule `backpack:purge-temporary-files` if using dropzone; cron `* * * * * php artisan schedule:run`.
- Closures can't live in cached config (e.g. `strippedRequest` in config must be an invokable class).
- Registration is closed outside `local` by default (`BACKPACK_REGISTRATION_OPEN`).

## Uninstall (reverse of install)
Remove `app/Http/Middleware/CheckIfAdmin.php`, `config/backpack`, `resources/views/vendor/backpack`, `routes/backpack`, your Admin controllers/requests, then `composer remove` add-ons (pro, devtools, editable-columns...) and finally `backpack/crud`.
