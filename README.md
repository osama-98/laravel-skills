<div align="center">

# Laravel Skills

**AI agent skills and guidelines for Laravel: one Composer package for Laravel Boost, Claude Code, Cursor, GitHub Copilot, Codex and Junie.**

[![Latest Version on Packagist](https://img.shields.io/packagist/v/osama-98/laravel-skills.svg?style=flat-square)](https://packagist.org/packages/osama-98/laravel-skills)
[![Total Downloads](https://img.shields.io/packagist/dt/osama-98/laravel-skills.svg?style=flat-square)](https://packagist.org/packages/osama-98/laravel-skills)
[![License](https://img.shields.io/packagist/l/osama-98/laravel-skills.svg?style=flat-square)](LICENSE)
[![Laravel Boost](https://img.shields.io/badge/Laravel%20Boost-2.x-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://github.com/laravel/boost)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg?style=flat-square)](#contributing)

[Install](#installation) · [Available skills](#available-skills) · [How it works](#how-it-works) · [Add a skill](#contributing) · [FAQ](#faq)

</div>

---

**Laravel Skills** is a growing, open-source library of **AI agent skills** and **Laravel Boost guidelines** for the Laravel ecosystem. Install it once with Composer, and [Laravel Boost](https://github.com/laravel/boost) finds and syncs every skill to the AI coding assistants you use.

AI agents often guess API shapes, invent config keys, or fetch live docs in the middle of a task. That happens most with third-party Laravel packages and payment gateways that Boost's built-in `search-docs` index does not cover. Each skill in this repo is a focused, checked knowledge pack. It gives your agent the right patterns, the real API, and the known gotchas, and it only loads when the task needs it.

> **Goal:** one place for Laravel AI skills. Admin panels, payment gateways, packages, tooling: if Laravel developers use it, it can have a skill here.

## Table of contents

- [Why Laravel Skills?](#why-laravel-skills)
- [Installation](#installation)
- [Available skills](#available-skills)
- [How it works](#how-it-works)
- [Supported AI agents](#supported-ai-agents)
- [Using the skills without Laravel Boost](#using-the-skills-without-laravel-boost)
- [Contributing: add a new skill](#contributing)
- [FAQ](#faq)
- [License](#license)

## Why Laravel Skills?

- **Fewer hallucinations.** Agents use checked references instead of guessing endpoints, field types or config keys.
- **Small context cost.** Skills load on demand. Guidelines are conditional, so they only show up in projects that use that package.
- **One install, every agent.** Laravel Boost syncs the same skills to Claude Code, Cursor, Copilot, Codex, Junie and more.
- **Works offline.** References ship inside the package, so no live doc fetching during a task.
- **No runtime code.** Documentation and agent guidance only: no service provider, no config, nothing added to production.
- **Built to grow.** New skills arrive with `composer update`.

## Installation

Requirements: PHP 8.2+, Laravel, and **Laravel Boost 2.x** (package skills were added in Boost 2).

```bash
composer require --dev osama-98/laravel-skills
php artisan boost:install
```

When `boost:install` asks for third-party packages, select **`osama-98/laravel-skills`**.

After upgrading the package, re-sync the skills:

```bash
composer update osama-98/laravel-skills
php artisan boost:update
```

> [!IMPORTANT]
> The package must be a **direct** dependency of your app (in your own `composer.json`). Boost ignores guidelines and skills from transitive dependencies.

## Available skills

| Topic | Skills (loaded on demand) | Guideline (always in context) |
|-------|---------------------------|-------------------------------|
| **Backpack for Laravel v6** (admin panel) | `backpack` | `backpack`: only when `backpack/crud` is installed; adapts to `backpack/pro` and `permissionmanager` |
| **HyperPay / OPPWA** (payment gateway) | `hyperpay` | `hyperpay`: only once `config/hyperpay.php` exists |

More skills are on the way. [Request a skill](https://github.com/osama-98/laravel-skills/issues/new?title=Skill%20request:%20) or [add one yourself](#contributing).

### Backpack for Laravel v6

**`backpack`**: build Backpack CRUD admin panels end to end. Checked against the `backpack/crud` 6.8.17 source.

- CRUD lifecycle and settings API
- Every operation: List, Create, Update, Show, Delete, Reorder, plus PRO Fetch, InlineCreate, Clone, Bulk\*, Trash, CustomViews and custom operations
- Every FREE and PRO field, column and filter type
- Relationships decision table, buttons, uploaders and validation
- Widgets, admin menu, Tabler theme, CrudField JavaScript API
- PermissionManager 7.x (roles and permissions), CLI, Basset and deployment
- A list of places where the official docs are wrong
- Copy-ready stubs: CrudController, CrudRequest, operations, fields, columns and buttons

### HyperPay (OPPWA)

**`hyperpay`**: add HyperPay payments to a Laravel app, with an offline API reference so the agent doesn't need the live docs.

- Config layout, layering, prepare-checkout and the COPYandPAY widget
- Saved cards (registration tokens) and merchant-initiated charges for subscriptions and installments
- The encrypted webhook and testing
- 10 reference pages: parameters, tokenization, backoffice (capture, refund, reversal), subscriptions, webhooks, result codes, widget options (where 3-D Secure opens, Apple Pay and Google Pay callbacks), test cards, payment-method matrix and doc index

## How it works

The package ships two kinds of content, and Laravel Boost installs both:

| | Guidelines | Skills |
|---|---|---|
| **Location** | `resources/boost/guidelines/*.blade.php` | `resources/boost/skills/<name>/SKILL.md` |
| **When loaded** | Always in the agent's context | Only when the task matches the skill's description |
| **Purpose** | Short, must-follow project rules | Deep reference material, examples and stubs |
| **Conditional?** | Yes: rendered with Blade, and empty output is skipped | No: all skills are installed (they cost nothing until used) |

So a project that doesn't use Backpack never gets Backpack rules in its context. The Backpack skill is still available if you add Backpack later.

```
resources/boost/
├── guidelines/
│   ├── backpack.blade.php
│   └── hyperpay.blade.php
└── skills/
    ├── backpack/              SKILL.md, references/, templates/
    └── hyperpay/              SKILL.md, references/
```

## Supported AI agents

Any agent that Laravel Boost supports, including:

- Claude Code
- Cursor
- GitHub Copilot
- OpenAI Codex
- JetBrains Junie

## Using the skills without Laravel Boost

Each skill is a plain folder with a `SKILL.md` file (the Agent Skills format). If you don't use Boost, copy the folder you need from `resources/boost/skills/` into your agent's skills directory, for example `.claude/skills/` for Claude Code.

## Contributing

Contributions are very welcome. The aim is to cover as much of the Laravel ecosystem as possible, so new skills for popular packages, services and tools are the most useful PRs.

### Add a new skill

1. **Create the skill.** Add `resources/boost/skills/<skill-name>/SKILL.md` with this frontmatter:

   ```yaml
   ---
   name: skill-name          # must match the folder name
   description: "What it does and when to use it. Name the package, classes, commands and keywords a developer would mention."
   license: MIT
   ---
   ```

   The `description` decides when agents load the skill, so make it rich in trigger words. Put long material in `references/` and copy-ready code in `templates/`.

2. **(Optional) Add a guideline** for rules that must always be in context: `resources/boost/guidelines/<topic>.blade.php`. Wrap it so it only appears in projects that use the package:

   ```blade
   @if ($assist->hasPackage('vendor/package'))
   # Package name
   ...
   @endif
   ```

3. **Avoid `*.blade.php` inside skills.** Boost renders every Blade file it copies. Keep example Blade code in `.md` or `*.stub` files.

4. **Update this README.** Add the skill to the [Available skills](#available-skills) table.

5. **Open a pull request.** After a release, users get it with `composer update osama-98/laravel-skills && php artisan boost:update`.

### Skill quality checklist

- [ ] Checked against the package source or official docs (note the version)
- [ ] Clear trigger-rich `description`
- [ ] `SKILL.md` stays short; details live in `references/`
- [ ] Sources listed; no copied proprietary content

## FAQ

<details>
<summary><strong>What is a Laravel Boost skill?</strong></summary>

A skill is a folder with a `SKILL.md` file that teaches an AI agent how to do a specific task. Laravel Boost finds skills in installed Composer packages under `resources/boost/skills` and syncs them to your AI agents. The agent loads a skill only when the task matches its description.
</details>

<details>
<summary><strong>What is the difference between a guideline and a skill?</strong></summary>

Guidelines are always in the agent's context, so they are kept short and conditional. Skills are loaded on demand and can hold deep reference material.
</details>

<details>
<summary><strong>Does this add code to my application?</strong></summary>

No. It is documentation and agent guidance only. Install it as a `--dev` dependency.
</details>

<details>
<summary><strong>Will unused skills fill up my agent's context?</strong></summary>

No. Only the short skill description is visible until the agent decides to load the skill. Guidelines only render for packages your project actually uses.
</details>

<details>
<summary><strong>Can I request a skill for a package?</strong></summary>

Yes. [Open an issue](https://github.com/osama-98/laravel-skills/issues/new?title=Skill%20request:%20) with the package name and what you want the agent to get right.
</details>

## Credits and sources

- HyperPay content is based on the [HyperPay / OPPWA documentation](https://hyperpay.docs.oppwa.com).
- Backpack content is based on the [Backpack for Laravel 6.x docs](https://backpackforlaravel.com/docs/6.x) and the Backpack source code.
- Built for [Laravel Boost](https://github.com/laravel/boost).
- Author: [Osama Sadah](https://github.com/osama-98).

Laravel, HyperPay, OPPWA and Backpack are trademarks of their respective owners. This package is not affiliated with or endorsed by them.

## License

MIT. See [LICENSE](LICENSE).

---

<div align="center">

If Laravel Skills helps you, please ⭐ **star the repo** so other Laravel developers can find it.

<sub>Keywords: Laravel AI skills, Laravel Boost skills, Laravel Boost guidelines, Claude Code skills for Laravel, Cursor rules for Laravel, GitHub Copilot instructions Laravel, AI coding assistant Laravel, Backpack for Laravel AI, HyperPay Laravel.</sub>

</div>
