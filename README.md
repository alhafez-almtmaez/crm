# Orbit CRM

Orbit CRM is a reusable Laravel admin starter built with Inertia SSR + Vue 3 + PrimeVue.

It is designed to be cloned for future projects and already includes:
- Admin authentication at `/admin/login`
- Role/permission authorization with Spatie
- Users CRUD and Roles CRUD
- Global system settings (theme, accent, branding, language, RTL/LTR, date/time/timezone, fonts)
- Persistent UI settings and reusable admin layout/components
- Server-side rendered Inertia pages

## Tech Stack

- Laravel 13 (PHP 8.3+)
- Inertia.js (Laravel + Vue 3)
- Inertia SSR (`vite build --ssr`)
- PrimeVue 4 + PrimeIcons
- Tailwind CSS 4
- Vue I18n 11 (`en`, `ar`)
- Spatie `laravel-permission`

## Core Modules

- `Dashboard`: `/admin/dashboard`
- `Users`: `/admin/users` (full CRUD)
- `Roles`: `/admin/roles` (full CRUD + permission mapping)
- `Settings`: `/admin/settings`

### Settings currently control

- Branding: app name, light logo, dark logo, app icon
- Localization: language (`en`/`ar`), direction (`ltr`/`rtl`)
- Date/time: date format, time format, timezone
- Appearance: light/dark mode, shape density, font family, accent color

These settings are persisted to DB (`system_settings` table, key `admin_ui`) and applied globally.

## Authorization Model

Admin area is protected by `auth:web` and Spatie permissions.

Permissions seeded by default:
- `view admin dashboard`
- `manage users`
- `manage roles`

`admin` role is seeded and synced with all permissions above.

## Project Structure (important parts)

- Backend
  - `app/Http/Controllers/Admin/*`
  - `app/Http/Requests/Admin/*`
  - `app/Services/Admin/*`
  - `app/Services/System/*`
  - `app/Models/SystemSetting.php`
- Frontend
  - `resources/js/Pages/Admin/*`
  - `resources/js/components/admin/*`
  - `resources/js/components/form/*`
  - `resources/js/composables/*`
  - `resources/js/i18n/index.js`
  - `resources/js/locales/en.json`
  - `resources/js/locales/ar.json`

## Local Setup

1. Install dependencies

```bash
composer install
npm install
```

2. Environment

```bash
cp .env.example .env
php artisan key:generate
```

3. Configure database in `.env`, then run migrations + seeders

```bash
php artisan migrate
php artisan db:seed
```

4. Link storage (required for uploaded branding assets)

```bash
php artisan storage:link
```

5. Run app

```bash
composer run dev
```

This starts Laravel server + queue + logs + Vite in one command.

## Admin Seed Credentials

Seeder reads these env vars:

- `ADMIN_EMAIL` (default: `admin@unmaro.com`)
- `ADMIN_NAME` (default: `Admin`)
- `ADMIN_PASSWORD` (default: `UNmaro@yolo`)

You can override them in `.env` before `php artisan db:seed`.

## Build

Client + SSR build:

```bash
npm run build
```

Scripts:
- `npm run dev`
- `npm run build`
- `npm run build:client`
- `npm run dev:ssr`

## Storage / Media Best Practice

Brand images are stored using Laravel's default filesystem disk.

- Current upload path: `branding/*`
- URL generation uses `Storage::url(...)`
- If you switch filesystem in `.env` (for example to S3), behavior remains consistent without changing upload code.

## i18n Notes

- Locale files: `resources/js/locales/en.json`, `resources/js/locales/ar.json`
- i18n bootstrap: `resources/js/i18n/index.js`
- Language is initialized from shared system settings and updates live when changed from Settings.

## Reusability Guidelines

When cloning for a new project:

1. Change app identity in settings (name/logos/icon).
2. Add new modules under `/admin/*` with:
   - resource routes
   - thin controller + request validation + service layer
   - permission names in Spatie and seeders
3. Add translation keys in both `en.json` and `ar.json`.
4. Reuse existing admin layout, datatable, forms, toast, confirm popup, and system settings composables.

## Security Notes

- Do not keep default admin password in production.
- Set strong `APP_KEY`, secure DB credentials, and proper filesystem config.
- Ensure production uses HTTPS and secure cookies.
