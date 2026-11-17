# ApiResponse

All responses go through one `ApiResponse` class at `app/Http/Responses/ApiResponse.php`, and `bootstrap/app.php` routes framework errors through it. When the app already has both, read them for the exact code; this page explains the contract.

## Success bodies

A success response carries the data in one envelope:

```json
{ "success": true, "message": "Success", "data": { "postId": "6af1...", "status": "PUBLISHED" }, "timestamp": "2026-11-03 10:00:00" }
{ "success": true, "message": "Success", "data": [ { } ], "pagination": { "current_page": 1, "per_page": 15, "total": 40, "last_page": 3 }, "timestamp": "2026-11-03 10:00:00" }
```

- `success()` and `created()` put the resource (or array) under `data`. The body is never a bare `null`: when there is nothing to return, send `data: null` with a `message` that says why.
- `paginated()` adds `pagination` with `current_page`, `per_page`, `total`, `last_page`.
- `noContent()` answers 204, and HTTP drops the body of a 204.

## Error envelope

```json
{ "success": false, "message": "Validation failed", "errors": { "title": ["..."] }, "timestamp": "2026-11-03 10:00:00" }
```

- `code` appears when the failure has an `ErrorCodeEnum` the client branches on.
- `errors` appears only when there are details to report. `serverError()` includes them only when `app.debug` is on.

## Compatibility

Clients parse these shapes, so never rename a method or change a key without updating them. `paginated(paginator: $paginator, resource: PostResource::class)` builds the pagination block so no controller repeats it.

## Errors

`bootstrap/app.php` maps framework exceptions to the envelope for API requests (`api/*` or `Accept: application/json`); web requests keep Laravel's default rendering.

| Exception reaching the handler | Response |
| --- | --- |
| `ValidationException` | `validationError()`, 422 |
| `AuthenticationException` | `unauthorized()`, 401 |
| `AccessDeniedHttpException` (a failed Policy or Gate) | `forbidden()`, 403 |
| `NotFoundHttpException` (unknown route, `findOrFail()`, route model binding) | `notFound()`, 404 |
| Any other `HttpExceptionInterface` (`abort(409, ...)`) | `error()` with its status and message, or the standard status text when the message is empty |
| A domain exception with its own `render()` (`ShouldntReport` + `HasRender`) | `error()` with its `$status` and translated message, built by the exception itself |

Laravel calls an exception's own `render()` method before any `bootstrap/app.php` render callback, so a domain exception needs no entry in `bootstrap/app.php`. Laravel converts `ModelNotFoundException` and `AuthorizationException` before these callbacks run, which is why the table names the converted types. Callbacks match in registration order, so the `HttpExceptionInterface` catch-all stays last. Unexpected exceptions keep Laravel's default 500 handling.
