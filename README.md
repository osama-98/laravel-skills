[![Latest Version on Packagist](https://img.shields.io/packagist/v/osama-98/laravel-skills.svg?style=flat-square)](https://packagist.org/packages/osama-98/laravel-skills)
[![Total Downloads](https://img.shields.io/packagist/dt/osama-98/laravel-skills.svg)](https://packagist.org/packages/osama-98/laravel-skills)
[![License](https://img.shields.io/packagist/l/osama-98/laravel-skills.svg?style=flat-square)](https://packagist.org/packages/osama-98/laravel-skills)

# Laravel Skills

A single package of AI-agent knowledge for Laravel applications: always-on **guidelines** and
on-demand **skills**, discovered automatically by [Laravel Boost](https://github.com/laravel/boost)
and synced to every agent you use (Claude Code, Cursor, Copilot, Codex, Junie, …).

These cover tools that Boost's hosted `search-docs` index does not, so agents stop guessing endpoint
shapes or fetching live docs mid-task.

## Install

```bash
composer require --dev osama-98/laravel-skills
php artisan boost:install      # first time: pick "osama-98/laravel-skills" in the third-party list
php artisan boost:update       # after upgrading this package
```

Requires **Laravel Boost 2.x** (package skills were introduced in Boost 2). The package must be a
**direct** dependency of your app — Boost ignores guidelines/skills from transitive dependencies.

## What you get

| Topic | Guideline (always in context) | Skills (loaded on demand) |
|-------|-------------------------------|---------------------------|
| HyperPay (OPPWA) | `hyperpay` — emitted once `config/hyperpay.php` exists | `hyperpay-integration`, `hyperpay-docs` |
| Backpack for Laravel v6 | `backpack` — emitted only if `backpack/crud` is installed; adapts to `backpack/pro` / `permissionmanager` | `backpack` |

Guidelines are **conditional**: Boost renders them with Blade and drops empty output, so a project
that doesn't use Backpack never gets Backpack rules in its context. Skills are cheap (agents load them
only when the task matches their description), so all of them are installed.

### HyperPay

- `hyperpay-integration` — building the integration in Laravel: config layout, layering, checkout,
  registration tokens, merchant-initiated charges, the encrypted webhook, testing.
- `hyperpay-docs` — offline API reference (router + 10 pages): parameters, tokenization, backoffice,
  subscriptions, webhooks, result codes, widget, test cards, payment-method matrix, doc index.

### Backpack for Laravel v6

- `backpack` — CRUD panels end to end, verified against `backpack/crud` 6.8.17 source:
  lifecycle & settings API, every operation (incl. PRO Fetch, InlineCreate, Clone, Bulk*, Trash,
  CustomViews), every FREE/PRO field, column and filter type, relationships decision table, buttons,
  uploaders & validation, widgets/menu/Tabler theme, CrudField JS API, PermissionManager 7.x,
  CLI/Basset/deploy, plus a list of places where the official docs are wrong. Ships copy-ready stubs.

## Layout

```
resources/boost/
├── guidelines/
│   ├── backpack.blade.php
│   └── hyperpay.blade.php
└── skills/
    ├── backpack/              SKILL.md, references/, templates/
    ├── hyperpay-docs/         SKILL.md, references/
    └── hyperpay-integration/  SKILL.md
```

### Adding a new skill

1. Create `resources/boost/skills/<skill-name>/SKILL.md` with `name` (must equal the folder name) and
   a trigger-rich `description` in the frontmatter. Put long material in `references/`.
2. Optionally add `resources/boost/guidelines/<topic>.blade.php` for rules that must always be in
   context. Wrap it in `@if ($assist->hasPackage('vendor/package')) … @endif` so it only appears in
   projects that use that package.
3. Never name files inside a skill `*.blade.php` unless you mean it: Boost renders every Blade file it
   copies. Keep example Blade code in `.md` files or `*.stub` files.
4. Tag a release; users run `composer update osama-98/laravel-skills && php artisan boost:update`.

## Scope

Documentation and agent guidance only — no runtime code, no service provider, nothing to configure.

HyperPay content is sourced from <https://hyperpay.docs.oppwa.com>; Backpack content from
<https://backpackforlaravel.com/docs/6.x> and the Backpack source code. HyperPay, OPPWA and Backpack
are trademarks of their respective owners; this package is not affiliated with or endorsed by them
or by Laravel.

## License

MIT. See [LICENSE](LICENSE).
