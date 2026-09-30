# Admin UI: Widgets, Alerts, Breadcrumbs, Menu, Pages, Themes, Auth, Helpers

## Widgets
Global per-request container `Backpack\CRUD\app\Library\Widget` (aliased as `Widget`). Pages extending `backpack_view('blank')` (all CRUD pages do) render sections `before_content` (default) and `after_content` (Tabler also `before_breadcrumbs` / `after_breadcrumbs`); List details row renders the `details_row` section.
```php
use Backpack\CRUD\app\Library\Widget;

Widget::add($definitionArray)->to('after_content');   // aliases: section(), group()
Widget::add()->to('before_content')->type('card')->content(['header' => 'Title', 'body' => 'Text']);
Widget::make([...]);                                  // create without adding (for nesting in a div)
Widget::add([...])->name('stats');                    // reference later
Widget::name('stats')->content('...'); ->forget('attr'); ->makeFirst(); ->makeLast(); ->remove();
Widget::add([...])->from('package::widgets');         // or 'viewNamespace' => 'admin.widgets'
Widget::add()->type('script')->inline()->content('assets/js/x.js');  // also loaded inside InlineCreate modal
```
Common attributes: `type`, `content`, `class`, `wrapper => ['class' => 'col-sm-6 col-md-4', 'style' => '...']`.
Where you call it decides scope: in `setupListOperation()` → only List; in `setup()` → all pages of that CRUD; in a base CrudController → all CRUDs; `config/backpack/ui.php` `scripts`/`styles` → every admin page.

| Type | Definition |
|---|---|
| `alert` | `['type' => 'alert', 'class' => 'alert alert-danger mb-2', 'heading' => '...', 'content' => '...', 'close_button' => true]` |
| `card` | `['type' => 'card', 'class' => 'card bg-dark text-white', 'wrapper' => [...], 'content' => ['header' => '...', 'body' => '...']]` |
| `jumbotron` | `heading`, `content`, `button_link`, `button_text`, `heading_class`, `content_class` |
| `progress` | `class => 'card text-white bg-primary mb-2'`, `value`, `description`, `progress` (int %), `hint`, `footer_link`, `footer_text` |
| `progress_white` | same + `progressClass => 'progress-bar bg-primary'` (only this type reads `progressClass`) |
| `div` | `class => 'row'`, `content => [widget, widget, ...]`; other string attributes become div attributes |
| `view` | `view => 'path.to.view'`, extra attrs available as `$widget[...]` |
| `script` | `content => 'assets/js/x.js'` or CDN URL; `stack => 'before_scripts'` (default `after_scripts`); `integrity`, `crossorigin` |
| `style` | `content => 'assets/css/x.css'` or URL; `stack => 'before_styles'` (default `after_styles`) |
| `livewire` | `content => 'component-name'`, `parameters => [...]` (passed to `mount`), `livewireAssets => true` (Livewire v2 only if not loaded elsewhere) |
| `chart` (PRO) | `controller => \App\Http\Controllers\Admin\Charts\WeeklyUsersChartController::class`, optional `class`, `wrapper`, `content => ['header', 'body']` |

Row of widgets:
```php
Widget::add()->to('before_content')->type('div')->class('row')->content([
    Widget::make()->type('progress')->class('card border-0 text-white bg-primary')
        ->value($count)->description('Registered users.')->progress(100 * $count / 1000)->hint((1000 - $count).' more until next milestone.'),
    Widget::make(['type' => 'card', 'wrapper' => ['class' => 'col-sm-3'], 'content' => ['header' => 'Hi', 'body' => '...']]),
]);
```
### Chart widget (PRO)
```bash
composer require consoletvs/charts:"6.*"
php artisan backpack:chart WeeklyUsers   # → app/Http/Controllers/Admin/Charts/WeeklyUsersChartController + route
```
```php
use Backpack\CRUD\app\Http\Controllers\ChartController;
use ConsoleTVs\Charts\Classes\Chartjs\Chart;   // or Echarts, Highcharts, Fusioncharts, C3, Frappe

class WeeklyUsersChartController extends ChartController
{
    public function setup()                  // mandatory
    {
        $this->chart = new Chart();
        $this->chart->labels(['Mon', 'Tue', '...']);
        $this->chart->load(backpack_url('charts/weekly-users'));   // AJAX → data()
        $this->chart->minimalist(false); $this->chart->displayLegend(true);
    }
    public function data()                   // optional; if defined the chart loads via AJAX
    {
        $this->chart->dataset('Users', 'line', [/* values */])->color('rgb(77,189,116)')->backgroundColor('rgba(77,189,116,0.4)');
    }
}
```
Change the JS lib location with `protected $library = '...'` or `getLibraryFilePath()`.

### Custom widget type
`resources/views/vendor/backpack/ui/widgets/well.blade.php` (`php artisan backpack:widget well`):
```blade
@includeWhen(!empty($widget['wrapper']), backpack_view('widgets.inc.wrapper_start'))
  <div class="{{ $widget['class'] ?? 'well mb-2' }}">{!! $widget['content'] !!}</div>
@includeWhen(!empty($widget['wrapper']), backpack_view('widgets.inc.wrapper_end'))
@push('after_scripts') ... @endpush
```
Override stock widget: `php artisan backpack:widget --from=card`.

## Alerts (notification bubbles)
PHP (prologue/alerts) — same page or flashed:
```php
\Alert::add('success', '<strong>Saved</strong><br>Done.');   // info | warning | error | success | primary | secondary | light | dark
\Alert::success('Moderation saved.')->flash();               // survives redirect
\Alert::error('Nope')->flash();
return redirect()->back();
```
JS (Noty):
```js
new Noty({ type: 'success', text: 'Some notification text' }).show(); // success, info, warning/notice, error/danger, primary, secondary, dark, light
```
Confirmation modals use SweetAlert: `swal({ title, text, icon: 'warning', buttons: {...} }).then(value => {...})`.

## Breadcrumbs
Toggle globally: `config/backpack/ui.php` (or theme config) `'breadcrumbs' => true`. Provide `$breadcrumbs` (label => url, last => false) from the controller (`$this->data['breadcrumbs'] = [...]`) or in the view:
```blade
@php $breadcrumbs = [trans('backpack::crud.admin') => backpack_url('dashboard'), 'Reports' => false]; @endphp
```

## Menu (`resources/views/vendor/backpack/ui/inc/menu_items.blade.php`)
Theme-agnostic Blade components (extra attributes like `target`, `class` are forwarded):
```blade
<x-backpack::menu-item title="Dashboard" icon="la la-home" :link="backpack_url('dashboard')" />
<x-backpack::menu-separator title="Catalog" />
<x-backpack::menu-dropdown title="Authentication" icon="la la-users">
    <x-backpack::menu-dropdown-header title="Access" />
    <x-backpack::menu-dropdown-item title="Users" icon="la la-user" :link="backpack_url('user')" />
    <x-backpack::menu-dropdown-item title="Roles" icon="la la-id-badge" :link="backpack_url('role')" />
    <x-backpack::menu-dropdown-item title="Permissions" icon="la la-key" :link="backpack_url('permission')" />
</x-backpack::menu-dropdown>
{{-- nested dropdown: <x-backpack::menu-dropdown title="..." nested="true"> --}}
```
Tabler-only side-by-side columns in horizontal layouts (crud ≥ 6.6.4, tabler ≥ 1.2.1):
```blade
<x-backpack::menu-dropdown title="Clinic" icon="la la-clinic-medical" :withColumns="true">
    <x-theme-tabler::menu-dropdown-column>
        <x-backpack::menu-dropdown-item title="Appointments" icon="la la-calendar-check" :link="backpack_url('appointment')" />
    </x-theme-tabler::menu-dropdown-column>
    <x-theme-tabler::menu-dropdown-column> ... </x-theme-tabler::menu-dropdown-column>
</x-backpack::menu-dropdown>
```
Permission-aware items: wrap in `@if(backpack_user()->can('users.see')) ... @endif`. Generator: `php artisan backpack:add-menu-content "<x-backpack::menu-item ... />"`. Active state is set by JS from `href`. Components can be overridden in `resources/views/vendor/backpack/ui/components` (avoid). Don't create components in the `backpack` namespace — use your app's namespace.

## Custom pages & dashboard
Generator: `php artisan backpack:page Reports` (controller + view + route + menu item). Manual:
```php
// routes/backpack/custom.php (inside the group)
Route::get('reports', 'ReportController@index')->name('backpack.reports');
// app/Http/Controllers/Admin/ReportController.php
public function index() { return view('admin.reports', ['title' => 'Reports', 'breadcrumbs' => [...]]); }
```
```blade
{{-- resources/views/admin/reports.blade.php --}}
@extends(backpack_view('blank'))
@php
    Widget::add(['type' => 'card', 'content' => ['header' => 'Sales', 'body' => '...']])->to('before_content');
@endphp
@section('content')
  <div class="card"><div class="card-body">Your HTML (copy Tabler components)</div></div>
@endsection
@push('after_styles') ... @endpush
@push('after_scripts') ... @endpush
```
Layout stacks/sections: `before_styles`, `after_styles`, `before_scripts`, `after_scripts` (as sections or stacks), `header`, `content`, `before_breadcrumbs_widgets`, `after_breadcrumbs_widgets`, `before_content_widgets`, `after_content_widgets`.
Dashboard: publish `php artisan backpack:publish ui/dashboard` → `resources/views/vendor/backpack/ui/dashboard.blade.php` (or override per theme under `resources/views/vendor/backpack/theme-tabler/`); it's rendered by `AdminController::dashboard()` via `view(backpack_view('dashboard'), $this->data)`. Load DB data via view composers, full-namespace model calls, or AJAX for heavy queries.
You can use the admin look for front-end pages by extending `backpack_view('blank')`.

## Themes
- Active theme: `config/backpack/ui.php` → `view_namespace` & `view_namespace_fallback` (Tabler: `'backpack.theme-tabler::'`). `backpack_view('x')` checks: view_namespace → your `resources/views/vendor/backpack/<theme>/` → theme package → `resources/views/vendor/backpack/ui/` → crud `ui` views.
- Theme config overrides `ui.php`: `config/backpack/theme-tabler.php` (publish: `php artisan vendor:publish --tag="theme-tabler-config"`). Read with `backpack_theme_config('key')`.
- Install/switch: `php artisan backpack:require:theme-tabler | theme-coreuiv4 | theme-coreuiv2`. Uninstall: `composer remove`, delete its config, set another namespace.
- Theme strings: `lang/vendor/backpack.theme-tabler/{locale}/theme-tabler.php`.

### theme-tabler config (1.2.x)
```php
'layout' => 'horizontal_overlap',   // horizontal, horizontal_dark, horizontal_overlap, vertical, vertical_dark, vertical_transparent, right_vertical, right_vertical_dark, right_vertical_transparent (or your own view name in resources/views/layouts/)
'auth_layout' => 'default',         // default, illustration, cover
'styles' => [ base_path('vendor/backpack/theme-tabler/resources/assets/css/color-adjustments.css'),
              base_path('vendor/backpack/theme-tabler/resources/assets/css/colors.css') /* copy & replace to re-skin */ ],
'scripts' => [],
'options' => [
    'colorModes' => ['system' => 'la-desktop', 'light' => 'la-sun', 'dark' => 'la-moon'],
    'defaultColorMode' => 'system',    // system | light | dark
    'showColorModeSwitcher' => true,
    'useStickyHeader' => false, 'useFluidContainers' => false, 'sidebarFixed' => false,
    'doubleTopBarInHorizontalLayouts' => false, 'showPasswordVisibilityToggler' => false,
],
'classes' => ['body' => null, 'topHeader' => null, 'sidebar' => null, 'menuHorizontalContainer' => null,
              'menuHorizontalContent' => null, 'footer' => null, 'table' => null, 'tableWrapper' => null],
```
Override a single Tabler view: copy it to `resources/views/vendor/backpack/theme-tabler/<same path>`. Too many overrides → make a child theme.

### Child / custom theme
Create `resources/views/my-theme/`, set `'view_namespace' => 'my-theme.'` (trailing dot) and `'view_namespace_fallback' => 'backpack.theme-coreuiv4::'`. Override `inc/theme_styles.blade.php`, `inc/theme_scripts.blade.php` (load Bootstrap yourself via `@basset`), `layouts/*.blade.php`, `inc/sidebar`, `inc/main_header`, `components/*` (menu components). Package with `theme-skeleton`.

### Global CSS/JS & look
- Every admin page loads `styles` / `scripts` (each entry through `@basset`), `mix_styles` / `mix_scripts` (`mix()`) and `vite_styles` / `vite_scripts` (`@vite`), in that order (`crud::ui.inc.styles` / `crud::ui.inc.scripts`).
- **Which file wins:** they are read with `backpack_theme_config($key)`, which returns the theme config's key first (e.g. `config/backpack/theme-tabler.php`), then the fallback theme's, then `ui.php`'s. A key defined in the theme file completely hides the same key in `ui.php`: theme-tabler's own config defines `styles`, so `ui.php`'s `styles` is ignored by default, and any other key you add to the theme config (e.g. `vite_scripts`) does the same — keep each key in one file, normally the theme config.
- Own CSS/JS: prefer `vite_*` over local paths in `styles`/`scripts` (see `cli-deploy.md` → "Your own CSS/JS"). The online v6 docs only describe the old `scripts` array; the `vite_*` keys are verified in the v6 source.
- Tests: `withoutVite()` makes `@vite` print nothing, so asserting "page loads my script" needs a stand-in `Vite` bound in the container whose `__invoke()` prints one tag per entry point.
- CSS hooks: elements carry `bp-section="page-header"` and `bp-section="crud-operation-{list|create|update|show|reorder}"`; buttons carry `bp-button="name"`.
- Project branding: `ui.php` `project_name`, `project_logo`, `home_link`, `developer_name`, `developer_link`, `show_powered_by`, `meta_robots_content`, `html_direction` (`rtl` for Arabic), `default_date_format` (ISO tokens).
- Favicons & mobile metas: `php artisan backpack:publish-header-metas`.

## Authentication & admins
- Separate guard/provider/broker named `backpack` (created at runtime). To customize define `backpack` guard/provider/password broker in `config/auth.php`. `base.guard => null` makes Backpack use the default web guard.
- Users table shared with front-end users → gate admins in `app/Http/Middleware/CheckIfAdmin::checkIfUserIsAdmin()` (e.g. `return $user->is_admin;` or `$user->hasRole('admin')`) — or add a permission middleware to `base.middleware_class`.
- Username login: add `username` column, set `authentication_column => 'username'`, `authentication_column_name => 'Username'`.
- Own routes/controllers: `setup_auth_routes => false` (+ `setup_dashboard_routes`, `setup_my_account_routes`, `setup_password_recovery_routes`), or create `routes/backpack/base.php` (replaces all base routes).
- Register form fields: route `admin/register` → your controller extending `Backpack\CRUD\app\Http\Controllers\Auth\RegisterController` (override `validator()`, `create()`, `showRegistrationForm()`), view `resources/views/vendor/backpack/theme-tabler/auth/register.blade.php`. Registration open only on local by default (`registration_open`).
- My Account (name/email/password): `Backpack\CRUD\app\Http\Controllers\Auth\MyAccountController`; extra inputs → override `resources/views/vendor/backpack/theme-tabler/my_account.blade.php` + fillable.
- Email verification (crud ≥ 6.2): user `implements MustVerifyEmail`, `email_verified_at` column, `verified`/`signed` middleware aliases, `setup_email_verification_routes => true`.
- Avatar: `avatar_type` = `gravatar` | `null` (initials) | a User method name returning a URL; `gravatar_fallback`.
  - **`gravatar` goes through Basset** (`backpack_avatar_url()`): it copies the image into the Basset disk (one file per user on a shared disk), and in **Basset dev mode** the page's second avatar (sidebar, then user menu) gets `LOADED` and a Basset path that was never written → **403**. To load it straight from Gravatar, set `avatar_type` to a User method, e.g. `avatarUrl()` returning `Gravatar::fallback(config('backpack.base.gravatar_fallback'))->get($this->email, ['size' => 80])` (`Creativeorange\Gravatar\Facades\Gravatar`, the package Backpack already uses), and allow `https://www.gravatar.com` in the CSP `img-src`.
- Create admin from CLI: `php artisan backpack:user`.

## Helpers (usable anywhere except config files)
`backpack_url($path)`, `backpack_auth()`, `backpack_user()`, `backpack_guard_name()`, `backpack_middleware()`, `backpack_authentication_column()`, `backpack_email_column()`, `backpack_users_have_email()`, `backpack_avatar_url($user)`, `backpack_view('blank')`, `backpack_theme_config('key')`, `backpack_pro()` (bool), `backpack_form_input()` (parsed form in AJAX), `old_empty_or_null()`, `square_brackets_to_dots()`, `is_multidimensional_array()`, `mb_ucfirst()`.

## Error pages
Backpack v5 used to publish `resources/views/errors/*` (400,401,403,404,405,408,429,500,503); in v6 delete them if they are Backpack's. Run `php artisan backpack:fix` to patch old unescaped error views.

## Translations
Uses `config('app.locale')`; 20+ languages incl. Arabic (RTL via `html_direction`). Override single strings in `lang/vendor/backpack/{locale}/crud.php|base.php` (only the keys you change). Publish all: `php artisan vendor:publish --provider="Backpack\CRUD\BackpackServiceProvider" --tag="lang"` (then delete untouched files).
