# CLAUDE.md — Coal Management (Kisantra Project Management)

Guidance for Claude when working in this repository. Read this before making changes. For folder-level details see [FOLDER_STRUCTURE.md](FOLDER_STRUCTURE.md).

## How to answer my questions and tasks (read this first)

When I give you a question or task, follow this approach:

1. **Clarify first.** If the request is ambiguous, has multiple valid interpretations, or could be solved in meaningfully different ways, ask 1–2 targeted clarifying questions **before** writing code. Don't ask for trivial mechanical edits (rename, typo fix, obvious one-liner) — just do those.

2. **Break it down step by step.** For any non-trivial task (multi-file changes, new features, anything touching the database, async work, or panel/permission boundaries), lay out the plan as numbered steps before executing. Show me the plan first when the change is risky or large; for medium tasks, execute and surface the steps as you go.

3. **Be critical — push back when the idea is wrong.** I want a sparring partner, not a yes-machine. Always:
   - **Flag bugs or risks** the approach could introduce (race conditions, N+1, permission bypass, broken migration order, lost data, breaking the Trackable / activity log chain, etc.).
   - **Call out a wrong premise.** If I'm asking you to fix the wrong layer, or my suggested fix treats a symptom instead of the cause, say so.
   - **Suggest a better alternative** when one exists. Briefly explain the tradeoff and let me decide — don't silently substitute your choice for mine.
   - **Disagree explicitly.** If I push back and you still think I'm wrong, say "I still think X because Y" rather than caving.

4. **Project-specific things to always check** before agreeing to a plan:
   - Which **panel** (admin `/` vs client `/klien`) the change targets — and whether files are landing in the right folder ([Client/Panel/](app/Livewire/Client/Panel/) vs [Client/Management/](app/Livewire/Client/Management/)).
   - Permission gating (`auth()->user()->can(...)` on Filament resources).
   - Whether a **migration** is being edited in-place (don't) vs added as a new file (do).
   - Whether activity logging should fire via the `Trackable` trait (descriptions in Bahasa Indonesia).
   - Whether the change affects the **Reverb broadcast** path or **Google Drive** upload job.

5. **Don't fabricate.** If you don't know a function signature, package behavior, or whether something exists in the codebase — read or grep first. Saying "I'm not sure, let me check" is better than confidently inventing.

The shape of a good response to a non-trivial task is: *clarifying question (if needed) → plan → flagged concerns / alternative → wait for confirmation → execute*.

## What this app is

A Laravel + Filament admin/client portal for an Indonesian tax & project management consultancy ("Kisantra"). It tracks clients, projects, daily tasks, SOPs, and **Indonesian tax reporting** (PPh = income tax, PPN = VAT, Bupot = tax withholding slips, Faktur Pajak / Invoices). The app is bilingual — UI labels and log messages are mostly in Bahasa Indonesia, code identifiers in English.

The system has **two Filament panels**:

| Panel | Path | Users | Entry point |
|-------|------|-------|-------------|
| Admin | `/` | Internal staff (Kisantra) | [AdminPanelProvider.php](app/Providers/Filament/AdminPanelProvider.php) |
| Client | `/klien` | Clients (self-service) | [ClientPanelProvider.php](app/Providers/Filament/ClientPanelProvider.php) |

Panel routing is enforced by [RedirectToProperPanelMiddleware.php](app/Http/Middleware/RedirectToProperPanelMiddleware.php) — users with the `client` role land on `/klien`, everyone else on `/`.

## Tech stack

- **PHP** ^8.1, **Laravel** ^10.10
- **Filament** 3.3.18 (admin panel framework) + Livewire (reactive components)
- **MySQL** (database `ProjectManagement`)
- **Laravel Reverb** for WebSockets / real-time (chat, notifications)
- **Tailwind CSS** 3.4 + Vite 5 (front-end build)
- **Spatie Activitylog** + `FilamentUserActivity` (audit trail)
- **Solution Forest Filament Access Management** (roles & permissions)
- **Google Drive** as cloud filesystem (`yaza/laravel-google-drive-storage`) — `FILESYSTEM_CLOUD=google`
- **Maatwebsite/Excel** (import/export), **dompdf** (PDF), **Pusher** (broadcasting fallback)
- **AI**: Gemini (`google-gemini-php/laravel`), OpenAI SDK, NeuronAI (`app/Neuron/TaxBotAgent.php`) — used in [TaxChat.php](app/Filament/Pages/TaxChat.php) and [InvoiceAIService.php](app/Services/InvoiceAIService.php)

## Directory map (high-level)

```
app/
├── Filament/              # Filament resources, pages, widgets (admin + client panels)
│   ├── Client/Pages/      # Client panel pages (only DashboardClient.php currently)
│   ├── Pages/             # Admin custom pages (Dashboard, TaxChat, DailyTask, etc.)
│   ├── Resources/         # Admin CRUD resources (Client, Project, TaxReport, …)
│   ├── Exports/ Imports/  # Excel exporters/importers
│   └── Widgets/           # Global admin widgets
├── Livewire/              # Livewire components used inside Filament views
│   ├── Client/Panel/      # Tabs rendered in client panel (/klien)
│   ├── Client/Management/ # Tabs rendered inside admin client detail view
│   ├── DailyTask/  Dashboard/  Projects/  TaxReport/  Widget/  …
├── Models/                # Eloquent models (see "Domain models" below)
├── Services/              # Business logic (ChatService, TaxCalculationService, …)
├── Observers/             # Model observers (DailyTask, Invoice, ProjectStep, TaxReport)
├── Jobs/                  # UploadToGoogleDrive
├── Events/                # ChatMessageSent (broadcast over Reverb)
├── Neuron/TaxBotAgent.php # NeuronAI tax assistant agent
├── Http/Controllers/Api/  # REST API v1 (read-only: users, projects, clients, activities)
├── Http/Middleware/       # Including RedirectToProperPanelMiddleware
├── Providers/Filament/    # AdminPanelProvider + ClientPanelProvider
└── Traits/Trackable.php   # logActivity() helper writing to user_activities table
database/migrations/       # 90+ migrations; cumulative schema evolution
resources/views/
├── filament/              # Filament page/component blade views
└── livewire/              # Livewire component blade views (mirrors app/Livewire/)
routes/
├── web.php                # Almost empty — Filament owns the routes
├── api.php                # /api/v1/{users,projects,clients,activities}
└── channels.php           # Broadcasting channels
```

## Domain models (the important ones)

- **User** — staff; uses `FilamentUserHelpers`, `CausesActivity`, `UserActivityTrait`. `canAccessPanel()` currently returns `true` for everyone — actual gating is by role (`client` role → client panel).
- **Client** — central entity. Belongs to a `Pic`, `AccountRepresentative`, `ClientGroup`. Has many `projects`, `taxreports`, `applications` (credentials per app), `contacts`, `affiliates`, `employees`, `clientDocuments`.
- **Project** — belongs to Client. Statuses: `draft`, `analysis`, `in_progress`, `review`, `completed`, `completed (Not Payed Yet)`, `canceled`. Status changes auto-sync linked `DailyTask` rows ([Project.php:46-60](app/Models/Project.php#L46-L60)).
- **ProjectStep / Task / RequiredDocument / SubmittedDocument** — project workflow & document approval chain.
- **TaxReport** — monthly per-client tax filing. Children: `Invoice` (faktur), `IncomeTax` (PPh), `Bupot`, `TaxCompensation`, `TaxCalculationSummary`.
- **DailyTask / DailyTaskAssignment / DailyTaskSubTask** — daily task tracker (kanban + list views).
- **Sop / SopStep / SopTask / SopRequiredDocument / SopLegalDocument** — SOP templates.
- **ChatThread / ChatMessage / ChatParticipant** — in-app chat (broadcast via Reverb).
- **UserActivity** — custom activity log written through the `Trackable` trait (separate from Spatie's `activity_log` table).

## Conventions & patterns

### Filament resources
- Navigation groups are fixed in [AdminPanelProvider.php:57-65](app/Providers/Filament/AdminPanelProvider.php#L57-L65): `Project Management`, `Tax Management`, `Tugas Harian` (menus currently hidden), `Client Management`, `Penyuratan` (letters module), `Standard Operating Procedures`, `Master Data`, `Administration`. Pick one — don't invent new groups.
- Access control uses **Filament Access Management** permissions, e.g. `auth()->user()->can('clients.*')`. Both `shouldRegisterNavigation()` and `canAccess()` should be set on each resource.
- Primary color is **Cyan** (overridden from Amber later in the provider).

### Livewire ↔ Blade naming
The folder split between `Client/Panel/` (client-facing) and `Client/Management/` (admin tabs inside the client detail page) is load-bearing — don't merge them. Livewire reference is kebab-case of the namespace, e.g. `App\Livewire\Client\Panel\OverviewTab` → `@livewire('client.panel.overview-tab')`. View path mirrors the namespace under `resources/views/livewire/`. Full mapping in [FOLDER_STRUCTURE.md](FOLDER_STRUCTURE.md).

### Activity logging
Two parallel systems coexist:
1. **Spatie Activitylog** on User, Project, etc. via `LogsActivity` trait → `activity_log` table.
2. **Custom `Trackable` trait** ([app/Traits/Trackable.php](app/Traits/Trackable.php)) → `user_activities` table, auto-attaches `client_id` / `project_id`. Use `$model->logActivity('action', 'description in Bahasa Indonesia')`. Descriptions automatically get ` oleh {userName}` appended.

When adding a tracked action, prefer the `Trackable` pattern — descriptions are user-visible in the admin activity feed and should be in Bahasa Indonesia.

### File storage
- Default disk: `local`. Cloud disk: `google` (Google Drive, folder `Sistem Informasi Digital/Project-Management`).
- Async uploads go through [UploadToGoogleDrive.php](app/Jobs/UploadToGoogleDrive.php) on the `database` queue.
- User avatars live on the `public` disk; `User::setAvatarPathAttribute()` auto-populates `avatar_url` with `storage/` prefix.

### Real-time / broadcasting
- Driver: **Reverb** (`BROADCAST_DRIVER=reverb`, dev server at `localhost:8080`).
- Events implement `ShouldBroadcast`; see [ChatMessageSent.php](app/Events/ChatMessageSent.php).
- Front-end uses `laravel-echo` + `pusher-js` (Reverb is Pusher-protocol compatible).

### Queues
`QUEUE_CONNECTION=database`. The `composer dev` script runs `queue:listen` automatically.

## Running the app

```bash
# All-in-one dev (server + queue + logs + vite)
composer dev

# Or individually
php artisan serve
php artisan queue:listen --tries=1
php artisan pail            # live log tail
npm run dev                 # vite
php artisan reverb:start    # websocket server (if needed)
```

Migrations: `php artisan migrate` (90+ files, cumulative — don't squash without coordinating).

Testing: PHPUnit 10 (`./vendor/bin/phpunit`). Tests live in `tests/` (Feature + Unit) but coverage is minimal.

## API

REST v1 under `/api/v1/` — currently **read-only** (no auth middleware on the group). Endpoints: `users`, `projects`, `clients`, `activities`. See [routes/api.php](routes/api.php).

## When making changes — checklist

1. **Match the panel.** Admin features go under `app/Filament/Resources` or `app/Filament/Pages`. Client features go under `app/Filament/Client/Pages` and `app/Livewire/Client/Panel/`. Don't cross the boundary.
2. **Permissions.** New resources need `shouldRegisterNavigation()` + `canAccess()` checks via `auth()->user()->can(...)`.
3. **Migrations are append-only.** Add a new migration file; don't edit historical ones.
4. **Indonesian for user-visible strings.** Labels, notifications, activity descriptions — Bahasa Indonesia. Code identifiers, class names, comments — English.
5. **Reuse the `Trackable` trait** for activity logging on new domain models when an audit trail matters.
6. **Run `php artisan filament:upgrade`** after composer changes (already wired into `post-autoload-dump`).
7. **Don't commit `.env`** — it currently contains live API keys for Gemini, OpenAI, Google Drive, Discord webhooks, and Pusher/Reverb.

## Known quirks

- The repo directory is named `coal-management` but the app itself is `Kisantra-ProjectManagement` (per `APP_NAME`). There is no coal-mining domain.
- `User::canAccessPanel()` returns `true` unconditionally — panel separation is enforced by the role check in `RedirectToProperPanelMiddleware`, not by Filament's contract.
- `FOLDER_STRUCTURE.md` is the canonical layout doc and is more detailed than this file for the Filament/Livewire tree.
- Two activity-log systems run in parallel (Spatie + custom `UserActivity`). When debugging "why isn't this showing up", check both tables.
