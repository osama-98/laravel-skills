---
name: clean-code
description: "A layered architecture for Laravel API endpoints and the business logic behind them: naming conventions, thin RESTful controllers, FormRequest validation, Policy authorization, focused Services, readonly DTOs, model scopes, events for side effects only, and Resources returned through ApiResponse. Load before writing or changing any controller, request, service, DTO, policy, resource, route, job, event, or listener, or before naming a new class, interface, enum, method, or variable."
when_to_use: "Adding or changing an endpoint or route; naming a new class, interface, enum, trait, method, or variable; deciding where validation, authorization, queries, or business logic belong; moving logic out of a controller; creating or splitting a Service; adding a DTO; deciding whether to add an interface or a second implementation (such as a payment gateway); reviewing an endpoint's request-to-response flow. Files: app/**, routes/api.php, tests/**."
license: MIT
metadata:
  author: osama-98
---

# Clean Code: Request / Controller / Service Architecture

This architecture strictly separates the HTTP layer from business logic. Every versioned API endpoint follows the same flow: Route, then Form Request for validation and authorization, then Controller for orchestration, then Service for business logic, then API Resource shaped data returned through `ApiResponse`.

Controllers never contain business logic. Services never touch `Illuminate\Http\Request`, validation, or response formatting. This separation keeps controllers thin adapters and lets the same Service run from a controller, a queued job, a console command, or a test.

All business logic lives in Services. It does not use Action classes or repositories, so there is one place to look for logic and no choice to make between patterns.

It targets Laravel 12+ (the `#[Scope]` and `#[Bind]` attributes) and adds one convention of its own: every response goes through an `ApiResponse` envelope (`references/api-response.md`). Where an app already has a different, consistent convention, follow the app.

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
| Trait | `app/Concerns/` | Adjective or `Has…` | `HasSlug`, `Publishable` | `SlugTrait` |
| Job | `app/Jobs/` | `{Verb}{Model}Job` | `PublishPostJob` | `PostJob`, `PublishPost` |
| Event | `app/Events/` | `{Model}{PastTense}Event` | `PostPublishedEvent` | `PublishPostEvent` |
| Listener | `app/Listeners/` | `{Verb}{What}Listener` | `SendPostPublishedMailListener` | `PostListener` |
| Mail | `app/Mail/` | `{Model}{Noun}Mail` | `PostPublishedMail` | `PostEmail` |
| Console command | `app/Console/Commands/` | `{Verb}{What}Command` | `PublishScheduledPostsCommand` | `PostCron` |
| Middleware | `app/Http/Middleware/` | `{Purpose}Middleware` | `VerifyWebhookSignatureMiddleware` | `WebhookCheck` |
| Exception | `app/Exceptions/` | `{Problem}Exception` | `PostAlreadyPublishedException` | `PostError` |
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

## 2. Routes

API routes live in `routes/api.php`, versioned under `Route::prefix('v1')`, and grouped by their auth middleware. Register resources with `Route::apiResource('posts', PostController::class)`, using `->except([...])`, `->only([...])`, and `->names(...)` to customize, rather than declaring every verb manually. Bulk-destroy endpoints accept a route or query param of ids rather than a separate bulk-delete route. Group related nested routes with `Route::prefix('posts')->group(function () { ... })`.

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

## 4. Form Requests

Form Requests live at `app/Http/Requests/V1/{Domain}/{Action}Request.php`, for example `StorePostRequest`, `UpdatePostRequest`, `PostFiltersRequest`, and extend `Illuminate\Foundation\Http\FormRequest`. Create them with `php artisan make:request V1/{Domain}/{Action}Request --no-interaction`.

Never validate inline with `$request->validate([...])` in a controller. Every endpoint gets a dedicated request class, including filter or query-param-only endpoints and single-field lookups. Use array-based validation rules, one rule per array item, matching sibling requests. Use `Rule::enum(SomeEnum::class)` for enum columns and `Rule::exists()` / `Rule::unique()` with `->where()` constraints for relational or scoped checks.

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

Every "can this user do this?" check lives in a Policy at `app/Policies/{Model}Policy.php`, created with `php artisan make:policy PostPolicy --model=Post --no-interaction`. Call it from the Form Request's `authorize()` method, or from the route with `->can('update', 'post')`. Never check permissions inline in a controller or a Service.

```php
public function authorize(): bool
{
    return $this->user()->can('update', $this->route('post'));
}
```

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

## 8. Queries (model scopes)

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
| An expected failure the Service reports | `ApiResponse::error(message: $message, statusCode: $status)` or the matching helper (`notFound()`, `forbidden()`, ...) |

Validation, authentication, authorization, and not-found errors are never built in a controller: the exception mapping turns them into the error envelope.

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
```

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
3. Create the Form Request with `php artisan make:request V1/{Domain}/{Action}Request --no-interaction`, with `authorize()` calling the Policy (or no `authorize()` when there is nothing to check) and `toDto()` only when a job or command also builds the same input.
4. Add the business logic to the resource's CRUD Service, or to a focused Service when it is not plain CRUD. Keep it free of Request/HTTP concerns.
5. Put reusable query conditions in `#[Scope]` methods on the model.
6. Move slow work to a queued Job and independent side effects to queued listeners.
7. Add or extend the Resource to shape the output.
8. Keep the controller action to a few lines: type-hint the request, call the Service, return the Resource through `ApiResponse`.
9. Register the route with `apiResource` under the correct auth middleware group in `routes/api.php`.
10. Write or update a Pest feature test asserting the response shape, status, and a forbidden case.
11. Run `vendor/bin/pint --dirty --format agent` and the relevant `php artisan test --compact` filter before finishing.
