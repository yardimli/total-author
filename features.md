# Writer’s Desk — current features

Inventory of implemented features, 11 September 2026. This describes the current app rather than the earlier roadmap.

## Writing and book projects
- Register, sign in, sign out, reset a forgotten password, and manage your account.
- Create a book with a title; keep multiple books in your library with word counts.
- Archive, filter archived books, delete, and recover deleted books.
- Set a synopsis, genre, point of view, tense, and style notes for AI context.
- Write directly in a ProseMirror manuscript editor with serif typography.
- Bold, italics, chapter and scene headings, scene separators, undo and redo.
- Continuous vertical scrolling with dotted page boundaries, page margins, a bordered sheet, and a subtle shadow.
- Chapter outline, live word counts, automatic saving, local draft recovery, and protection against overwriting newer work.
- Light, Dark, and warm Paper themes; persistent appearance preferences.
- Side panels open alongside the manuscript and chat.

## A conversation about your story
- Talk back and forth with an AI writing companion beside the manuscript.
- Brainstorm, discuss characters, develop scenes, and request revisions in ordinary language.
- Choose how much past conversation to include: all, 20, 10, 5, 1, or zero exchanges.
- Reference codex entries with searchable @ mentions; mentions are optional.
- Delete individual chat messages from the visible conversation and future history context.
- Regular chat includes the full book and codex, highlighting roughly 1,000 words on either side of the cursor as a flexible focus.
- Request changes across the whole book, a scene, or other passages. The cursor focus does not prevent broader requested changes.
- Select manuscript text to restrict editing to that exact range. The request includes roughly 500 words of surrounding context per side and the full codex, without the remaining manuscript.
- Surrounding context expands to sentence boundaries. Selected replacements preserve text and formatting outside the selected range.
- Requests over 30,000 words trigger an expense warning before paid calls. Cancel, send anyway, or suppress warnings for one hour.

## Review and revision history
- AI suggestions never apply automatically: inspect a diff before approving changes.
- New proposals open immediately; accept individual changes or reject the proposal.
- Return to View Changes beneath the relevant assistant reply, including after approval or rejection.
- Compare a saved revision against the current document in a Git-style diff with additions/removals and total-change counts.
- Inspect manuscript and codex differences and restore a previous revision.
- Stale revisions and stale proposals cannot silently overwrite newer work.

## Your book’s codex
- Create, edit, and delete entries for People, Places, Items, Organizations, Events, Lore, or custom types.
- Organize entries into collapsible groups with sticky headings; hide empty groups.
- Edit names, descriptions, and comma-separated aliases such as nicknames and titles.
- Recognize codex names and aliases in the manuscript; click them to open the entry.
- Ask the AI in chat to create or update codex entries and aliases, with approval before saving.
- Scan an existing manuscript for people, places, and things; review extracted entries before adding them.
- Autosave codex drafts and recover local unsaved work.

## Names and places
- Open the name helper from the current codex entry.
- Search local country datasets by letters, country, and gender.
- Generate ten random name combinations without spending AI credits.
- Select first-name and last-name countries independently; dropdowns show full country names.
- Prefill separate first/last name inputs and apply a chosen name to the current entry.
- Leave gender blank to include both genders.
- Ask for ten AI place-name suggestions using an optional country and a guiding prompt through chat.
- Choose personal names before AI-created People entries; manuscript scans can extract names already present.

## Models, spending, and transparency
- Fetch the OpenRouter model catalog on login; use the cache thereafter or refresh explicitly.
- Search models, inspect pricing, favorite models, and retain the selected model across sessions.
- Filter output pricing by minimum and maximum dollars per million tokens; default range is $1–$10.
- Favorites-only mode hides search/price filters. Batch and image-generation models are excluded.
- Supply your own encrypted OpenRouter API key, or use the account’s $1 demo allowance.
- Track AI spending per book and across the account, plus demo balance and pending reservations.
- Check model context and budget limits before calls; reserve spending and reconcile uncertain provider costs.
- View a dedicated book LLM history page with filters, token counts, model, cost, and call status.
- Inspect full sent/returned JSON, pretty-printed side by side, with copy controls.
- AI requests use a lightweight classification call followed by a context-rich execution call; visible chat history excludes raw structured response bodies.

## Import and export
- Import TXT or DOCX with a text preview before replacing the manuscript.
- Export TXT or DOCX from library cards.
- DOCX import is intended for prose, not exact preservation of Word page layout.

## Implementation boundaries
- This is a local Laravel 10 application with Breeze authentication, Blade HTML, Tailwind styling, separate JavaScript, and Vite build/development scripts.
- Provider model context limits and spending limits still apply after choosing Send anyway.
- No real-time coauthoring, image generation, deferred/batch AI jobs, EPUB export, or automatic unreviewed AI edits are currently offered.
- Deployment readiness and outstanding operational checks remain tracked in build-v1.md.

## Branding and public account services
- Configurable Total Author name, logo, and favicon; Paper default.
- Optional Google sign-in through Socialite when configured.
- Optional Mailgun account email; password recovery remains hidden/disabled without both credentials.
- Shared contact forms on public/auth pages, stored in the database without email.
- Generic privacy and terms pages linked from the footer.

- Members who have not configured favorites receive matching starter models from config/default_favorites.php when opening their writing page. Initialization runs once and preserves later removals.
