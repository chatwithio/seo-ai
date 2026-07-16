# ChatWithSEO Frontend Refactor Plan

## Objective

Replace the customer-facing Filament and Blade interface with a modular Inertia React application while preserving the working Laravel backend, tenant ownership, Google integrations, imports, background commands, content generation, and database schema.

The refactor must be incremental. Existing users must continue working while pages are migrated and verified one at a time.

## Current Baseline

- Laravel 13 provides routing, authentication, models, services, commands, jobs, scheduling, and persistence.
- Filament 5.6 and Livewire 4.3 provide the current customer admin interface.
- Custom pages mix Filament components, raw Blade markup, Livewire polling, inline styles, and Tailwind classes.
- Login and registration are custom Blade pages.
- Long-running work is launched through Laravel commands and tracked by `BackgroundTaskManager`.
- User ownership is represented primarily by `user_id` and site ownership.

## Target Architecture

### Backend retained

- Laravel routes, middleware, sessions, CSRF protection, and authentication.
- Eloquent models and tenant-scoped queries.
- Google OAuth and Search Console services.
- Import, grouping, aggregation, content-generation, and agent commands.
- Queue jobs, scheduled tasks, audit logs, and background-task tracking.

### Customer frontend replaced

- Inertia 3 as the Laravel-to-frontend bridge.
- React 19 with TypeScript.
- Tailwind CSS 4.
- shadcn/ui source components.
- Lucide icons through the shadcn/ui convention.
- Vite for development and production builds.
- Laravel controllers return Inertia pages and typed props.

### Filament retained temporarily

- Keep Filament available at an internal-only route during migration.
- Use it for database administration and operational support until the React interface reaches feature parity.
- Remove customer access to Filament only after final acceptance.

## Design System

### Product principles

1. Show the next useful action instead of every available action.
2. Never leave a long-running operation without visible status.
3. Prefer plain SEO language over implementation terminology.
4. Keep tables dense but readable.
5. Use the same component for the same meaning everywhere.
6. Support mobile, light mode, dark mode, keyboard navigation, and reduced motion.

### Color roles

- Primary blue: navigation, links, selected state, and data exploration.
- Amber: primary conversion action and active processing.
- Green: successful connection, completion, and positive state.
- Red: destructive actions and failures only.
- Slate/zinc surfaces: backgrounds, borders, and neutral states.

### Shared tokens

Define tokens once in the React Tailwind theme and CSS variables:

- Colors and semantic status colors.
- Font family and type scale.
- Spacing scale.
- Border radius.
- Shadows.
- Sidebar width.
- Page maximum width.
- Table row height and density.
- Animation duration and reduced-motion behavior.

## Proposed Frontend Structure

```text
resources/js/
├── app.tsx
├── components/
│   ├── app/
│   │   ├── app-sidebar.tsx
│   │   ├── background-job-status.tsx
│   │   ├── page-header.tsx
│   │   └── user-menu.tsx
│   ├── data/
│   │   ├── data-table.tsx
│   │   ├── filter-bar.tsx
│   │   ├── metric-card.tsx
│   │   └── pagination.tsx
│   ├── feedback/
│   │   ├── empty-state.tsx
│   │   ├── error-state.tsx
│   │   ├── loading-state.tsx
│   │   ├── progress-card.tsx
│   │   └── status-badge.tsx
│   └── ui/
│       └── shadcn source components
├── hooks/
│   ├── use-background-jobs.ts
│   ├── use-polling.ts
│   └── use-table-state.ts
├── layouts/
│   ├── app-layout.tsx
│   └── auth-layout.tsx
├── lib/
│   ├── routes.ts
│   ├── formatters.ts
│   └── constants.ts
├── pages/
│   ├── auth/
│   ├── dashboard.tsx
│   ├── google-connections/
│   ├── managed-sites/
│   ├── search-keywords/
│   ├── grouped-keywords/
│   ├── content-plans/
│   ├── articles/
│   ├── active-jobs/
│   ├── activity-log/
│   └── ai-instructions/
└── types/
    ├── models.ts
    ├── page-props.ts
    └── jobs.ts
```

## Backend Presentation Boundary

Controllers must prepare page data and enforce tenant ownership. React components must not reproduce authorization or business rules.

Each page should receive a documented prop contract containing only the data it renders. Shared TypeScript types should mirror these contracts.

Use dedicated request classes for validation and dedicated action/service classes for mutations. Avoid placing Google API calls, command execution, or complex database work directly in controllers.

## Background Job Status Contract

Create a stable current-user endpoint or Inertia partial reload contract:

```json
{
  "jobs": [
    {
      "id": "stable-task-id",
      "type": "keyword_import",
      "name": "Importing keywords",
      "status": "running",
      "statusText": "Importing example.com",
      "progressCurrent": 2,
      "progressTotal": 6,
      "progressPercent": 33,
      "startedAt": "ISO-8601 timestamp",
      "canCancel": true
    }
  ]
}
```

The sidebar status component, Dashboard status, and Active Jobs page must consume this same contract. Polling should pause when the browser tab is hidden and stop when there are no active jobs. WebSockets can be considered after the polling implementation is stable.

## Migration Phases

### Phase 0 - Stabilize the current interface

- [x] Create and register a compiled Filament theme.
- [x] Ensure custom Filament and Livewire Tailwind classes are scanned.
- [x] Remove Tailwind CDN usage from authentication pages.
- [ ] Move remaining inline styles into shared theme utilities.
- [ ] Assign unique navigation icons.
- [ ] Record screenshots of every current page and state.

Exit criteria:

- Current customer workflows remain functional.
- Styles compile through Vite in development and production.
- Existing pages have a documented visual baseline.

### Phase 1 - Install the React foundation

- [ ] Install the official Laravel React/Inertia dependencies.
- [ ] Add React and Inertia Vite configuration.
- [ ] Create the Inertia root template and middleware.
- [ ] Add TypeScript configuration and path aliases.
- [ ] Initialize shadcn/ui.
- [ ] Implement design tokens and light/dark themes.
- [ ] Add linting, formatting, type checking, and frontend tests.

Exit criteria:

- A protected React test page renders for an authenticated user.
- Production assets build successfully.
- Laravel and React tests run in CI.

### Phase 2 - Shared application shell

- [ ] Build the responsive application layout.
- [ ] Build the sidebar using the approved navigation labels.
- [ ] Build the global background-job status component.
- [ ] Build the page header, user menu, notifications, and breadcrumbs.
- [ ] Implement route-aware active navigation.
- [ ] Verify mobile collapse and keyboard operation.

Exit criteria:

- The shell works at 375, 768, 1024, and 1440 pixel widths.
- Light and dark themes meet contrast requirements.
- Global job status is consistent across pages.

### Phase 3 - Authentication

- [ ] Migrate login to React.
- [ ] Migrate registration to React.
- [ ] Add password reset and email verification if required.
- [ ] Preserve current Laravel session authentication.
- [ ] Preserve intended redirect destinations.

Exit criteria:

- Registration, login, logout, failed validation, and session expiry work without Blade page templates.

### Phase 4 - Dashboard and onboarding

- [ ] Build onboarding states for disconnected, connected, syncing, importing, ready, and failed accounts.
- [ ] Add top-performing keyword cards.
- [ ] Add high-impression, low-click opportunity cards.
- [ ] Add working quick actions.
- [ ] Add support contact guidance.

Exit criteria:

- A new user can understand and begin the full workflow from the Dashboard.
- Every asynchronous action exposes progress and completion feedback.

### Phase 5 - Google Connections and Managed Sites

- [ ] Migrate Google Connections.
- [ ] Migrate Managed Sites.
- [ ] Preserve automatic site sync after OAuth callback.
- [ ] Display connection, sync, empty, partial-failure, and failure states.
- [ ] Keep sites scoped to the authenticated user.

Exit criteria:

- Two users can connect access to the same property without transferring ownership.
- Site syncing never blocks the page request.

### Phase 6 - Search Keywords

- [ ] Build a reusable server-driven data table.
- [ ] Implement site, intent, clicks, impressions, CTR, position, and opportunity filters.
- [ ] Preserve sorting, searching, pagination, and URL state.
- [ ] Add per-row Generate Content.
- [ ] Add bulk grouping and bulk content generation.
- [ ] Show import progress and refresh data after completion.

Exit criteria:

- Filters return correct tenant-owned results alone and in combination.
- Imported keywords appear without a manual reload.
- Content generation can be tracked from the same page.

### Phase 7 - Grouped Keywords and Content Plans

- [ ] Migrate Grouped Keywords.
- [ ] Show group purpose, keyword membership, metrics, and primary keyword.
- [ ] Add create-plan and create-article actions.
- [ ] Migrate Content Plans with structured sections.
- [ ] Preserve AI grouping batch limits and progress.

Exit criteria:

- Users can understand what a group contains and take the next action without SEO expertise.

### Phase 8 - Articles

- [ ] Migrate the article list and editor.
- [ ] Add preview and source modes.
- [ ] Preserve metadata, status, review results, and published URL.
- [ ] Add autosave or explicit unsaved-change protection.

Exit criteria:

- Existing drafts can be opened and edited without losing HTML or metadata.

### Phase 9 - Active Jobs, Activity Log, and AI Instructions

- [ ] Migrate Active Jobs using the shared job-status contract.
- [ ] Preserve user-scoped cancellation.
- [ ] Migrate Activity Log with readable filters and error details.
- [ ] Migrate AI Instructions with clear defaults and validation.

Exit criteria:

- Users see only their tasks and activity.
- All running processes expose a meaningful state.

### Phase 10 - Cutover

- [ ] Run page-by-page feature parity review.
- [ ] Run tenant-isolation verification with two users.
- [ ] Run complete Google connection and import acceptance tests.
- [ ] Run responsive and accessibility checks.
- [ ] Measure page load and interaction performance.
- [ ] Move Filament to an internal-only route.
- [ ] Redirect customer routes to React pages.
- [ ] Keep a documented rollback switch for one release cycle.

Exit criteria:

- All customer workflows pass acceptance tests.
- No customer-facing route requires a Filament page.
- Operational staff retain internal administration access.

## Testing Strategy

### Backend

- Feature tests for authentication and tenant-scoped controllers.
- Tests for import launch, job status, cancellation, grouping, and content generation.
- Tests that two users can share the same Search Console property safely.

### Frontend

- TypeScript type checking.
- Component tests for loading, empty, error, ready, and running states.
- Table filter and URL-state tests.
- End-to-end tests for the main onboarding and content workflows.
- Automated accessibility checks.
- Screenshot tests at the approved responsive widths.

## Rollout Rules

1. Do not change database ownership semantics as part of visual migration.
2. Do not replace a working Filament route until the React equivalent passes acceptance tests.
3. Keep old and new routes behind a configuration or feature flag during migration.
4. Migrate one workflow at a time, not individual visual fragments across every page.
5. Record performance and error rates before and after each cutover.

## Definition of Done

- Customer-facing pages are React TypeScript components rendered through Inertia.
- Laravel remains the source of truth for authentication, authorization, validation, and business logic.
- Design changes are made through shared tokens and components rather than page-specific CSS.
- Every long-running operation exposes consistent progress.
- All pages support light mode, dark mode, mobile, keyboard navigation, and reduced motion.
- Tenant isolation and existing SEO workflows pass automated and manual acceptance tests.
- Filament is retained only for internal administration or removed after a separate operational review.
