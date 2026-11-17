---
name: clean-code
description: "A layered architecture for Laravel API endpoints and the business logic behind them: naming conventions, thin RESTful controllers, FormRequest validation (only when there are rules), Policy authorization, focused Services that throw self-rendering exceptions for expected failures, readonly DTOs, strict models and scopes, events for side effects only, reliable jobs, and Resources returned through ApiResponse. Load before writing or changing any controller, request, service, exception, middleware, model, DTO, policy, resource, route, job, event, or listener, or before naming a new class, interface, enum, method, or variable."
when_to_use: "Adding or changing an endpoint or route; naming a new class, interface, enum, trait, method, or variable; deciding where validation, authorization, queries, or business logic belong; deciding how a Service signals a failure (exception, abort) or how a middleware hands a computed value to the controller; moving logic out of a controller; creating or splitting a Service; adding a DTO; deciding whether to add an interface or a second implementation (such as a payment gateway); writing a job or an outside-system client; reviewing an endpoint's request-to-response flow. Files: app/**, bootstrap/app.php, routes/api.php, tests/**."
license: MIT
metadata:
  author: osama-98
---

# Clean Code: Request / Controller / Service Architecture

This architecture strictly separates the HTTP layer from business logic. Every versioned API endpoint follows the same flow: Route, then Form Request for validation and authorization, then Controller for orchestration, then Service for business logic, then API Resource shaped data returned through `ApiResponse`.

Controllers never contain business logic. Services never touch `Illuminate\Http\Request`, validation, or response formatting. This separation keeps controllers thin adapters and lets the same Service run from a controller, a queued job, a console command, or a test.

All business logic lives in Services. It does not use Action classes or repositories, so there is one place to look for logic and no choice to make between patterns.

It targets Laravel 12+ (the `#[Scope]` and `#[Bind]` attributes) and adds one convention of its own: every response goes through an `ApiResponse` envelope (`references/api-response.md`). Where an app already has a different, consistent convention, follow the app.

Rules come in three strengths, so you know which ones can bend:

- **Hard rule**: breaking it causes a bug or a security hole. Never break it.
- **Default**: what to write unless you have a reason not to. Each one names when an alternative is fine.
- **Convention**: this skill's style choice. It may differ from the Laravel default on purpose; keep it so the codebase reads as one.

An unlabeled rule is a default.

Every example in this skill uses one blog domain, the way the Laravel documentation does: a `Post` with `Comment`s, written by a `User`, published or drafted through `PostStatusEnum`, paid for through a `PaymentGateway` interface. Use the same names when you extend this skill, so the examples read as one codebase. Do not use `Foo`/`Bar` or `Thing`: they show syntax but hide what each piece is for.

## 1. Naming

Every class carries a suffix naming its type, except models, `App\Validation` classes, and interfaces with their implementations, which follow Laravel's contracts (`Illuminate\Contracts\Queue\Queue`). Names are singular, except collections, tables, and route URIs.

### Classes, interfaces, enums, and traits

| Kind | Location | Convention | ✅ Example | ❌ Not this |
| --- | --- | --- | --- | --- |
| Model | `app/Models/` | Singular noun, no suffix | `Post`, `Comment` | `Posts`, `PostModel` |
| Controller | `app/Http/Controllers/V1/{Domain}/` | `{Model}Controller` | `PostController` | `PostsController` |
| Non-CRUD controller | same | `{Model}{Noun}Controller` | `PostPublicationController` | `PostController@publish` |
| Invokable controller | same | `{Verb}{Model}Controller` | `ArchivePostController` | `PostArchiver` |
| Form Request | `app/Http/Requests/V1/{Domain}/` | `{Action}{Model}Request` | `StorePostRequest`, `UpdatePostRequest` | `PostRequest`, `CreatePostRequest` |
| Filters Request | same | `{Model}FiltersRequest` | `PostFiltersRequest` | `IndexPostRequest` |
| Service (CRUD) | `app/Services/V1/{Domain}/` | `{Model}Service` | `PostService` | `PostManager`, `PostHelper` |
| Service (focused) | same | `{Model}{Purpose}Service` | `PostPublishingService` | `PostService2` |
| Outside-system client | same | `{System}Client` | `StripeClient` | `StripeHelper` |
| DTO (input) | `app/Dto/{Domain}/` | `{Action}{Model}Dto` | `StorePostDto` | `StorePostData`, `StorePostDTO` |
| DTO (result) | same | `{PastParticiple}{Model}Dto` | `PublishedPostDto` | `PostResult`, `PublishedPostData` |
| Policy | `app/Policies/` | `{Model}Policy` | `PostPolicy` | `PostPermissions` |
| Resource | `app/Http/Resources/V1/{Domain}/` | `{Model}Resource` | `PostResource` | `PostTransformer` |
| Simple Resource | same | `{Model}SimpleResource` | `UserSimpleResource` | `UserMiniResource` |
| Enum | `app/Enums/{Domain}/` | `{Model}{Attribute}Enum` | `PostStatusEnum` | `PostStatus`, `PostStatuses` |
| Rule | `app/Rules/` | `{Condition}Rule` | `PublishableSlugRule` | `SlugValidator` |
| `after()` validation class | `app/Validation/` | The invariant, no suffix | `UniqueTagsPerPost` | `UniqueTagsRule` |
| Interface | next to its implementations | The capability, no suffix | `PaymentGateway` | `PaymentGatewayInterface`, `IPaymentGateway` |
| Implementation | same folder as its interface | `{Variant}{Interface}` | `StripePaymentGateway` | `PaymentGatewayImpl`, `Stripe` |
| Trait | `app/Support/Traits/` (a subfolder per purpose, such as `Exceptions/`) | Adjective or `Has…` | `HasSlug`, `Publishable`, `Exceptions\HasRender` | `SlugTrait` |
| Job | `app/Jobs/` | `{Verb}{Model}Job` | `PublishPostJob` | `PostJob`, `PublishPost` |
| Event | `app/Events/` | `{Model}{PastTense}Event` | `PostPublishedEvent` | `PublishPostEvent` |
| Listener | `app/Listeners/` | `{Verb}{What}Listener` | `SendPostPublishedMailListener` | `PostListener` |
| Mail | `app/Mail/` | `{Model}{Noun}Mail` | `PostPublishedMail` | `PostEmail` |
| Console command | `app/Console/Commands/` | `{Verb}{What}Command` | `PublishScheduledPostsCommand` | `PostCron` |
| Middleware | `app/Http/Middleware/` | `{Purpose}Middleware` | `VerifyWebhookSignatureMiddleware` | `WebhookCheck` |
| Exception | `app/Exceptions/{Domain}/` | `{Problem}Exception` | `PostAlreadyPublishedException` | `PostError` |
| Feature test | `tests/Feature/{Domain}/` | `{Action}{Model}Test` | `StorePostTest` | `PostControllerTest` |

### Enums

```php
// ✅
enum PostStatusEnum: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case IN_REVIEW = 'IN_REVIEW';
}

// ❌
enum PostStatus: string
{
    case Draft = 'DRAFT';
    case InReview = 'IN_REVIEW';
}
```

### Interfaces and implementations

```php
// ✅
#[Bind(StripePaymentGateway::class)]
interface PaymentGateway
{
    public function charge(Post $post, int $amount): PaymentResultDto;
}

final class StripePaymentGateway implements PaymentGateway {}
final class PaypalPaymentGateway implements PaymentGateway {}

// ❌
interface PaymentGatewayInterface {}
class PaymentGatewayImpl implements PaymentGatewayInterface {}
```

### Methods, variables, and constants

| Kind | Convention | ✅ Example | ❌ Not this |
| --- | --- | --- | --- |
| Method | camelCase verb | `publish()`, `findBySlug()` | `doPublish()`, `post_publish()` |
| Boolean method / property | `is`/`has`/`can` prefix | `isPublished()`, `hasComments()` | `published()`, `checkComments()` |
| Scope | camelCase, no `scope` prefix | `published()`, `ofType()` | `scopePublished()` |
| Relation (one) | singular camelCase | `author()` | `getAuthor()` |
| Relation (many) | plural camelCase | `comments()` | `commentList()` |
| Variable (model) | camelCase, model name | `$post` | `$p`, `$data`, `$obj` |
| Variable (collection) | plural | `$posts` | `$postList`, `$postsArray` |
| Variable (id) | `{model}Id` | `$postId` | `$id` when two ids are in scope, `$post_id` |
| Variable (boolean) | `is`/`has`/`should` prefix | `$isPublished`, `$shouldNotify` | `$flag`, `$published` |
| Constant | UPPER_SNAKE_CASE, typed | `public const string SIGNATURE_HEADER = 'X-Signature';` | `const signatureHeader = ...` |
| Enum case | UPPER_SNAKE_CASE | `PostStatusEnum::IN_REVIEW` | `PostStatusEnum::InReview` |
| Database column / JSON key | One case across the app: snake_case (Laravel's default) or camelCase, matching existing columns | `published_at` or `publishedAt` | Mixing both, `AuthorId` |
| Route URI | kebab-case, plural | `/v1/posts/{post}/scheduled-publications` | `/v1/Post/{id}/scheduledPublication` |
| Route name | `{resource}.{action}` | `posts.index` | `getPosts` |
| Config key | snake_case | `config('services.stripe.secret_key')` | `config('services.stripe.secretKey')` |
| Env variable | UPPER_SNAKE_CASE | `STRIPE_SECRET_KEY` | `stripeSecretKey` |

### Conventions that override the Laravel default

These are deliberate. Laravel's docs and community guides (Spatie, alexeymezenin/laravel-best-practices) do it differently; this skill keeps its own choice so the codebase reads as one. Do not "fix" them back.

| Convention | This skill | Laravel / community default |
| --- | --- | --- |
| Enum names and cases | `PostStatusEnum`, `case IN_REVIEW` | `PostStatus`, `case InReview` |
| Event names | `PostPublishedEvent` | `PostPublished` |
| DTOs | `final readonly class` | not `final` by default |
| Authenticated user in a controller | `auth()->user()` inline | `$request->user()` |

## 2. Routes

API routes live in `routes/api.php`, versioned under `Route::prefix('v1')`, and grouped by their auth middleware. Register resources with `Route::apiResource('posts', PostController::class)`, using `->except([...])`, `->only([...])`, and `->names(...)` to customize, rather than declaring every verb manually. Bulk-destroy endpoints accept a route or query param of ids rather than a separate bulk-delete route. Group related nested routes with `Route::prefix('posts')->group(function () { ... })`.

**Hard rule:** a route that binds a child model under its parent (`/posts/{post}/comments/{comment}`) calls `->scopeBindings()`, so `{comment}` resolves only through `$post->comments()`. Without it, a client can read or change a comment of a different post by changing the id.

```php
Route::apiResource('posts.comments', PostCommentController::class)->scopeBindings();
```

The v1 group carries `throttle:api`, and login, OTP, and password-reset routes get their own stricter limiter. Define the limiters in `AppServiceProvider::boot()` (`references/production-practices.md`).

## 3. Controllers

Controllers live at `app/Http/Controllers/V1/{Domain}/{Name}Controller.php`, extending the abstract `App\Http\Controllers\Controller`. The Service is injected via constructor property promotion: `public function __construct(protected PostService $service) {}`.

A controller's single service property is always named `$service`, whatever the class.

```php
// ❌
public function __construct(protected PostService $posts) {}

// ✅
public function __construct(protected PostService $service) {}
```

With more than one service, name each after its class, minus the domain prefix the classes share, ending in `Service`:

```php
// ❌
public function __construct(
    protected PostService $posts,
    protected PostPublishingService $publishing,
) {}

// ✅
public function __construct(
    protected PostService $postService,
    protected PostPublishingService $publishingService,
) {}
```

Pass the validated input to a service as one array, never field by field. The service reads the keys it needs and declares their shape in an `@param array{…}` tag.

```php
// ❌
$post = $this->service->store(
    $request->validated('title'),
    $request->validated('body'),
    $request->validated('tags'),
);

// ✅
$post = $this->service->store($request->validated());
```

A controller only uses the standard resource methods: `index`, `show`, `store`, `update`, `destroy` (plus `create` and `edit` for web pages). An operation that is not one of these gets its own controller, modeled as a resource of its own rather than a custom method:

| Operation | Not this | This |
| --- | --- | --- |
| Publish / unpublish a post | `PostController@publish` | `PostPublicationController@store` / `@destroy` |
| Change a post's status | `PostController@updateStatus` | `PostStatusController@update` |
| A one-off command | `PostController@archive` | Invokable `ArchivePostController` (`make:controller --invokable`) |

A controller action reads as: pass `$request->validated()` to one Service method, then return the result as a Resource inside `ApiResponse`.

```php
public function store(StorePostRequest $request): JsonResponse
{
    $post = $this->service->store($request->validated());

    return ApiResponse::created(PostResource::make($post));
}
```

A controller may only contain its action methods plus the constructor. Never add private/protected helper methods to a controller, not even a one-liner that wraps `auth()->user()`. Anything that is not an action belongs in a Service, a Form Request, or a Policy. Call `auth()->user()` inline where you need the authenticated user, and pass it into the Service method as a plain argument. Bulk destroy actions accept `array|string|int $ids` straight from the route or request and hand them to the Service.

A controller never catches the exceptions a Service throws and never `match`es on a result to pick an error response. An expected failure renders itself (section 7), so the action stays the happy path only.

## 4. Form Requests

Form Requests live at `app/Http/Requests/V1/{Domain}/{Action}Request.php`, for example `StorePostRequest`, `UpdatePostRequest`, `PostFiltersRequest`, and extend `Illuminate\Foundation\Http\FormRequest`. Create them with `php artisan make:request V1/{Domain}/{Action}Request --no-interaction`.

Create a Form Request only when the endpoint has input to validate: at least one rule in `rules()`, or an `after()` check. A filter or query-param endpoint with rules gets one (`PostFiltersRequest`); an endpoint with nothing to validate does not. There, the action type-hints `Illuminate\Http\Request` only when it reads something from it, and otherwise takes no request parameter at all. A class whose `rules()` returns `[]` is noise: it hides that the endpoint takes no input. Authorization alone is not a reason to create one; see section 5.

```php
// ❌
final class ShowPostRequest extends FormRequest
{
    public function rules(): array
    {
        return [];
    }
}

public function show(ShowPostRequest $request, Post $post): JsonResponse

// ✅
public function show(Post $post): JsonResponse
```

Never validate inline with `$request->validate([...])` in a controller. Use array-based validation rules, one rule per array item, matching sibling requests.

**Hard rules** for what `validated()` hands to the Service:

- Every `array` rule lists the keys it accepts (`'meta' => ['array:title,description']`). Without the list, `validated()` returns every key the client nested under that field, validated or not, and they reach `create()`.
- `Rule::unique(...)->ignore()` takes the bound route model (`$this->route('post')`), never a value from the input. Laravel's docs flag user input there as an SQL injection risk.
- An optional field is `nullable`. Laravel's `ConvertEmptyStringsToNull` middleware turns `""` into `null`, which fails a rule that is not `nullable`. Use `Rule::enum(SomeEnum::class)` for enum columns and `Rule::exists()` / `Rule::unique()` with `->where()` constraints for relational or scoped checks.

A fixed set of allowed values is a backed PHP enum in `app/Enums/`, named with an `Enum` suffix (`PostStatusEnum`, `PostVisibilityEnum`), validated with `Rule::enum()`. Never list the values in an array or a class constant and check them with `Rule::in()`. `Rule::in()` is for values that come from data at runtime, such as the options one record was granted.

Enum case names are all UPPER_SNAKE_CASE, never TitleCase. The backing value is either the same upper-case string or its lower-case form: `case IN_REVIEW = 'IN_REVIEW';` or `case IN_REVIEW = 'in_review';`, never `case InReview = 'IN_REVIEW';`. This overrides the TitleCase enum-key rule in the Laravel Boost guidelines.

```php
// ❌
public const array STATUSES = ['DRAFT', 'PUBLISHED'];
'status' => ['required', 'string', Rule::in(PostService::STATUSES)],

// ✅
'status' => ['required', 'string', Rule::enum(PostStatusEnum::class)],
```

Never write a closure rule inside `rules()`. Any check the built-in rules and `Rule::exists()` / `Rule::unique()` cannot express becomes a dedicated invokable rule class in `app/Rules/` implementing `Illuminate\Contracts\Validation\ValidationRule`, created with `php artisan make:rule NameRule --no-interaction` and named with a `Rule` suffix (`PublishableSlugRule`). Pass any context it needs, such as another field's value or an owning model id, through the constructor via promoted properties, so the rule never reaches back into the request. Closures cannot be reused, tested in isolation, or named; rule classes can.

A check that spans several fields at once, needs the whole submitted payload, or must run only after the individual fields already passed, belongs in the request's `after()` method rather than in `rules()`. Every entry `after()` returns MUST be an invokable class, never an inline closure, for the same reason: a closure cannot be named, reused across the Store/Update pair, or tested on its own.

These invokable classes live in `app/Validation/`, in the `App\Validation` namespace, as the Laravel documentation prescribes. They are NOT `app/Rules/` classes: `app/Rules/` holds `ValidationRule` objects that validate one field's value and carry a `Rule` suffix, while an `App\Validation` class implements a single `__invoke(Validator $validator): void` that receives the `Illuminate\Validation\Validator` instance and adds errors through `$validator->errors()->add(...)`. Name them for the invariant they enforce, with no suffix (`UniqueTagsPerPost`). Read the submitted data with `$validator->getData()` rather than reaching back into the request, and return early when `$validator->errors()->isNotEmpty()` so the hook never stacks a second error on top of input that already failed its own rules.

```php
public function after(): array
{
    return [
        new UniqueTagsPerPost,
    ];
}
```

Update requests require the same fields as their Store counterpart; do not downgrade `required` to `sometimes`. The only exception is upload fields such as images, pdf, or docx, which become `nullable` on update so clients can omit them to keep the existing file.

## 5. Authorization (Policies)

**Hard rule:** never check permissions with an inline `if` in a controller or a Service. Every "can this user do this?" check goes through Laravel's Gate: a Policy, a permission, or the middleware that calls them.

A check about one record ("may this user edit *this* post?") lives in a Policy at `app/Policies/{Model}Policy.php`, created with `php artisan make:policy PostPolicy --model=Post --no-interaction`. Call it from where the endpoint already has a home for it:

| The endpoint has | Call the Policy from |
| --- | --- |
| A Form Request (it has rules) | Its `authorize()` method |
| No Form Request | `#[Authorize('update', 'post')]` (`Illuminate\Routing\Attributes\Controllers\Authorize`) on the action, on Laravel 13; the route's `->can('update', 'post')` on Laravel 12 |

```php
// Form Request
public function authorize(): bool
{
    return $this->user()->can('update', $this->route('post'));
}

// No Form Request
#[Authorize('update', 'post')]
public function destroy(Post $post): JsonResponse
```

For a record owned by one user or tenant, where a stranger must not learn that the id exists, the Policy returns `Response::denyAsNotFound()` (`Illuminate\Auth\Access\Response`) instead of `false`, and the test asserts `assertNotFound()`.

When the app already has `spatie/laravel-permission` in `composer.json`, use it; do not install it just for this skill. It registers its permissions with the Gate, so everything above keeps working:

- A check that depends only on a role or permission ("may editors publish?") needs no Policy: use its `permission:publish posts` / `role:editor` route middleware, or `#[Authorize('publish posts')]`.
- A check that also depends on the record calls the permission from the Policy: `return $user->can('edit posts') && $post->user_id === $user->id;`.

When the request has nothing to authorize, because middleware or a route `->can()` already does it, delete `authorize()` entirely. Never leave an `authorize()` that only returns `true`: Laravel treats a missing method as authorized, as its validation docs state.

```php
// ❌
public function authorize(): bool
{
    return true;
}

// ✅ no authorize() method at all
```

## 6. DTOs

Pass `$request->validated()` to the Service by default; the Service declares the keys it reads in an `@param array{…}` tag. Add a DTO only when a second entry point, such as a job or a console command, must build the same input. A DTO is a plain `final readonly` class at `app/Dto/{Domain}/{Name}Dto.php` with promoted constructor properties and no behavior. Do not add a DTO package.

The Form Request builds it from the validated input in a `toDto()` method, so the controller never assembles it:

```php
// app/Dto/Post/StorePostDto.php
final readonly class StorePostDto
{
    public function __construct(
        public string $title,
        public PostStatusEnum $status,
        public ?string $excerpt,
        public int $categoryId,
    ) {}
}

// StorePostRequest
public function toDto(): StorePostDto
{
    return new StorePostDto(
        title: $this->validated('title'),
        status: $this->enum('status', PostStatusEnum::class),
        excerpt: $this->validated('excerpt'),
        categoryId: $this->integer('categoryId'),
    );
}
```

Without a second entry point, skip the DTO and pass `$request->validated()`.

## 7. Services

Services live at `app/Services/V1/{Domain}/{Name}Service.php`, with no interface required. They own all business logic: query building, often via private helpers such as `indexQuery()` and `applyFilters()`; pagination; `DB::transaction()` for multi-step writes; and calls into other Services.

Each resource has one CRUD Service holding its standard operations. Work that is not plain CRUD gets its own focused Service named for what it does:

| Service | Holds |
| --- | --- |
| `PostService` | `index()`, `show()`, `store()`, `update()`, `destroy()` |
| `PostPublishingService` | `publish()`, `unpublish()` |
| `StripeClient` | Talking to an outside system |

Split rule: a Service has one reason to change. When a new method serves a different purpose from the rest of the Service, or a Service starts needing many unrelated dependencies, move that work into its own focused Service instead of growing the existing one. Split by purpose, not by line count.

Service methods accept plain PHP types only: data objects, arrays, scalars, and Eloquent models. They never accept a `Request` object and never return a `JsonResponse` or Resource. Normalize `array|string|int` id input (a comma-separated string, a single int, or an array) to a list before a bulk `whereIn()` operation. Pagination parameters such as `per_page` are validated in the endpoint's filters Form Request and passed into the Service as plain values.

### Failures

A Service signals a failure by throwing, never by returning an outcome enum, a status flag, or `null` for the controller to `match` on. The Service stops at the failure, and every caller (controller, job, command, test) gets the same behavior without repeating the `match`. This is Laravel's own model: an exception that knows how to report and render itself (https://laravel.com/docs/13.x/errors).

```php
// ❌ The Service returns an outcome; every caller must translate it.
$outcome = $this->service->publish($post);

return match ($outcome) {
    PublishPostOutcomeEnum::PUBLISHED => ApiResponse::success(PostResource::make($post)),
    PublishPostOutcomeEnum::ALREADY_PUBLISHED => ApiResponse::error(message: __('posts.already_published'), statusCode: 409),
};

// ✅ The Service throws; the controller is the happy path.
$post = $this->service->publish($post);

return ApiResponse::success(PostResource::make($post));
```

Pick the tool by what kind of failure it is:

| Failure | Use | Logged |
| --- | --- | --- |
| Expected business failure the client handles ("already published", "coupon invalid") | **Default:** a domain exception that renders the error envelope (below) | No |
| A one-off HTTP failure in the HTTP layer (controller, Form Request, middleware) | `abort(409, __('posts.already_published'))`; `bootstrap/app.php` renders it into the envelope | No |
| Something that should never happen: a bug, broken data, an outside system down | A plain `RuntimeException` / `LogicException`, or let the original exception bubble up | **Yes** |

`abort()` inside a Service works, but it ties the Service to HTTP, while the same Service also runs from jobs and commands. Prefer a domain exception there.

**Hard rule:** only expected failures skip the logs. Never mark a real error `ShouldntReport`, and never swallow one with `catch (Throwable)` to return a friendly message: it hides the bug from the error tracker. Catch only an exception you can handle, such as retrying a failed outside call.

A domain exception lives at `app/Exceptions/{Domain}/{Problem}Exception.php`. It implements `Illuminate\Contracts\Debug\ShouldntReport`, so Laravel never logs it, and uses the shared `HasRender` trait, whose `render()` Laravel calls on its own to build the response. The translated message goes to the parent constructor, and the HTTP status goes in a `$status` property, the way Laravel's `ValidationException` holds its 422. Do not put the status in the exception's `$code`: PHP's `$code` is an app error code, and the envelope's own `code` key already means an `ErrorCodeEnum`.

```php
// app/Support/Traits/Exceptions/HasRender.php
trait HasRender
{
    public function render(): JsonResponse
    {
        return ApiResponse::error(
            message: $this->getMessage(),
            statusCode: $this->status,
        );
    }
}

// app/Exceptions/Post/PostAlreadyPublishedException.php
final class PostAlreadyPublishedException extends Exception implements ShouldntReport
{
    use HasRender;

    protected int $status = Response::HTTP_CONFLICT;

    public function __construct()
    {
        parent::__construct(__('posts.already_published'));
    }
}

// PostPublishingService
/**
 * @throws PostAlreadyPublishedException
 */
public function publish(Post $post): Post
{
    if ($post->isPublished()) {
        throw new PostAlreadyPublishedException;
    }

    // ...
}
```

Choose the status by what went wrong: `409` when the record's current state blocks the action (already published, already enrolled), `422` when the input breaks a business rule that validation cannot see, `400` for a malformed request. `403` and `404` come from Policies and `findOrFail()` / route binding, not from domain exceptions.

### Data computed before the controller

Sometimes a middleware resolves something once (the current tenant, a verified caller) and the endpoint needs it too. It reaches the controller and the Service as a **typed value**, never through `$request->attributes->set()` / `get()` and never through `Context`. The attributes bag is untyped (`get()` returns `mixed`) and makes the controller silently depend on a middleware having run; `Context` is for sharing information with logs and queued jobs, not for handing objects to Services.

Pick by what the value is:

| The value is | Use |
| --- | --- |
| A model named in the URL (`/posts/{post}`) | Route model binding, or `resolveRouteBinding()` on the model for a custom lookup |
| Who is calling (an API key client, a partner, a device) | A custom guard with `Auth::viaRequest()`; read it with `auth()->user()` behind the `auth:` middleware |
| Derived from this endpoint's input (a coupon from a code, a verified signature) | The Form Request: compute it in `passedValidation()`, keep it on a property, expose a typed method (`$request->coupon()`) |
| Any other per-request value several layers need (the current tenant) | A `scoped` container binding (below) |
| A slow calculation called many times in one request | `once(fn () => ...)` |

A `scoped` binding is built at most once per request and cleared after it, so it is as fast as the attributes bag and safe under Octane, where a `singleton` would leak one request's value into the next. The middleware only guards the route; the controller asks for the value by type, so it also works, and tests can swap it with `app()->instance()`, without the middleware:

```php
// AppServiceProvider::register()
$this->app->scoped(Tenant::class, fn (Application $app): Tenant => Tenant::query()
    ->where('domain', $app['request']->header('X-Tenant'))
    ->firstOr(fn () => throw new TenantNotFoundException));

// ResolveTenantMiddleware: rejects the request early; resolves the one instance
public function handle(Request $request, Closure $next): Response
{
    app(Tenant::class);

    return $next($request);
}

// Controller: a GET endpoint with no Form Request
public function index(Tenant $tenant): JsonResponse
{
    $posts = $this->service->index(tenant: $tenant);

    return ApiResponse::paginated(
        paginator: $posts,
        resource: PostResource::class,
    );
}
```

## 8. Models and queries

**Hard rule:** `AppServiceProvider::boot()` turns on Eloquent strict mode outside production. It makes three silent mistakes throw in local development and tests: a lazy-loaded relation (an N+1 the Service forgot to eager-load), a write to a column missing from `$fillable` (instead of dropping it), and a read of a column the query did not select.

```php
Model::shouldBeStrict(! $this->app->isProduction());
```

**Hard rule:** every model lists its writable columns in `$fillable`. Never use `$guarded = []` or `Model::unguard()`: the Service passes `$request->validated()` to `create()` / `update()`, and `$fillable` is the last guard on which columns that array can reach.

A model declares its casts in a `casts()` method: enum columns to their `*Enum`, timestamps to `immutable_datetime`, booleans to `boolean`, JSON columns to `array` or `AsArrayObject`. Then `$post->status` is a `PostStatusEnum`, never a string the Service compares by hand.

```php
protected function casts(): array
{
    return [
        'status' => PostStatusEnum::class,
        'published_at' => 'immutable_datetime',
        'is_featured' => 'boolean',
    ];
}
```

### Scopes

A reusable query condition, such as "published posts" or "posts of a given type", becomes a scope on the model, not a Service method and never a repository class. Use the `#[Scope]` attribute on a `protected` method returning `void`, with `@noinspection PhpUnused` so the IDE doesn't grey out a scope that is in use:

```php
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/** @noinspection PhpUnused */
#[Scope]
protected function published(Builder $query): void
{
    $query->where('status', PostStatusEnum::PUBLISHED);
}
```

Services compose scopes (`Post::query()->published()->ofType($type)`) rather than repeating `where()` chains. A query used in one place only stays in the Service.

## 9. Events and side effects

Events are for side effects that can happen independently of the main operation: sending an email, notifying another system, recording metrics. The main business rule always stays in the Service, where a reader can see it, never inside a listener.

- Make listeners that do I/O queued (`ShouldQueue`).
- Dispatch events after the database transaction commits (`ShouldDispatchAfterCommit` on the event), so a listener never acts on a rolled-back write.
- Do not put business rules in model observers or model events. They run on every save, including in factories, and hide behavior from the code that triggered it.
- For a single side effect that must succeed together with the write, call it directly from the Service instead of firing an event.

## 10. Slow work (queued jobs)

Anything slow, such as calling an outside API, generating a file, or bulk processing, runs in a queued Job (`ShouldQueue`) so the request returns quickly. The Job stays thin like a controller: it receives ids or plain values and calls a Service method.

- Laravel tries a job once by default. A job that can fail for a passing reason declares `$tries`, `$backoff`, and `$timeout`, and keeps `$timeout` below the queue connection's `retry_after`.
- `handle()` must be safe to run twice, because a retry or a worker restart can repeat it. Check the state before acting ("already sent?"), or make the job `ShouldBeUnique` when two copies at once would do harm.
- **Hard rule:** a job, a queued mail, or a queued notification dispatched inside `DB::transaction()` waits for the commit: `->afterCommit()` on the dispatch, or `'after_commit' => true` on the queue connection. Otherwise a worker can pick it up before the row exists, or after it was rolled back.
- Never `->get()` a set with no upper bound. Process it with `chunkById()` or `lazyById()`, which stay correct when the loop updates the column the query filters on.

`references/production-practices.md` has the full job template, `failed()` handling, and unique jobs.

## 11. Abstractions and dependencies

- Add an interface only when a second implementation exists, such as a second payment gateway. Bind the default implementation with `#[Bind(StripePaymentGateway::class)]` on the interface. With two or three implementations, pick one with a `match` on the enum. Move to `#[Tag('gateways')] iterable $gateways` and a registry only when implementations keep growing (four or more).

```php
$gateway = match ($post->paymentMethod) {
    PaymentMethodEnum::STRIPE => app(StripePaymentGateway::class),
    PaymentMethodEnum::PAYPAL => app(PaypalPaymentGateway::class),
};
```

- A subclass never throws a "not supported" exception or leaves an inherited method empty. If one would, the hierarchy is wrong: split the contract into smaller interfaces and implement only the ones that apply.
- Inject dependencies through the constructor. Facades (`DB::`, `Http::`, `Cache::`) and `new` for data objects, value objects, and exceptions are fine.
- **Hard rule:** call `env()` only inside `config/*.php`. App code reads `config('services.stripe.secret_key')`. Once `php artisan config:cache` runs, as it does in production, `env()` outside config returns `null`.
- A `{System}Client` sets a timeout, a connect timeout, and a retry on every call, and calls `->throw()`: Laravel's HTTP client waits 30 seconds by default and does not throw on a 4xx or 5xx response. See `references/production-practices.md`.
- Since PHP 8.4, a method or property can be used straight on a `new` expression, with no parentheses around it. When the app requires PHP 8.4+ in `composer.json`, always drop them. The constructor's own argument parentheses must stay: `new Parser(new JoseEncoder)->parse($token)` works, but `new JoseEncoder->encode()` does not, and needs `new JoseEncoder()->encode()`.

```php
// ❌
$jwt = (new Parser(new JoseEncoder))->parse($token);

// ✅ PHP 8.4+
$jwt = new Parser(new JoseEncoder)->parse($token);
```

- A value used in exactly one place stays a literal there. Never wrap it in a constant just to name it: the constant is one more thing to read and adds nothing. Add a public constant once two or more places must agree on the value, and tests count as a place: a header name the middleware reads and its tests send is shared, so it's a constant.

```php
// ❌ Read in one place, used nowhere else.
private const int DEFAULT_PER_PAGE = 15;

$perPage = $filters['perPage'] ?? self::DEFAULT_PER_PAGE;

// ✅
$perPage = $filters['perPage'] ?? 15;

// ✅ The middleware reads it and the tests send it.
public const string KEY_HEADER = 'Idempotency-Key';

$key = (string) $request->header(self::KEY_HEADER);
```

## 12. Output: Resources inside ApiResponse

Resources live at `app/Http/Resources/V1/{Domain}/{Model}Resource.php`, extending `Illuminate\Http\Resources\Json\JsonResource`. Use `whenHas()` for columns that may be excluded by a `select()`, `whenLoaded()` for relations, `whenCounted()` for `withCount()` aggregates, and `mergeWhen()` for conditional attribute groups. Compose nested simple resources for relations, such as `UserSimpleResource`, instead of inlining relation arrays.

A Resource never queries the database. It only shapes data that is already loaded. Never call `Model::query()`, a scope, `find()`, `get()`, or `first()` inside `toArray()` or a Resource helper. Each one runs once per resource, so it becomes an N+1 as soon as the resource is used in a collection, and it hides database work in the output layer. Define a relation on the model, eager-load it in the Service that returns the model (`->with([...])`), and read it in the Resource with `whenLoaded()`. Every Service path that feeds the Resource must load the relation, because `whenLoaded()` drops the key when it isn't loaded.

```php
// ❌
'comments' => Comment::approved($this->id)->get()->map(...),

// ✅ Model
public function approvedComments(): HasMany
{
    return $this->hasMany(Comment::class, 'postId')->whereNotNull('approvedAt');
}

// ✅ Service
return Post::query()->with(['approvedComments'])->findOrFail($postId);

// ✅ Resource
'comments' => $this->whenLoaded('approvedComments', fn () => $this->approvedComments->map(...)->values()->all()),
```

Every response, success or error, goes through `App\Http\Responses\ApiResponse`. A success body is `{success: true, message, data, timestamp}`, never a bare `null`; a paginated list adds `pagination`; an error is `{success: false, message, code?, errors?, timestamp}`. Never return a raw `response()->json()` or a bare Resource. Controller actions return `JsonResponse`. `bootstrap/app.php` routes validation, 401, 403, and 404 errors through it; `references/api-response.md` explains the shapes and that mapping. Never change the method names or keys without updating the clients that parse them.

Use named arguments when an app-code call passes two or more arguments, or when a literal's meaning is unclear, one per line when the call wraps. A single obvious argument stays positional.

```php
// ✅ One obvious argument.
return ApiResponse::created(PostResource::make($post));

// ❌ Two arguments, positional.
return ApiResponse::paginated($paginator, PostResource::class);

// ✅
return ApiResponse::paginated(
    paginator: $paginator,
    resource: PostResource::class,
);
```

| Action | Return |
| --- | --- |
| `show`, `update` | `ApiResponse::success(PostResource::make($post))` |
| `store` | `ApiResponse::created(PostResource::make($post))` |
| `index` | `ApiResponse::paginated(paginator: $paginator, resource: PostResource::class)` |
| `destroy` | `ApiResponse::noContent()` |
| An expected failure | Nothing: the Service throws a domain exception, which renders `ApiResponse::error()` itself (section 7) |

No error response is built in a controller. Validation, authentication, authorization, and not-found errors go through the exception mapping in `bootstrap/app.php`, and domain exceptions render themselves.

Map a model to its output with its Resource, never with an array built in the controller:

```php
// ❌
return ApiResponse::created([
    'postId' => $post->id,
    'title' => $post->title,
    'status' => $post->status,
]);

// ✅
return ApiResponse::created(PostResource::make($post));
```

A Resource always wraps an Eloquent model, never a DTO or an array, so `whenLoaded()`, `whenCounted()`, and `whenHas()` keep working on it. When the response also needs a value the model can't give, such as a preview link known only at creation, take it as an extra constructor argument and pass it through `make()`:

```php
// ❌
return ApiResponse::created(StoredPostResource::make($storedPostDto));

// ✅
public function __construct(Post $resource, protected readonly string $previewUrl)
{
    parent::__construct($resource);
}

return ApiResponse::created(StoredPostResource::make($stored->post, $stored->previewUrl));
```

## 13. Testing

Tests use Pest and live under `tests/Feature/{Domain}/...`. Authenticate with `actingAs($user)` using a factory-built user. Assert on the envelope's `data`, for example `assertJsonPath('data.status', 'PUBLISHED')` on a single resource, `assertJsonStructure(['data' => ['*' => ['id', 'title']], 'pagination'])` on a list, and `assertJsonPath('success', false)` on error cases, and prefer specific assertions such as `assertOk()`, `assertCreated()`, `assertNoContent()`, and `assertForbidden()` over `assertStatus()`. Cover the Policy with at least one `assertForbidden()` test per protected endpoint. Define reusable payload or upload helper functions at the top of a test file, such as `validPostPayload()`, rather than duplicating arrays across tests.

- Reset the database with `RefreshDatabase` (or `LazilyRefreshDatabase`).
- Fake every outside effect the test does not check by hand: `Queue::fake()`, `Event::fake()`, `Mail::fake()`, `Notification::fake()`, and `Http::fake()` with `Http::preventStrayRequests()`, so a forgotten fake fails instead of calling a real API.
- Assert a 422 with `assertJsonValidationErrors(['title'])`, and a domain exception with its status plus `assertJsonPath('success', false)`.
- Freeze the clock with `freezeTime()` or `travelTo()` for anything date-based.

Guard the layering with Pest architecture tests in `tests/Unit/ArchitectureTest.php`, so a boundary leak fails the build:

```php
arch('services never touch the HTTP layer')
    ->expect('App\Services')
    ->not->toUse(['Illuminate\Http\Request', 'Illuminate\Http\JsonResponse']);

arch('controllers do not query the database directly')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('data objects are final and readonly')
    ->expect('App\Dto')
    ->toBeFinal()
    ->toBeReadonly();

arch('domain exceptions are expected failures, never logged')
    ->expect('App\Exceptions')
    ->classes()
    ->toImplement('Illuminate\Contracts\Debug\ShouldntReport');

arch('env is only read in config files')
    ->expect('env')
    ->not->toBeUsedIn('App');
```

When the app has a custom exception that must be logged, exclude it from the exceptions test with `->ignoring(...)`.

## 14. Docblocks and comments

A docblock explains what the signature cannot. Comments explain *why*, never *what*.

Never write `array<string, mixed>` in a `@param` or `@return` tag. It says no more than the native `array` type in the signature, so drop the tag. Keep an array tag only when it gives a real shape (`array{…}`) or a known value type.

```php
// ❌
/**
 * Get the post's SEO metadata.
 *
 * @return array<string, mixed>
 */
public function metadata(): array

// ✅
/**
 * Get the post's SEO metadata.
 */
public function metadata(): array
```

## Checklist for a new endpoint

1. Name every new class, method, and variable by section 1.
2. Pick the controller: a standard resource method on the resource's controller, or a new controller for a non-CRUD operation.
3. If the endpoint has input to validate, create the Form Request with `php artisan make:request V1/{Domain}/{Action}Request --no-interaction`, with `authorize()` calling the Policy (or no `authorize()` when there is nothing to check) and `toDto()` only when a job or command also builds the same input. With nothing to validate, skip it: take `Request` only if the action reads from it, and authorize with `#[Authorize]` or the route's `->can()`.
4. Add the business logic to the resource's CRUD Service, or to a focused Service when it is not plain CRUD. Keep it free of Request/HTTP concerns. Throw a `ShouldntReport` domain exception with `HasRender` for each expected failure, and declare it in `@throws`.
5. Put reusable query conditions in `#[Scope]` methods on the model. Keep `$fillable` and `casts()` up to date for new columns.
6. Move slow work to a queued Job and independent side effects to queued listeners, with `afterCommit` when dispatched inside a transaction.
7. Add or extend the Resource to shape the output.
8. Keep the controller action to a few lines: type-hint the request, call the Service, return the Resource through `ApiResponse`. No try/catch, no `match` on a result.
9. Register the route with `apiResource` under the correct auth middleware group in `routes/api.php`, with `->scopeBindings()` on nested resources.
10. Write or update a Pest feature test asserting the response shape, status, a forbidden case, and each domain exception the endpoint can throw.
11. Run `vendor/bin/pint --dirty --format agent` and the relevant `php artisan test --compact` filter before finishing.
