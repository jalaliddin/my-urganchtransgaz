# Urganchtransgaz Employee Portal — Frontend

Vue 3 + TypeScript + Vuetify SPA (Vite). See the [project root README](../README.md) for the full-stack overview and demo accounts.

## Setup

```bash
cp .env.example .env   # VITE_API_URL defaults to http://localhost:8000/api/v1
npm install
npm run dev             # http://localhost:5173
```

## Build

```bash
npm run build   # type-checks (vue-tsc) then builds to dist/
```

## Structure

```
src/
├── components/common/   Reusable building blocks (AppDataTable, AppPageHeader,
│                        AppStatusChip, AppEmptyState, AppConfirmDialog,
│                        AppAvatar, AppFileUpload, AppLoading, NotificationBell) —
│                        registered globally, see plugins/globalComponents.ts
├── views/                Route-level pages, grouped by feature
├── layouts/              DefaultLayout (authenticated app shell, dark-navy
│                        sidebar) and AuthLayout (login/reset-password)
├── stores/               Pinia stores (auth.ts, notifications.ts)
├── services/             Axios-based API clients — one per resource, built
│                        on resourceService.ts's generic REST factory where
│                        the endpoint is plain CRUD (custom methods otherwise,
│                        e.g. documentService's approve/reject/download)
├── composables/          usePaginatedResource.ts (shared server-side
│                        pagination/search/sort state for list views) and
│                        useAuthenticatedImage.ts (fetches a private-disk
│                        image as a blob, since <img src> can't attach a
│                        Bearer token)
├── types/                TypeScript interfaces mirroring the API's
│                        Resources (models.ts) and envelope shape (api.ts)
├── router/                Vue Router routes + auth guards
├── plugins/              Vuetify theme, vue-i18n, global component registration
└── locales/               uz.json (default), ru.json, en.json
```

No business logic lives here — every view calls a `services/*Service.ts` module, which talks to the Laravel API. Validation errors from the API (`{success:false, errors:{...}}`) are surfaced directly under the relevant form field.

## i18n

Uzbek (`uz`) is the default and most complete locale; Russian and English are fully translated for the current UI strings. Add new keys to all three locale files together. Switch locale at runtime via `plugins/i18n.ts`'s `setLocale()`.

## Session restoration

A stored auth token only proves a session *existed*; on every fresh page load, `main.ts` re-fetches the current user (`/auth/me`) **before** mounting the app, so a direct URL visit or a reload doesn't transiently show a logged-out-looking UI (empty permissions, hidden nav) while still holding a valid token. If that fetch fails (expired/revoked token), the session is cleared and the router's guard sends the user to `/login`.
