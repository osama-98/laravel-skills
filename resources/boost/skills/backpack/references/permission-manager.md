# backpack/permissionmanager 7.x (spatie/laravel-permission UI)

Provides User, Role, Permission CRUDs at `admin/user`, `admin/role`, `admin/permission`. A user can have multiple roles plus extra direct permissions.

## Install / verify
```bash
composer require backpack/permissionmanager
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="permission-migrations"
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="permission-config"
php artisan vendor:publish --provider="Backpack\PermissionManager\PermissionManagerServiceProvider" --tag="config" --tag="migrations"
php artisan migrate
```
User model needs both traits:
```php
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable { use CrudTrait, HasRoles; /* ... */ }
```
Menu:
```blade
<x-backpack::menu-dropdown title="Authentication" icon="la la-users">
    <x-backpack::menu-dropdown-item title="Users" icon="la la-user" :link="backpack_url('user')" />
    <x-backpack::menu-dropdown-item title="Roles" icon="la la-id-badge" :link="backpack_url('role')" />
    <x-backpack::menu-dropdown-item title="Permissions" icon="la la-key" :link="backpack_url('permission')" />
</x-backpack::menu-dropdown>
```

## Config `config/backpack/permissionmanager.php`
```php
'models' => [
    'user'       => config('backpack.base.user_model_fqn', \App\Models\User::class),
    'permission' => Backpack\PermissionManager\app\Models\Permission::class,
    'role'       => Backpack\PermissionManager\app\Models\Role::class,
],
'allow_permission_create' => true, 'allow_permission_update' => true, 'allow_permission_delete' => true,
'allow_role_create' => true,       'allow_role_update' => true,       'allow_role_delete' => true,
'multiple_guards' => false,
```
Permissions/roles are referenced by name in code — once defined, disable create/update so admins can't rename them (or seed them and hide the panels).

## Guards — make `@can` / `can()` work in admin
spatie uses the default guard; Backpack uses guard `backpack`. Choose one:
- **A)** `config/backpack/base.php` → `'guard' => null` (use `web`; roles/permissions saved with guard `web`).
- **B)** add `\Backpack\CRUD\app\Http\Middleware\UseBackpackAuthGuardInsteadOfDefaultAuthGuard::class` to `base.middleware_class` (then `auth()` === `backpack_auth()` on admin routes; new roles saved with guard `backpack`).
- Or always check via `backpack_user()->can('x')` / `hasRole('x')`, which works regardless.
Make sure the `guard_name` stored in `roles`/`permissions` matches the guard in use (common "There is no permission named X for guard Y" error). Clear cache after changes: `php artisan permission:cache-reset`.

## Gate the whole panel by role
```php
// app/Http/Middleware/CheckIfAdmin.php
private function checkIfUserIsAdmin($user)
{
    return $user->hasAnyRole(['admin', 'super-admin']);   // or $user->can('access admin panel')
}
```

## Permission-driven CRUD access (recommended pattern)
```php
namespace App\Http\Controllers\Admin\Traits;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

trait CrudPermissionTrait
{
    public array $operations = ['list', 'show', 'create', 'update', 'delete'];

    public function setAccessUsingPermissions(): void
    {
        $this->crud->denyAccess($this->operations);
        $table = CRUD::getModel()->getTable();
        $user = backpack_user();
        if (! $user) return;

        foreach ([
            'see'  => ['list', 'show'],                               // permission "{table}.see"
            'edit' => ['list', 'show', 'create', 'update', 'delete'], // permission "{table}.edit"
        ] as $level => $ops) {
            if ($user->can("$table.$level")) {
                $this->crud->allowAccess($ops);
            }
        }
    }
}
// in any CrudController::setup(): $this->setAccessUsingPermissions();
```
Finer: map each operation to its own permission (`products.create`, `products.update` …) and `setAccessCondition()` for per-entry ownership. Seed permissions:
```php
collect(['users', 'roles', 'products'])->crossJoin(['see', 'edit'])
    ->each(fn ($p) => \Backpack\PermissionManager\app\Models\Permission::firstOrCreate(['name' => implode('.', $p)]));
```
Run in prod: `php artisan db:seed --class=PermissionSeeder --force`.

## Extending the package's UserCrudController
Option 1 — bind your controller (AppServiceProvider `register()`/`boot()`):
```php
$this->app->bind(
    \Backpack\PermissionManager\app\Http\Controllers\UserCrudController::class,
    \App\Http\Controllers\Admin\UserCrudController::class
);
```
```php
namespace App\Http\Controllers\Admin;

use Backpack\PermissionManager\app\Http\Controllers\UserCrudController as BaseUserCrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class UserCrudController extends BaseUserCrudController
{
    use \App\Http\Controllers\Admin\Traits\CrudPermissionTrait;

    public function setup()
    {
        parent::setup();
        $this->setAccessUsingPermissions();
    }

    public function setupListOperation()
    {
        parent::setupListOperation();
        CRUD::column('phone')->after('email');
    }

    protected function addUserFields()   // used by create & update in the package
    {
        parent::addUserFields();
        CRUD::field('phone')->after('email');
    }
}
```
Option 2 — own routes file `routes/backpack/permissionmanager.php` (overrides the package's) pointing `user` to `App\Http\Controllers\Admin` and `role`/`permission` to `\Backpack\PermissionManager\app\Http\Controllers`, both with `['web', backpack_middleware()]` and the admin prefix.

What the package's UserCrudController does (useful when extending): columns name/email/roles/permissions (select_multiple), PRO filters role (dropdown) + permissions (select2) when `backpack_pro()`, fields name/email/password/password_confirmation + `checklist_dependency` roles→permissions, `store()`/`update()` validate first, hash password (remove if empty), strip `password_confirmation`, `roles_show`, `permissions_show`, then `unsetValidation()` and call the trait methods. Requests: `UserStoreCrudRequest`, `UserUpdateCrudRequest`.

## Spatie API quick reference
```php
backpack_user()->givePermissionTo('edit articles'); ->revokePermissionTo(...); ->hasPermissionTo(...); ->can(...);
backpack_user()->assignRole('writer'); ->removeRole('writer'); ->hasRole('writer'); ->hasAnyRole([...]); ->hasAllRoles([...]); ->syncRoles([...]);
$role->givePermissionTo('edit articles'); $role->hasPermissionTo(...); $role->revokePermissionTo(...);
```
Blade: `@role('writer') … @endrole`, `@hasrole`, `@hasanyrole(...)`, `@hasallroles(...)`, `@can('edit articles') … @endcan` (guard caveat above).
