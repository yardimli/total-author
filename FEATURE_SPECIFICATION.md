# Writer’s Desk — Feature Specification

Status: Initial-release scope and Section 1.12 approved. Implementation and verification are tracked in build-v1.md.

This document defines the initial release, separates future-version features, and ends with the implementation sequence. The initial release includes the previously recommended first-release additions and the subsequent decisions on imports, document scanning, book context, model preferences, ProseMirror, mandatory AI diffs, and selectable chat history. Remaining implementation choices are identified within the initial-release section.

## 1. Initial release

### 1.1 Product and technical foundation

- A writing workspace for literary fiction, organized around book projects.
- Laravel 10 with Laravel Breeze for authentication and Blade views.
- Use ordinary HTML in Blade templates, with separate JavaScript files for AJAX updates and interactions.
- Do not use `x-template` markup. Proposed interpretation: also avoid Blade `<x-...>` component tags and Alpine `x-*` directives in application views; use Blade layouts, sections, and includes instead. Confirm this interpretation before scaffolding the views.
- Tailwind CSS for styling.
- Use ProseMirror as the editor foundation, extended for dynamic pagination, clickable codex references, and manuscript change review.
- Serif fonts throughout the interface, including authentication, navigation, editor, codex, and chat.
- Support light, dark, and paper modes. Paper mode should provide a warm, subdued reading and writing appearance.
- The existing `.env` contains database connection settings, and a blank database has already been created. Preserve those settings when installing the application and preparing migrations.
- Existing name datasets are located in `data/names_by_country`. The US first-name and last-name samples were inspected and match the supplied formats.
- Application implementation and any additional build stories are tracked in build-v1.md.

### 1.2 Accounts and authentication

- Provide sign-in, register/sign-up, and forgot-password pages styled consistently with the writing workspace.
- Register and sign up refer to the same account-creation flow.
- Include the reset-password page reached from the recovery email.
- After signing up, a user can immediately start a new book by entering its title.
- Provide account settings for the user’s OpenRouter API key and account-wide AI usage.
- Support a shared, environment-configured OpenRouter key for demo usage and a user-supplied key for personal usage.
- Proposed default: a new account receives up to USD $1 of shared-key usage across its entire account, rather than $1 per book.

### 1.3 Book projects and workspace

- A user can create a book project by entering a title and begin writing.
- Each book has its own manuscript, codex, chat history, and AI usage total.
- Store editable book-level synopsis, genre, point of view, tense, and style notes. Include these in execution-stage AI context when relevant; keep classification context lightweight.
- The main workspace contains the manuscript editor and an AI chat panel on the right.
- The codex can be opened and edited while writing.
- Provide access to the name generator from the writing/codex workflow.
- Display AI usage for the current book and provide access to the whole-account total.
- Proposed supporting navigation: a book library/dashboard for reopening projects, creating additional books, and switching between them.

### 1.4 Manuscript editor and dynamic pages

- Use a continuous ProseMirror document as the manuscript model. Build pagination and codex linking as extensions around that model; visual page boundaries must not fragment the stored book.
- The editor looks like a page editor, and the user types directly into the displayed pages.
- The underlying document is one continuous book; displayed pages are a layout of that document, not independently stored page records.
- Paginate on the fly as text is typed, removed, or changed.
- Provide previous-page and next-page buttons.
- Provide a whole-book “winder” scroller. Proposed interpretation: a draggable scrubber that moves through the entire manuscript, with a current-page/total-pages indicator. The exact appearance is still to be agreed.
- Reflow content when layout-affecting conditions change, such as available editor width or font metrics.
- Keep text selection, cursor position, copy/paste, paragraph boundaries, and undo/redo usable across page boundaries.
- Page transitions must not drop, duplicate, or reorder manuscript text.
- Distinguish explicit author-created breaks, if supported, from automatic visual page boundaries.
- Highlight names, places, and things that match codex entries using spans associated with the relevant entry.
- Match both canonical names and aliases.
- Codex highlighting must preserve the manuscript’s actual text and must not disrupt typing or pagination.
- Clicking a highlighted reference opens that codex entry. Implement reference spans through ProseMirror-aware decorations or marks so updates preserve selections and editing history.

#### Import an existing story and scan for codex entries

- Allow users to import an existing story into a book. Supported file formats remain to be agreed before implementing the importer.
- Preview imported content and require an explicit choice before replacing an existing manuscript; preserve a recoverable revision.
- Provide a **Scan document to create codex** button for imported stories and manuscripts written in the application.
- Route the button through the book's chat and the same two-stage AI pipeline: classify from lightweight metadata, then analyze manuscript content to propose people, places, items, other entry types, and aliases.
- Extract existing names from the manuscript rather than generating replacement personal names. Names already present satisfy the name-first requirement for extracted person entries.
- Compare extracted entities with existing codex names and aliases to propose updates or new entries without blindly creating duplicates. Flag uncertain identity matches for review.
- Base extracted descriptions on the manuscript and flag uncertainty; do not silently invent unsupported facts.
- Show all proposed entries, descriptions, and aliases in the mandatory diff review before saving. Allow accepting or rejecting individual entries.
- Scanning does not change the manuscript. Account for every scan-related AI call in book/account usage and the demo allowance.
- If a complete scan exceeds model limits, offer a suitable model or explicit staged scanning with cross-section deduplication; do not silently scan only part of the story.

### 1.5 Codex

#### Entries and types

- Each book has a codex for people, places, things, and other supporting material.
- Provide preset entry types. Suggested initial set: People, Places, Items, Organizations, Events, and Lore.
- Users can add custom types.
- Each entry has a name/title, type, descriptive content, and aliases.
- Users can create and edit entries while working in the manuscript.
- Aliases are edited as a comma-separated list and can represent nicknames, titles, shortened names, and alternate forms.
- Both the user and AI can update aliases.
- Proposed internal handling: trim whitespace, remove empty values, and deduplicate aliases while keeping the comma-separated editing interface.

#### AI-assisted entries

- Users can ask the AI to create or edit codex entries through chat.
- Support batch creation, for example: “Add 10 sci-fi items for the book into the codex.”
- Person entries require the name step first. Use the dataset-driven name workflow to select names before asking the AI to create the remaining person details.
- Proposed alternative for people already named by the author: accept a manually entered name as satisfying the name step. Confirm whether this is desired.
- For a batch request for people without names, pause the writing operation to collect/select names, then continue the same chat workflow with those names.
- Place names can be entered manually or suggested by the AI.
- Before requesting place-name suggestions, show an optional country selector and require a guiding prompt.
- Ask the AI for 10 place-name suggestions, then let the user select a name before continuing entry creation.
- All AI requests and responses remain in the chat window, including requests initiated from codex or place-name controls.

### 1.6 Dataset-driven name generator

#### Data source

- Generate personal names from local JSON datasets; do not ask an AI to invent personal names.
- Read first names from `{COUNTRY_CODE}_first_names.json` and surnames from `{COUNTRY_CODE}_last_names.json`.
- Both file types contain `country_code`, `name_type`, and a `names` array.
- First-name records contain `name`, `rank`, and `gender`.
- Last-name records contain `name` and `rank`, with no gender field required.
- Display full country names in every country dropdown, while retaining country codes internally.
- A country-code-to-display-name mapping is needed; the source/mapping will be determined during implementation.
- Preserve accented and non-Latin names correctly.

#### Search mode

- Provide a search box for entering letters, a country selector, and an optional gender selector.
- Search the local names and display matching results.
- Proposed presentation: distinguish first-name and surname results; gender filtering applies only to first names.
- Blank gender includes both male and female first names available in the dataset.
- Proposed search behavior: case-insensitive partial matching, with rank used for ordering.

#### Random-list mode

- Provide separate dropdowns for first-name country and last-name country.
- Provide an optional gender selector; leaving it blank includes both genders.
- Generate a list of combinations using first names from one selected country and surnames from the other selected country.
- Users can select generated names for person codex entries.
- List size and whether rank influences random selection remain configurable decisions.
- Handle missing datasets and empty filter results with clear feedback.

### 1.7 Chat, models, and context references

- Provide a persistent right-side chat dialog for the active book.
- Users can type messages and choose an AI model.
- Fetch the model catalog from OpenRouter rather than maintaining a hard-coded list. Show all catalog models, with any unsupported capability or demo-budget restriction explained in the UI.
- Provide model search, pricing display, and favorites. Offer a favorites view so users do not have to browse the entire catalog each time, with access to the full list when wanted.
- Refresh the OpenRouter catalog once per successful user login, then use the cached catalog throughout that session. Page refreshes, opening the picker, and AI requests must not trigger another automatic catalog fetch.
- Provide an explicit refresh action. If refresh fails, retain the last successful catalog and show its freshness/error state.
- Persist favorite model IDs and the selected model ID in the user's account so they survive page refreshes and logout/login.
- If a saved model becomes unavailable, indicate that and request a replacement selection instead of silently substituting another model.
- Allow users to save their own OpenRouter API key.
- Entering `@` opens a searchable list of codex entries from the active book.
- Selecting an entry creates a reference associated with its stable entry ID, rather than relying only on its display name.
- Codex mentions are optional; users can ask questions or request changes using ordinary text.
- Include all prior user messages and the assistant's visible chat responses by default.
- Add a chat-panel history dropdown with **All**, finite recent-history limits, and **0 — No past history**. Define finite limits in complete user/assistant exchanges so the boundary is clear.
- Apply the selected history scope to both classification and execution. Zero excludes past messages but still includes the current request, explicit mentions, and operation-required book/codex context.
- Limiting history changes what is sent to the model; it does not delete saved chat messages. Proposed finite options: latest 1, 5, 10, or 20 exchanges.
- Do not replay the complete structured edit payloads as ordinary chat history.
- All AI interactions, including classification, generation, place-name suggestions, and follow-up questions, belong to this chat workflow.

### 1.8 Two-stage AI request pipeline

#### Stage 1: classify the request with lightweight context

- Send the request to an LLM to classify intent before execution.
- Include the current message, relevant visible conversation history, book title, codex entry IDs/titles/types, and explicit mention IDs.
- Do not include the full manuscript or full codex entry descriptions in this first request.
- Return structured JSON identifying the intended operation, relevant entries, scope, and any missing information.
- Proposed intent categories: conversation/question, manuscript addition, manuscript edit, codex creation, codex update, alias update, place-name suggestions, document-to-codex scan, and clarification needed.
- Support requests containing more than one operation, such as adding an item and updating a character’s description.
- If a prerequisite is missing, such as names for new people, obtain it through chat before executing the requested changes.

#### Stage 2: execute with intent-specific instructions

- Send a second LLM request using instructions tailored to the classified operation.
- Include the current user request and visible conversation history within the user's selected history scope.
- Include the entire codex or the relevant subset, according to the requested action.
- For manuscript editing, include the whole book and the codex as requested.
- Include explicitly referenced entries and enough context to interpret the user’s intended changes.
- Supply chosen personal names before creating person descriptions.
- Supply the optional country and guiding prompt when asking for 10 place-name suggestions.
- Instruct the model to return JSON with both a visible chat response and the actual content to be added or changed.
- A response that performs no edits still includes a chat response and an empty changes list.

#### Proposed structured result contract

```json
{
  "chat_response": "I drafted two codex entries for your review.",
  "changes": [
    {
      "operation": "codex_create",
      "entry_type": "Items",
      "name": "Memory Lantern",
      "content": "The actual descriptive text to save.",
      "aliases": ["the lantern"]
    }
  ]
}
```

- The example shows a proposed shape, not a final schema.
- Define separate validated operation shapes for manuscript edits, codex updates, aliases, and suggestions.
- Existing-entry operations target stable entry IDs. Manuscript changes target stable text ranges/anchors and the document revision they were generated against, not transient page numbers.
- Parse and validate results on the server before applying any change.
- Show `chat_response` in chat and retain structured changes separately for application/review.
- Malformed output must not partially modify the book or codex.
- **Mandatory for the initial release:** all AI-proposed changes require a diff view and explicit approval before application. Show original versus proposed content with additions, removals, and replacements clearly marked.
- Cover manuscript edits/additions, codex creation/updates, alias changes, and document-scan results. New entries appear as additions against an empty original.
- Provide accept/reject controls and partial acceptance for batches. Persist only approved changes, and support undo/restoring an accepted change.
- Bind each diff to its source revision. If the manuscript or entry changes before approval, detect the conflict and regenerate or reconcile the proposal before it can overwrite newer work.

#### Context-size constraint to resolve

The requested whole-book-plus-codex edit context, scan input, or selected chat history may exceed a model's context window. Detect this before sending, explain the limitation in chat, and offer a suitable model, a lower history setting, or an explicitly narrower/staged scope as appropriate. All history is the default, not permission to silently truncate or summarize it. Exact limits and model capabilities should be verified during implementation.

### 1.9 API keys, demo allowance, and usage accounting

- Use the environment-provided key for eligible demo accounts.
- Limit shared-key spending for each new user to USD $1 across all books.
- Track usage per book and across the whole account.
- Count both classification and execution requests, plus place-name suggestions and other paid AI calls.
- Proposed UI: show USD spent for the book, USD spent for the account, and the remaining demo allowance. Avoid implying that provider tokens and dollar credits are interchangeable.
- Separate demo-funded usage from user-key usage so users can understand which balance is affected.
- Proposed behavior: a valid user key takes precedence; if it fails, do not silently charge the shared demo key.
- A user who exhausts the demo allowance can continue writing, editing the codex manually, and generating dataset names, and can add a personal key to continue AI use.
- Record actual provider-reported costs when available, and distinguish pending estimates from settled amounts.
- A hard spending cap needs pre-request budgeting, a bounded output allowance, and an atomic reservation for the total operation so simultaneous requests cannot each spend the same balance.
- Reserve capacity for both classification and execution, and reconcile reservations against actual billed usage, including errors and retries that incur charges.
- If a safe upper cost bound cannot be established for a model/request, it should not use the demo key. Provider accounting details must be checked before implementing this guarantee.
- Never expose the shared key or saved user keys in browser responses, chat prompts, or application logs. Store user keys encrypted and mask them in settings.

### 1.10 Main user journeys and acceptance checklist

1. A new user signs up on a serif, themed authentication page, enters a book title, and begins writing.
2. Writing flows across dynamically generated pages. Previous/next controls and the whole-book scrubber navigate without changing the underlying document.
3. The user opens the codex while writing, creates a person with selected names, and edits aliases as comma-separated text.
4. Matching canonical names and aliases appear as codex-linked spans in the manuscript.
5. The name generator searches existing JSON records and generates combinations with independent first-name and surname countries and optional gender.
6. The user asks for place names through chat, provides a guiding prompt and optional country, and receives 10 suggestions.
7. The user types `@`, searches the book’s codex, and references an entry in a chat message.
8. An AI request is classified using lightweight metadata before a second request receives the operation-specific context.
9. The result includes a visible chat reply and structured content changes; only the visible reply is replayed as assistant chat history.
10. The user can request a batch of codex entries and update aliases through chat. Person creation resolves names before generating descriptions.
11. Book and account usage include both LLM stages, and concurrent demo requests cannot bypass the account’s $1 allowance.
12. Changing among light, dark, and paper modes produces a consistent, readable editor, chat, codex, and authentication experience.
13. Import a story, scan it through chat, review extracted codex entries in a diff, and approve selected entries without altering the manuscript.
14. Every AI content change remains a proposal until the user approves its diff, including changes to aliases and batch-created entries.
15. Favorite and selected models survive logout/login. The catalog refreshes once at login and is reused on page refresh and subsequent model-picker use.
16. Chat history defaults to All; selecting zero excludes prior conversation from both AI stages while retaining the current request and required content context.
17. Book synopsis, genre, point of view, tense, and style notes can be edited and supplied to relevant execution requests.

### 1.11 Reliability and supporting features

All features in this subsection are included in the initial release.

- **Autosave and recovery:** debounce manuscript/codex saves, show saving/saved/failed status, and retain recoverable drafts when a connection fails.
- **AI change review:** require the original/proposed diff and explicit approval for every AI edit as specified above, with partial batch acceptance and undo.
- **Revision history:** retain manuscript and codex revisions so manual or AI changes can be restored.
- **Concurrent-edit protection:** detect stale AI results and edits from another tab before overwriting newer text.
- **Book management:** rename, archive, and delete projects, with recovery or a deliberate confirmation for deletion.
- **Word counts:** show book word count and selection word count; optionally add a writing-session count.
- **Chapters and scenes:** allow explicit structure and navigation without equating chapter boundaries with visual pages.
- **Export:** offer plain text and a manuscript-friendly format such as DOCX; remove application-only codex markup from exports.
- **Theme persistence and accessibility:** remember theme choice, maintain readable contrast, support keyboard navigation, and provide a useful narrow-screen layout.
- **Codex match disambiguation:** prefer longest exact matches, respect word boundaries, and let users resolve aliases shared by multiple entries. Generic aliases such as “Doctor” should not automatically link every occurrence incorrectly.
- **Codex lifecycle:** search/filter entries and define what happens to references when entries or custom types are renamed or deleted.
- **Reliable AI operations:** show pending/error states, prevent duplicate submissions, and make retries avoid duplicate codex entries or manuscript changes.
- **Account isolation and request validation:** enforce ownership checks for books, codex entries, mentions, chat history, and edit targets; treat AI output as untrusted data.
- **Long-history handling:** default to All, honor the chat history dropdown down to zero, and report context overflow without silently trimming or summarizing messages.

### 1.12 Decisions to confirm

| Topic | Proposed interpretation or decision needed |
| --- | --- |
| Laravel version | Laravel 10 is the requested target. Verify its support status and dependency compatibility before implementation; any version change needs agreement. |
| No `x-template` | Proposed: plain HTML in Blade layouts/includes, no `<x-...>` view components or Alpine directives; separate JavaScript modules. |
| Winder scroller | Proposed: a whole-book draggable scrubber with page position. Confirm its visual behavior. |
| Editor formatting | ProseMirror is confirmed. Decide the initial formatting toolbar/schema; dynamic pages, codex links, chapters/scenes, and AI diff review are required. |
| Paper mode | Proposed: warm page/background tones and subdued serif controls, independent of dark mode. |
| AI edits | Confirmed: mandatory diff review and approval for all AI changes in the initial release. |
| Demo allowance | Proposed: $1 lifetime shared-key spend per new account, no automatic replenishment. Confirm eligibility and whether all new accounts receive it. |
| Classifier model | Decide whether classification uses the selected model or a separate configured low-cost model; both stages count toward usage. |
| Chat history | Confirmed: default All with a dropdown down to zero. Proposed finite options count complete exchanges: 1, 5, 10, 20. |
| Full-book context | Requested for book edits; recommended to block oversized requests and offer model/scope options instead of silent truncation. |
| Personal names | Confirm manual names are allowed and choose the default random list size and rank weighting. |
| Codex scope | Proposed: codex and custom types are book-specific; cross-book sharing is not requested. |
| Character fields | Start with freeform content plus names/types/aliases, or add structured fields such as appearance, motivation, and relationships. |
| Import formats | Story import and scan-to-codex are confirmed for the initial release; agree supported file formats. |
| Model preferences | Confirmed: search, prices, persistent favorites/selection, and one automatic catalog refresh per login, plus manual refresh. |

## 2. Future versions

- Search and replace across the manuscript.
- Focus mode that hides side panels while retaining quick access.
- Optional inline formatting, scene separators, explicit page breaks, and typography preferences.
- Configurable random-name list length, copy actions, and duplicate removal.
- Usage details by request/model/date, with token counts and estimated-versus-final cost status.
- User-key replacement/removal and an account data deletion/export workflow.
- Email verification and practical demo-abuse controls, such as request/account rate limits, to protect the shared allowance.
- Backups and restore procedures for stored manuscripts and codex data.

## 3. Implementation sequence after review

1. Resolve remaining initial-release choices, including import formats, ProseMirror schema/formatting, pagination presentation, classifier model, and framework/dependency compatibility.
2. Set up Laravel, Breeze authentication, HTML Blade layouts, separate JavaScript, Tailwind, serif typography, persistent themes, and accessible responsive layouts using the existing database settings.
3. Implement account-owned books, library navigation, synopsis/genre/point-of-view/tense/style settings, rename/archive/delete, and the revision model.
4. Build the continuous ProseMirror editor with dynamic pages, previous/next controls, the whole-book scrubber, chapters/scenes, word counts, autosave/recovery, revision restore, and concurrent-edit protection.
5. Implement codex types, entries, aliases, search/filter/lifecycle behavior, dataset name generation, and clickable manuscript references with ambiguous-match handling.
6. Add story import with preview/recovery and manuscript export with application-only reference markup removed.
7. Integrate OpenRouter key settings, catalog caching with one refresh per login, manual refresh, model search/pricing, persistent favorites/selection, and model-availability feedback.
8. Build usage accounting, atomic demo-budget reservations, bounded requests, cost reconciliation, and book/account totals before enabling paid AI actions.
9. Implement persistent chat, mentions, the All-to-zero history dropdown, lightweight classification, intent-specific execution, book context, validated structured results, and context-limit handling.
10. Implement mandatory diff review for all AI changes, individual/batch accept/reject, revision conflict checks, and undo. Connect person-name prerequisites, place-name suggestions, and manuscript/codex editing to this flow.
11. Add the Scan document to create codex action through chat, with existing-name extraction, duplicate/alias resolution, explicit handling of long documents, usage tracking, and entry-by-entry diff approval.
12. Verify complete authentication/writing/import/export flows, pagination on long manuscripts, accessibility/themes, ownership isolation, model preference persistence and refresh frequency, history controls, AI diff approval, retry safety, and concurrent saves/spending enforcement.

The user approved the initial scope and Section 1.12 decisions. See build-v1.md for implementation progress and concrete defaults.


