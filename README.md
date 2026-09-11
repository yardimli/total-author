# Total Author

![Total Author logo](public/images/logo-128.png)

A literary-fiction workspace built with Laravel 10, Breeze, Blade, Tailwind, and ProseMirror. Write manually, develop ideas with an AI companion, maintain a book-specific codex, and review every proposed AI change before applying it.

The project is developed locally at **http://localhost:8023**. The intended production domain is **total-author.com**; production deployment is not configured by these instructions.

## Features

- Multiple book projects with word counts, archive filters, deletion, and recovery.
- A serif manuscript editor with formatting, chapter/scene headings, continuous scrolling, visual page boundaries, autosave, and local draft recovery.
- Paper (default), Light, and Dark appearances.
- AI chat through OpenRouter, model search, pricing filters, favorites, persistent model selection, and configurable conversation history.
- Whole-book AI context with a flexible focus around the cursor. Selection editing restricts changes to the selected passage and sends only nearby manuscript context plus the codex.
- Mandatory diff approval, selective acceptance, persistent View Changes links, saved revision comparisons, and restore.
- Codex entries, custom types, aliases, clickable manuscript references, @ mentions, and document scanning.
- Local personal-name search/random generation and AI-assisted place-name suggestions.
- TXT/DOCX import previews and exports.
- Per-book/account AI usage, a $1 demo allowance, personal API keys, and full request/response logs.
- Optional Google sign-in and Mailgun email, enabled only when configured.
- Configurable name/logo/favicon, a public landing page, privacy/terms pages, and database-only contact forms.

See [features.md](features.md) for the full inventory, [build-v1.md](build-v1.md) for progress and verification, and [branding-and-integrations.md](branding-and-integrations.md) for integration details. [FEATURE_SPECIFICATION.md](FEATURE_SPECIFICATION.md) records the original scope; the feature inventory describes current behavior.

## Screenshots

Real application captures from the sample book *Star Trek meets Star Wars*. Some captures show the earlier Writer’s Desk branding.

### Manuscript editor

Write beside the AI companion in the Paper-themed manuscript workspace.

![Manuscript editor](https://raw.githubusercontent.com/yardimli/total-author/main/public/images/landing/manuscript.png)

### AI conversation

Develop ideas through chat and revisit the changes attached to each response.

![AI conversation](https://raw.githubusercontent.com/yardimli/total-author/main/public/images/landing/conversation.png)

### Selection editing

Select a passage to keep AI revisions focused on that exact text.

![Selection editing](https://raw.githubusercontent.com/yardimli/total-author/main/public/images/landing/selection.png)

### Book codex

Organize characters, places, and other story details alongside the manuscript.

![Book codex](https://raw.githubusercontent.com/yardimli/total-author/main/public/images/landing/codex.png)

### Names and places

Find personal names and develop place names from the current codex entry.

![Names and places](https://raw.githubusercontent.com/yardimli/total-author/main/public/images/landing/names.png)

### AI change review

Compare original and proposed text before deciding which changes to keep.

![AI change review](https://raw.githubusercontent.com/yardimli/total-author/main/public/images/landing/ai-diff.png)

### Saved revision comparison

Inspect a saved revision against the current manuscript with change counts.

![Saved revision comparison](https://raw.githubusercontent.com/yardimli/total-author/main/public/images/landing/revisions.png)

### LLM history

Review individual AI calls, models, token usage, and recorded costs.

![LLM history](https://raw.githubusercontent.com/yardimli/total-author/main/public/images/landing/llm-log.png)

## Requirements

- PHP 8.1+ compatible with the locked dependencies, Composer, and PHP extensions including PDO for your database, mbstring, intl, XML, curl, and zip.
- Node.js 20.19+ or 22.12+ and npm for Vite. Local verification used PHP 8.1.13 and Node 24.
- A database supported by Laravel, with its PDO extension. Tests additionally require PDO SQLite.
- OpenRouter credentials only if you want to run AI requests.

## Local setup

From the project folder (currently `F:\GitHub\total-author`):

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Copy `.env.example` and generate an application key **only for a new installation**. Keep an existing `.env` and `APP_KEY`: the key is needed to decrypt saved personal API keys.

Configure `DB_*` in `.env` for your database, then run:

```powershell
npm install
php artisan migrate
npm run build
php artisan serve --host=127.0.0.1 --port=8023
```

Open **http://localhost:8023**. Register through the application to create your account. Do not run development seeders on a production installation.

`package-lock.json` is currently ignored by Git, so a fresh clone uses `npm install`. If you choose to track the lockfile, use `npm ci` for reproducible installs thereafter. Composer dependencies are locked in `composer.lock`.

### Development assets

Keep the PHP server running and use a second terminal:

```powershell
npm run dev
```

For built assets, stop Vite and run `npm run build`. If Vite was terminated abruptly, remove its stale `public/hot` file. Built files in `public/build` are ignored by Git and must be generated after cloning and during deployment.

### Local name datasets

`data/names_by_country` is currently excluded from Git. Supply these files separately to enable local personal-name search and generation:

```text
data/names_by_country/US_first_names.json
data/names_by_country/US_last_names.json
```

```json
{
  "country_code": "US",
  "name_type": "first_names",
  "names": [{"name": "Maria", "rank": 1, "gender": "Female"}]
}
```

Last-name files use `"name_type": "last_names"` and entries with `name` and `rank`; no gender is needed. Use corresponding country codes in filenames. The UI displays full country names.

## Configuration

### Branding

```dotenv
APP_NAME="Total Author"
APP_URL=http://localhost:8023
APP_LOGO=/images/logo.png
APP_FAVICON=/images/favicon.png
```

The UI reads the name and image paths from configuration. Logo exports include 128px and 256px PNGs. Existing screenshot assets are static captures and do not change automatically with the branding settings. Paper is the default; explicit appearance preferences are retained.

After editing `.env`, run `php artisan config:clear` locally. Keep the browser hostname consistent with `APP_URL`, especially for OAuth sessions.

### OpenRouter

Set `OPENROUTER_API_KEY` for the shared demo allowance, or enter a personal key on the Account page. Each account has a $1 lifetime demo budget. Personal keys are encrypted in the database.

Both classification and execution calls reserve budget before sending. Uncertain costs remain reserved until reconciled. Displayed money rounds upward to two decimals; stored billing precision is retained. The catalog refreshes once per login and can also be refreshed manually.

Regular chat includes the full book and codex with roughly 1,000 words on each side of the cursor as a focus guide. Selection edits include the selected text, roughly 500 surrounding words per side, and the full codex, without the remaining manuscript. Context windows preserve sentence boundaries. Chosen chat history is included in either mode.

Prompts exceeding 30,000 words trigger a warning before paid calls. Users can cancel, proceed, or suppress warnings for one hour. Proceeding does not override provider context limits or budget checks.

### Google sign-in

```dotenv
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_CALLBACK_URL="${APP_URL}/login/google/callback"
```

Uses Laravel Socialite. Configure the exact callback URL in your Google OAuth web application; locally it is `http://localhost:8023/login/google/callback`. The button and routes stay disabled until configuration is complete. New verified Google identities can create accounts. Existing password accounts are not silently linked by matching email and must use their password.

### Mailgun

```dotenv
MAILGUN_DOMAIN=
MAILGUN_SECRET=
MAIL_MAILER=mailgun
MAIL_FROM_ADDRESS="noreply@total-author.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Uses Symfony Mailgun Mailer and HTTP Client. Set a verified sending domain, API secret, and appropriate sender before enabling delivery. `MAILGUN_ENDPOINT` can select the appropriate provider region.

Without both domain and secret, account email is suppressed and forgot-password links/request routes are disabled. No welcome-email flow is currently implemented. Contact forms save to `contact_messages` and **never send email**, even when Mailgun is configured. There is no contact inbox administration UI yet.

## Verification and maintenance

```powershell
php artisan test
node --test tests/*.test.mjs
npm run build
php artisan writer:reconcile-usage
```

PHP tests use isolated in-memory SQLite and do not reset the development database. Provider tests use fake responses; live Google OAuth, Mailgun delivery, and paid AI behavior require separately configured credentials.

Run Laravel’s scheduler every minute in deployment (`php artisan schedule:run`); usage reconciliation is scheduled every five minutes. Charges without a provider generation ID need operator investigation.

## Before public deployment

- Laravel 10 is retained by project choice but is out of security support. The existing build tracker records unresolved framework advisories. Review `composer audit` and `npm audit` and resolve deployment risks before public launch.
- Serve only `public/`, use HTTPS, set `APP_DEBUG=false`, configure production sessions, and keep `.env`, database backups, and private storage out of Git and the public document root.
- Configure the production database, scheduler, provider callback URL, and mail sender. Back up the database and application key.
- Review the generic privacy/terms text for the operator and deployment, and review public screenshots before publication because they contain actual manuscript/chat content.
- Keep independent manuscript exports. DOCX import extracts prose rather than preserving exact Word layout. AI-rewritten ranges may need formatting reapplied.

This repository does not configure hosting, DNS, or production service accounts.

## Search-engine discovery

`/robots.txt` advertises `/sitemap.xml`, which lists the landing, privacy, and terms pages. Public pages include canonical URLs; authentication and workspace views use noindex. Private book/account routes remain authenticated and are excluded from crawling. Sitemap and canonical URLs use `APP_URL`: set it to `https://total-author.com` on deployment and rebuild the configuration cache. Search engines cannot reach a localhost-only development server. Submit the deployed sitemap to your search-engine webmaster tools when the site is public.

## Languages

Set `APP_LOCALE=en` or `APP_LOCALE=tr` in `.env` to choose the default language (English unless changed). Run `php artisan config:clear` after changing it, or rebuild the configuration cache for deployment. Set `ALLOW_LANGUAGE_CHANGE=yes` to show the header switch and allow saved account/visitor preferences to override the default. With `ALLOW_LANGUAGE_CHANGE=no` (also the default when missing), the switch is hidden, change requests are blocked, and the UI always uses `APP_LOCALE`. Saved preferences are retained for later re-enabling. Member preferences are saved to the account, and guest preferences use a session and cookie. Laravel translations live in lang/en.json and lang/tr.json, with auth, validation, password, and pagination files in each language folder. JavaScript uses the same Laravel catalog through resources/js/i18n.js. Run npm run build after changing interface strings; npm run dev also regenerates the frontend translation key list on startup. Clear Laravel's configuration/view caches when deploying updated translations. Interface language does not translate existing manuscripts, codex content, or chat history.


### Long AI requests

Each OpenRouter completion can run for up to 120 seconds. A chat uses classification plus execution, so PHP permits 260 seconds and the browser waits up to 270 seconds for the whole workflow. Configure deployment proxy/FPM request limits to accommodate that window. Chat concurrency uses OS file locks in storage/framework/cache; workers must share the same lock-capable filesystem. These locks release when a request/process ends, including fatal PHP timeouts; do not delete active lock files. Uncertain provider charges remain reserved for reconciliation.


## Administration

All users default to `is_admin = 0`. Set `users.is_admin` to `1` directly in the database for an administrator. The dashboard then displays an Admin button leading to `/admin/users` (searchable, 25 users per page).

The user list shows join date, book and archive counts, AI call count, settled AI cost, and demo spent/reserved amounts. Deleted books are excluded from book counts; financial values round up to two decimal places.

Admins can use **Log in as user** for non-admin accounts. This switches the current browser session to that user's permissions and data. The header shows the current user's name and **Return to admin**. Impersonation cannot be nested or used to enter another admin account. Logging out ends the session; revoking the original administrator's flag also ends their impersonation session on the next request. Start/return events are recorded in Laravel's log with the two user IDs. Admin privileges are not editable through registration or account forms.
