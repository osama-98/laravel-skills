# Production Practices

The details behind the short rules in SKILL.md sections 2, 10, 11, and 13. They come from the Laravel 13.x docs (queues, http-client, routing, eloquent, http-tests) and community guides (Spatie, alexeymezenin/laravel-best-practices).

## Queued jobs

Laravel tries a job once and stops it after 60 seconds by default. A job that talks to an outside system or can hit a passing failure declares its retry policy:

```php
final class PublishPostJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds to wait before each retry. */
    public array $backoff = [10, 60, 300];

    /** Keep this below the queue connection's `retry_after`, or a second worker picks the job up while the first still runs it. */
    public int $timeout = 120;

    public function __construct(public int $postId) {}

    public function handle(PostPublishingService $service): void
    {
        $service->publishScheduled($this->postId);
    }

    public function failed(?Throwable $exception): void
    {
        // Tell someone: mark the post as failed, notify the author.
    }
}
```

- **Run twice, same result.** A retry or a worker restart can run `handle()` again. Check the state before acting ("already published?" then return), or let a unique database constraint reject the duplicate.
- **One at a time.** When two copies at once would do harm, implement `ShouldBeUnique` and return the record id from `uniqueId()`. Use the `WithoutOverlapping($this->postId)` job middleware when copies may queue up but must not run together.
- **The record may be gone.** A job that receives a model, and whose model can be deleted before the job runs, sets `public bool $deleteWhenMissingModels = true;`.
- **After the commit.** A job dispatched inside `DB::transaction()` calls `->afterCommit()`, or the connection in `config/queue.php` sets `'after_commit' => true`. The same goes for queued mail (`Mail::to($user)->queue(...)` with a mailable that calls `afterCommit()`) and queued notifications.
- **Deadlocks.** A transaction that can deadlock under load passes `attempts:` to `DB::transaction(fn () => ..., attempts: 3)`.

## Outside-system clients

Laravel's HTTP client waits 30 seconds by default and does not throw on a 4xx or 5xx response, so a client that does not opt in hides failures. Build every request from one configured base:

```php
final class StripeClient
{
    public function charge(Post $post, int $amount): array
    {
        return $this->request()
            ->post('/charges', ['amount' => $amount, 'reference' => $post->id])
            ->throw()
            ->json();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config('services.stripe.base_url'))
            ->withToken(config('services.stripe.secret_key'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->retry(3, 200, fn (Throwable $exception) => $exception instanceof ConnectionException);
    }
}
```

Retry only what is safe to repeat: a connection failure, or a request the outside system deduplicates (an idempotency key). Never blindly retry a `POST` that may already have charged the user.

## Rate limiting

Define the limiters in `AppServiceProvider::boot()`, then attach them to route groups:

```php
RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
    ->by($request->user()?->id ?: $request->ip()));

RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)
    ->by($request->ip()));
```

```php
Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::post('login', LoginController::class)->middleware('throttle:auth');
});
```

## Large data sets

- `Post::query()->...->chunkById(500, fn (Collection $posts) => ...)` loads a page at a time. Use the `ById` variants (`chunkById`, `lazyById`) whenever the loop updates a column the query filters on; plain `chunk()` then skips rows.
- `lazyById()` returns a `LazyCollection` for a `foreach` that keeps memory flat.
- Group `orWhere` conditions in a closure before chunking, so the chunk's own `where` does not combine with them by `OR`.
- Bulk-update in one query (`Post::query()->whereIn('id', $postIds)->update([...])`) when no per-row model event is needed.

## Testing

```php
uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

it('rejects publishing a post twice', function () {
    $post = Post::factory()->published()->create();

    actingAs($post->author)
        ->postJson("/api/v1/posts/{$post->id}/publication")
        ->assertConflict()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('posts.already_published'));
});

it('queues the publish job after the commit', function () {
    Queue::fake();

    // ... act ...

    Queue::assertPushed(PublishPostJob::class);
});
```

- `Http::fake([...])` stubs each outside call; `preventStrayRequests()` fails any call you forgot to stub.
- `assertJsonValidationErrors(['title'])` checks the envelope's `errors` key for a 422.
- `freezeTime()` / `travelTo(now()->addDay())` for scheduled and expiring logic.
