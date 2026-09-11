# Build v1 tracker

Updated: 2026-09-11. Status: initial implementation complete; local verification passed. Deployment checks are listed below.

Legend: `[x]` completed and checked, `[-]` in progress, `[ ]` remaining.

## Approved implementation choices

- Laravel 10, Breeze, ordinary Blade HTML/includes, separate JavaScript, Tailwind; no Blade x-components or Alpine directives.
- ProseMirror continuous manuscript, dynamic visual pages, chapters/scenes, clickable codex references.
- Mandatory diff approval for every AI change; history defaults to All with 0/1/5/10/20 exchange options.
- Account-persistent model selection/favorites; catalog refresh once per login plus explicit refresh.
- TXT and DOCX import/export for v1; plain prose, headings, emphasis, scene breaks as the initial editor schema.
- Book-specific codex/custom types; freeform descriptions; manual existing names allowed. Random names default to 10 unweighted combinations.
- $1 lifetime demo allowance per account. Classifier uses the selected model initially, separately configurable later.
- Warm paper theme and whole-book page scrubber.

## Work stories

- [x] B01 Review approved scope and inspect workspace/runtime/name data.
- [x] B02 Scaffold Laravel 10 and Breeze; preserve existing database environment settings.
- [x] B03 Database models, ownership boundaries, books/library/settings and metadata.
- [x] B04 Serif themed authentication, responsive workspace, persistent themes.
- [x] B05 ProseMirror editing, pages/navigation/scrubber, chapters/scenes/word counts.
- [x] B06 Autosave, local recovery, revisions, restore and optimistic concurrency.
- [x] B07 Codex CRUD/types/aliases/search and clickable disambiguated references.
- [x] B08 Country name mapping and dataset search/random generator.
- [x] B09 TXT/DOCX import preview and export.
- [x] B10 OpenRouter catalog, prices/search/favorites, login refresh and persisted selection.
- [x] B11 Encrypted personal key settings; book/account usage and atomic demo budget enforcement.
- [x] B12 Chat/history scope/mentions, two-stage classification and execution, structured validation.
- [x] B13 Mandatory diff review, batch accept/reject, stale-proposal protection and undo.
- [x] B14 Person-name prerequisites, place-name suggestions and document-to-codex scans.
- [x] B15 Functional/security tests, production asset build and browser verification.
- [x] B16 Setup/run documentation and final limitations report.

## New stories and implementation findings

- Laravel 10 requires PHP 8.1+, available locally (8.1.13). Its official security support ended February 4, 2025. Retaining the explicitly approved version; record dependency audit results and deployment limitations.
- No application scaffold existed. Name datasets and `.env` are present. Database secrets must not be printed or replaced.
- Git status has an ownership mismatch in this sandbox; no global Git trust settings changed.
- Pagination must be implemented as a ProseMirror extension; it is not assumed to be provided by the editor core.

## Verification log

- Workspace and name samples inspected; PHP, Composer, Node/npm are available.

## Activity

- Created this tracker before application implementation. All Section 1.12 decisions are approved; concrete defaults above resolve remaining format/list/schema choices.

### Implementation checkpoint

- Laravel/Breeze installed, schema migrated against the configured blank database, and server running at http://127.0.0.1:8023 (requested port).
- Implemented library, auth views, ProseMirror workspace, codex/name tools, import/export, model settings, chat routing and diff approval. These remain in progress until functional/browser tests verify behavior.
- `npm run build` passed. `npm run dev` is configured through Vite. npm dependency audit currently reports zero vulnerabilities.
- Added a project-local npm cache because the default Windows profile cache is not writable in the sandbox.
- New verification stories: inspect document schema validation, revision/alias restoration, concurrent saves versus AI proposals, uncertain-cost reconciliation, and long-manuscript pagination.

### Verification checkpoint

- 38 PHP tests / 126 assertions pass, including auth, isolated ownership, save conflicts, exact whitespace preservation, codex revision IDs, country datasets, encrypted keys, model refresh cadence, both AI stages, zero history, approval-only writes, demo reservation accounting, and idempotent cost reconciliation.
- Browser verified: login, create/open book, manuscript autosave, create codex with aliases, click manuscript name to reopen its entry, and reflow a 30-paragraph manuscript over six pages.
- Fixed browser initialization, editor scrolling, inline formatting whitespace preservation, and page-divider labels during verification.
- Composer audit reports Laravel 10 signed-URL and email-rule advisories. Added raw email-control rejection before trimming; framework remains unsupported and audit is not clean.
- Automatic approval review rejected removing unused Breeze view/component directories. They remain unreferenced; all active application/auth views use ordinary HTML Blade layouts/includes.

### Completed behavior checks

- Browser verified selective diff acceptance, persistence of selected model/favorite after logout/login, cached live OpenRouter catalog with prices, zero-history selection, and independent US-first/BR-last random name generation with female filtering.
- Codex edits now autosave and preserve local drafts. Place-name suggestions have selectable Use-name actions and person names can open a new codex entry directly.
- Added a concurrency guard against refreshing the server revision beneath an unsaved local manuscript, plus idempotent uncertain-request retries.
- AI tests use deterministic fake provider responses; live paid generation is intentionally not claimed as verified.
- Verified both Vite modes: production build and running development server. APP_URL now matches requested port 8023; database connection values remain unchanged.

- New story B17: use authenticated server download responses for TXT/DOCX exports; browser blob downloads did not emit a download event reliably in the embedded browser. Implemented; verifying response contents and browser downloads.

## Final local verification

- [x] B17 Authenticated server TXT/DOCX downloads; correct text/Unicode/emphasis and ownership checked in tests; DOCX browser download confirmed.
- [x] B18 Codex autosave survives reload; manuscript recovery retains the original ProseMirror formatting when the preview is unchanged.
- [x] B19 Sidebar/data refresh cannot silently advance the base revision under unsaved work; book settings drafts are not reset by background refresh.
- [x] `php artisan test`: 44 passed, 156 assertions, isolated SQLite memory database.
- [x] `php vendor/bin/pint --test app database routes tests`: 76 files pass.
- [x] `npm run build`: production build succeeds. DOCX importer is lazy-loaded; its bundled vendor chunk produces a size warning but does not block the build.
- [x] `npm run dev`: verified Vite startup and live asset updates; stopped after verification so port 8023 now uses built production assets.
- [x] `npm audit`: zero known vulnerabilities in the installed Node dependency set.
- [x] Browser: login/create book, manuscript autosave, six-page reflow, clickable codex aliases, codex autosave/reload, chapter outline, DOCX import preview/application, DOCX download, model favorites/selection after logout/login, and zero-history dropdown.
- [x] Narrow-screen layout checked at 390px: no horizontal overflow; editor and chat stack vertically. Dark mode and persistent appearance verified.
- [x] PHP server remains running at http://127.0.0.1:8023. Existing database connection settings preserved; APP_URL matches 8023.

## Remaining deployment checks / known limitations

- [ ] Live paid AI smoke test with a configured OpenRouter key. Routing, JSON validation, suggestions, scans, approvals and budgets have deterministic HTTP-fake coverage; no paid generation was used during testing.
- [ ] Real password-reset email delivery with the intended SMTP service. Auth/reset logic passes automated tests.
- [ ] Before public deployment, resolve Laravel 10's unsupported-framework/security-advisory status. The requested version remains installed; the raw-email-control mitigation does not make Composer audit clean.
- [ ] Production-scale long-novel profiling and device/browser coverage beyond the local six-page reflow test. Current pagination uses transient ProseMirror decorations and conservative line measurement; oversized AI contexts are rejected explicitly.
- [ ] Configure the deployment scheduler for cost reconciliation. Unknown costs without a provider generation ID stay reserved for operator investigation.
- Unused Breeze component/view scaffolding remains because automatic approval review rejected its removal. These files are not referenced by active application/auth routes; active views use HTML Blade includes and separate JavaScript.
- DOCX import is a plain-prose import, not preservation of Word's exact layout. AI-replaced paragraph ranges become plain paragraphs; surrounding ProseMirror blocks remain intact.

## Run and review

- App: http://127.0.0.1:8023
- Setup, credentials for the local-only test account, asset commands and operational notes: README.md.
- No commits, pushes, or public deployment were performed.

### Model picker update

- [x] B20 Favorites only is unchecked by default. Added adjacent minimum/maximum output-price inputs, default $1–$10 per million output tokens, with live filtering and range validation.
- [x] B21 Hide batch models (case-insensitive name/ID match), image-generation models including mixed text/image output, and other models without text output. The server also rejects batch/image models before reserving or spending credits.
- Production assets rebuilt with `npm run build`; 45 PHP tests / 164 assertions pass after the change.

### Pricing compatibility and money display

- [x] B22 Fix model rejection caused by optional web-search fees and conditional pricing overrides. Text requests disable search plugins; reservations cover maximum advertised tier rates, fixed request fees, cache and reasoning allowances. Unknown or unbounded fees still fail safely before payment.
- [x] B23 Round displayed money upward to two decimal places in book/account usage, account settings, and model price labels. Keep full precision for stored costs and budget checks.
- [x] Regression coverage: tiered models complete both AI stages; provider price ceilings account for tiers; actual fractional-cent spending is preserved; expensive requests are blocked before payment; exact cents and fractional cents display correctly.
- [x] Verification: 48 PHP tests / 179 assertions and the JavaScript money test pass. `npm run build` succeeds (existing DOCX vendor chunk size warning). App responds with HTTP 200 at http://127.0.0.1:8023.

### Compact workspace and viewport layout

- [x] B24 Compact model picker with an overlaid menu and inline output-price controls. Favorites mode hides and bypasses search/price filters, showing all saved favorites.
- [x] B25 Move conversation-history selection below the chat composer. Compact chat heading and spacing.
- [x] B26 Fit the workspace to the dynamic viewport without a document/outer scrollbar. Manuscript pages reflow to available height; previous/next and the bottom navigator switch pages immediately. Chat retains its own scrolling area.
- [x] B27 Narrow screens use manuscript/companion tabs to keep both panels accessible without vertically stacking an overflowing workspace.
- [x] Verification: isolated browser layout at 1440×900 and 390×844 has no outer overflow and keeps navigation at the viewport bottom. Synthetic long manuscript produces 21 pages; next-page and final-page access checked. 48 PHP tests / 179 assertions pass; production assets rebuilt.
- Authenticated visual retest was limited by automatic approval review rejecting the test-account sign-in. Layout/editor checks used isolated markup and synthetic text without accessing an account.

### Library file controls and sliding left panels

- [x] B28 Reduce the site header to 50px and remove the manuscript eyebrow from the book bar.
- [x] B29 Move Import, TXT/DOCX selection, and Export to each non-deleted dashboard book card. Import retains text preview, file validation, revision conflict protection, and the existing server revision snapshot.
- [x] B30 Move side panels into a left grid column. Opening/closing animates the panel and reallocates manuscript width while keeping chat visible on desktop. Closed panels are inert; reduced-motion preferences disable animation.
- [x] B31 Close the model dropdown on outside clicks and Escape.
- [x] Verification: 48 PHP tests / 179 assertions pass; production assets rebuilt. Isolated browser geometry confirms a 50px header, no panel overlap/outer overflow, unchanged chat width, and restored manuscript width after closing. Card import exercised with synthetic TXT and a mocked API, verifying document and revision payload.

### Appearance and navigation icons

- [x] B32 Replace appearance dropdown with labeled sun, moon, and paper SVG buttons, selected-state styling, and persistent theme selection. Darken Paper to a warm parchment palette distinctly separate from Light.
- [x] B33 Replace My Library and Account text links with accessible icon links. Move the CSRF-protected Sign Out form from the header to the Account page.
- [x] Verification: all three theme buttons and Paper persistence checked on the public login page; 390px viewport has no horizontal overflow and retains the 50px header. 48 PHP tests / 179 assertions pass; production assets rebuilt.

- [x] B34 Refine appearance control to a single current-mode icon opening a three-mode dropdown. Selection, outside clicks, and Escape close the menu; selection remains persistent.
- [x] B35 Use 8px scrollbars (approximately half native desktop width), with parchment track/thumb colors for Paper and matching Light/Dark colors. Firefox uses its native thin scrollbar option.
- [x] Public-page browser check confirms selection updates the current icon, selection/outside click closes the dropdown, hidden choices stay hidden, and scrollbar width is 8px. Production assets rebuilt successfully.

### Dashboard book management

- [x] B36 Remove archive/delete controls from book details and place them on dashboard cards. Archived cards offer Unarchive; delete remains recoverable and uses the existing confirmation.
- [x] B37 Add dashboard filters for active, archived, and recently deleted books. Default to active; all filters remain scoped to the signed-in account.
- [x] Verify archive/unarchive, filtered visibility, account isolation, delete and recovery: 49 PHP tests / 200 assertions pass. Production assets rebuilt.

### Codex list, entry editing, and dialogs

- [x] B38 Replace codex search/type filters with entry groups by type; omit empty groups. Separate the list and scan controls from new/edit forms, which begin with Name.
- [x] B39 Move Names & Places to a modal opened from the link beneath Name. Applying a chosen name preserves the current entry's other fields.
- [x] B40 Move custom-type creation to a dialog linked beneath Type; save refreshes and selects the new type automatically. Remove type-management clutter from the list.
- [x] B41 Standardize modal dismissal: no backdrop/light dismissal; allow explicit corner X, Escape, or submission. Import dialogs now use corner X controls.
- [x] Fixture browser checks pass for populated groups only, list/edit separation, automatic custom-type selection, outside-click persistence, X and Escape dismissal. 49 PHP tests / 200 assertions pass; production assets rebuilt.

### Revision change details

- [x] B42 Clicking a revision opens a Git-style unified diff dialog against the current version, with old/new line numbers, context, additions, and removals. Include manuscript formatting, book details, and codex changes.
- [x] B43 Show total changed lines and per-section counts; define replacement as one removed line plus one added line. Keep Restore as a separate explicit action within the dialog.
- [x] B44 Load revision snapshots on demand through an owner/book-scoped endpoint. Save pending edits before comparison; render diff content as text, and bound expensive comparisons.
- [x] Verification: 50 PHP tests / 204 assertions pass, including revision snapshot ownership and book isolation. Two JavaScript diff tests pass for counts, identical content, whitespace/formatting, and aliases. Production assets rebuilt.

### Current-entry name selection

- [x] B45 Replace the bulk chosen-names textarea with first/last inputs for the current codex entry. Search fills the matching input; random results provide both name components without splitting compound names. Apply preserves the other entry fields.
- [x] B46 Widen Names & Places and arrange desktop search controls in one row, with responsive wrapping. Search respects both country selections. Remove the place-country heading and move suggestion guidance into the textarea hint.
- [x] B47 Remove obsolete bulk-name chat references; current People-entry names can still accompany AI requests. Place suggestions can update the current Places entry without resetting its details.
- [x] Production assets rebuilt; 50 PHP tests / 204 assertions pass.

### Book LLM log and chat deletion

- [x] B48 Add LLM Log beside Revisions. List this book's calls with model, stage, time, status, and cost; paginate results and open individual calls in a dialog with side-by-side read-only request/response textareas.
- [x] B49 Persist full outbound JSON bodies and complete raw provider responses, HTTP status, and failure summaries for future calls. Authentication headers/API keys are not logged. Older calls clearly show when payloads were not recorded.
- [x] B50 Add Delete to user/assistant chat messages. Soft deletion removes them from visible chat and future history while retaining duplicate-request protection and billing logs. Endpoints enforce book ownership and message/call membership.
- [x] Applied database migration to the configured database. Production assets rebuilt. 52 PHP tests / 221 assertions pass, including payload recording, log access isolation, message deletion, and deleted-request retry protection.

### Dedicated LLM log pages

- [x] B51 Replace log dialogs with dedicated book log and call-detail pages. List call IDs and explicit request/response availability so older unrecorded calls are distinguishable.
- [x] B52 Render payloads directly from stored values on the server and pretty-print both JSON textareas, including JSON stored as an encoded string. Preserve non-JSON error responses as text.
- [x] Verified that the newest stored calls contain payloads while earlier calls predate payload recording. Regression coverage confirms populated payloads render as formatted JSON and both pages enforce ownership. 52 PHP tests / 230 assertions pass; production assets rebuilt.

### History layout and dashboard counts

- [x] B53 Restyle book LLM history using the supplied visual reference: dark introduction, summary metrics, action/status/key-source filters, spacious call cards, token/cost metrics, metadata, and full-payload links. Retain dedicated pages, serif UI, and theme support.
- [x] B54 Dark, wrapped, pretty-printed JSON panels with Copy JSON controls on call details.
- [x] B55 Persist reported prompt/completion/total tokens and backfill from recorded responses. Show unavailable counts honestly and indicate partial aggregate coverage. Summary metrics follow active filters.
- [x] B56 Display current manuscript word count on dashboard cards, using the same whitespace-separated definition as the editor.
- [x] Migration applied and production assets rebuilt. 52 PHP tests / 238 assertions pass, including token recording, filtered summaries, and dashboard word count.

### Full-height codex browser

- [x] B57 Expand the codex list through the available sidebar height and keep Scan fixed at the bottom. Remove the scan explanatory text.
- [x] B58 Make type groups collapsible with sticky headings; retain collapse choices and scroll position across list refreshes during the session.
- [x] Isolated browser check verifies available list height, scan visibility at the bottom, sticky heading alignment during scrolling, and group collapse. Production assets rebuilt.

### Immediate reviews and continuous editor

- [x] B59 Automatically open newly received AI proposals. Keep pending, approved, and rejected diff links in chat; historical review shows applied selections read-only and cannot apply twice.
- [x] B60 Give AI review content its own visible vertical scrollbar with constrained height, while keeping the heading and approval controls outside the scrolling region.
- [x] B61 Remove automatic ProseMirror pagination, page breaks, and page navigation. Use a continuous bordered manuscript sheet with contrasting theme-aware background, light shadow, and a regular vertical editor scrollbar.
- [x] Browser fixture verifies automatic opening, all 50 changes reachable at the scroll end, approval controls within viewport, historical selections/read-only behavior, and no generated page breaks. 52 PHP tests / 241 assertions pass; production assets rebuilt.

- [x] B62 Attach each proposal to its originating assistant message and render Review/View Changes immediately beneath that reply. Backfill unambiguous historical associations; deleted or unidentifiable replies retain a clearly labeled changes item in chronological position.
- [x] Migration applied; proposal/message association regression passes. Production assets rebuilt; 52 PHP tests / 242 assertions pass.

- [x] B63 Restore visual page-boundary markers using the original viewport-sized boundary calculation, with simple dotted lines and spacing above/below. Keep continuous scrolling without page navigation. Markers reflow on typing and resizing and use each theme's muted color.
- [x] Browser fixture confirms 17 markers on a long manuscript, unchanged document JSON, regular scrolling, and distinct dotted-line colors for Paper/Light/Dark. Production assets rebuilt.

- [x] B64 Extend dotted page markers through both page margins to the sheet edges, using shared responsive padding values for desktop/mobile. Retain spacing and theme colors; production assets rebuilt.

### Selection-focused AI editing

- [x] B65 Capture selected manuscript text and exact block/UTF-16 offsets. Show selection-only scope above chat, retain it while entering a prompt, and provide an explicit scope-clear button.
- [x] B66 Send selection-specific instructions and enforce a single selection replacement on the server. Reject whole-paragraph/codex operations outside this scope; reject stale revisions/text before payment.
- [x] B67 Review only the selected replacement and apply it without modifying surrounding text or inline formatting. Support partial paragraphs, multi-paragraph selections, Unicode, and whitespace preservation.
- [x] Browser fixture verifies exact offsets and scope persistence when moving to chat. 56 PHP tests / 255 assertions pass, including formatting preservation, multi-paragraph edits, out-of-scope rejection, and stale-selection protection. Production assets rebuilt.


### Cursor focus and bounded selection context

- [x] B68 Send the current ProseMirror cursor with the saved revision. Regular chat receives the full book and codex plus roughly 1,000 words on each side of the cursor as a focus guide, explicitly allowing requested edits elsewhere.
- [x] B69 Selection edits receive the selection, roughly 500 surrounding words per side, and the complete codex; omit remaining manuscript blocks from execution and budget estimates. Keep server-enforced selection-only replacements. Existing chat-history preferences still apply.
- [x] B70 Expand context windows to complete sentence boundaries using ICU, preserve Unicode/UTF-16 cursor offsets, and reject stale cursor revisions before paid calls. Declare the PHP intl extension dependency.
- [x] Verified 59 PHP tests / 284 assertions and 3 JavaScript tests; production assets rebuilt. Server on port 8023 responds successfully.

- [x] B71 Warn before either paid LLM call when assembled prompt context exceeds 30,000 words. Offer Cancel or Send anyway, suggest selection editing, and optionally suppress warnings for the account for one hour. Closing with X/Escape cancels; backdrop clicks do not dismiss. No chat message or usage reservation is created until confirmed. Existing budget/context limits still apply.
- [x] Verified warning, forced retry, hourly suppression and expiry with mocked LLM calls: 60 PHP tests / 299 assertions pass. Production assets rebuilt.

### Public landing page

- [x] B72 Create features.md inventory of the currently implemented app, including context scopes, review flow, codex, names, models, budgets, logs, and import/export boundaries.
- [x] B73 Replace the root login redirect with a public editorial landing page, account-aware calls to action, seven feature sections, and a four-step writing workflow. Use separate Vite CSS/JS and ordinary Blade HTML.
- [x] B74 Capture eight actual PNG screenshots from user 2’s Star Trek meets Star Wars book using the supplied normal login: manuscript, conversation, selection, codex, names, AI diff, saved revision diff, and LLM history. No manuscript/codex edits or paid generation performed.
- [x] B75 Add accessible click-to-enlarge screenshot dialogs and responsive layouts. Verified images load, Escape dismissal, desktop/mobile overflow, 60 PHP tests / 301 assertions, and npm run build.

### Total Author branding and optional account services

- [x] B76 Centralize app name, logo and favicon via APP_NAME / APP_LOGO / APP_FAVICON; configure Total Author, canonical localhost:8023, and Paper default.
- [x] B77 Install Socialite and add conditional Google sign-in with state validation, verified provider identity, session regeneration, and no silent linking of existing email accounts.
- [x] B78 Install Mailgun transport and HTTP client; gate mail delivery, notifications, password recovery links/routes on both Mailgun credentials. Preserve existing database configuration.
- [x] B79 Add contact_messages migration and database-only validated/throttled contact form; shared public/auth footer and generic privacy/terms pages.
- [x] B80 Production build and 66 PHP tests / 347 assertions pass; public desktop/mobile footer, branding, hidden unconfigured options, and Paper default verified. Live external authentication/mail awaits credentials.
- Screenshot refresh was rejected by automatic approval review; existing real screenshot assets retain prior branding. Live views and titles use configured branding.

- [x] B81 Create matching vector logo and favicon: prominent outlined TA serif lettering, a subtle angled lined page, Paper background, sharp corners, and thin dark border. Uses existing APP_LOGO/APP_FAVICON paths; verified rendering and public page references.

- [x] B82 Replace active SVG branding with a built-in image-generated PNG: realistic textured manuscript paper, closely set words, paragraph spacing and indents, prominent TA lettering, sharp border. Export a 64px PNG favicon and update environment/config defaults.

- [x] B83 Export and verify 128x128 and 256x256 PNG logo sizes from the generated master artwork.

- [x] B84 Rewrite README for Total Author's current features, installation, optional integrations, ignored datasets/lockfile, and deployment boundaries. Restart artisan serve on port 8023 from renamed F:/GitHub/total-author folder.

- [x] B85 Add eight real application screenshots to README using raw.githubusercontent.com/yardimli/total-author/main image URLs, with feature captions and descriptive alt text. Verified every referenced image exists locally.

- [x] B86 Add dynamic robots.txt and sitemap.xml for public pages, APP_URL-based canonical URLs, and noindex on auth/workspace views. Keep private book/account routes authenticated and excluded from crawling.

- [x] B87 Extract ten model IDs from the supplied links into config/default_favorites.php. On first eligible writing-page visit, seed favorites from exact supported catalog matches, reuse login refresh/cache, preserve existing choices and deliberate empty lists, and retry later when no catalog is available. Migration applied; 69 PHP tests / 365 assertions pass.

- [x] B88 Default first-time model pickers to Favorites only, persist filter preference, and select the cheapest supported favorite by output price (input price breaks ties). Preserve existing/manual model choices; automatic choices are reconsidered on writing-page visits until an explicit model selection. Migration applied; 70 tests / 374 assertions pass; production assets rebuilt.

- [x] B89 Show welcome on every writing-page entry, including browser back/forward restoration. Name the current model, explain cheaper-model instruction-following limitations and suggest mid-range choices. Closing via button/X/Escape highlights model/chat controls and focuses the chat cursor, switching mobile to chat. Isolated browser fixture verifies focus, highlights, mobile behavior, and repeat opening; production assets rebuilt.
