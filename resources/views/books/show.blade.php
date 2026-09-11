@extends('layouts.writer')
@section('title', $book->title.' · '.config('app.name'))
@section('content')
<main id="workspace" data-book="{{ $book->id }}" data-model="{{ auth()->user()->selected_model }}" data-favorites="{{ json_encode(auth()->user()->favorite_models ?? []) }}">
    <div class="book-bar"><div><h1 id="book-heading">{{ $book->title }}</h1></div><div class="row"><span id="save-status" role="status">Loading…</span><button data-panel="details">Book details</button><button data-panel="codex">Codex</button><button data-panel="history">Revisions</button><a class="toolbar-link" href="{{ route('books.llm-log', $book) }}">LLM Log</a></div></div>
    <div class="workspace-tabs"><button type="button" id="show-writing" aria-pressed="true">Manuscript</button><button type="button" id="show-chat" aria-pressed="false">AI companion</button></div>
    <div class="workspace-grid">
<div class="side-panel-shell">    <aside id="side-panel" inert aria-hidden="true"><div class="row panel-title"><h2 id="panel-title">Codex</h2><button id="close-panel" aria-label="Close panel">×</button></div>
        <section data-content="codex" hidden>
            <div id="codex-browser">
                <div id="codex-recovery" class="notice" hidden>A local codex draft is available. <button id="recover-codex">Recover draft</button><button id="discard-codex">Discard local draft</button></div>
                <button id="new-entry" class="primary">New entry</button>
                <div id="codex-list"></div>
                <div class="codex-scan"><button id="scan-document">Scan document to create codex ✧</button></div>
            </div>
            <form id="entry-form" hidden>
                <input name="id" type="hidden">
                <label>Name<input name="name" required maxlength="200"></label>
                <button type="button" class="field-link" id="open-names">Find names & places</button>
                <label>Type<select name="type"></select></label>
                <button type="button" class="field-link" id="open-custom-type">Add a custom type</button>
                <label>Aliases, separated by commas<input name="aliases" maxlength="4000"></label>
                <label>Description<textarea name="content" rows="8"></textarea></label>
                <div class="row"><button class="primary">Save entry</button><button type="button" id="delete-entry">Delete entry</button></div>
                <button type="button" class="field-link" id="back-to-codex">Back to all entries</button>
            </form>
        </section>

        <section data-content="details" hidden><form id="details-form"><label>Book title<input name="title" required maxlength="200"></label>@foreach (['synopsis'=>'Synopsis','genre'=>'Genre','point_of_view'=>'Point of view','tense'=>'Tense','style_notes'=>'Style notes'] as $key=>$label)<label>{{ $label }}<textarea name="{{ $key }}" rows="{{ in_array($key,['synopsis','style_notes']) ? 4 : 1 }}"></textarea></label>@endforeach<button class="primary">Save book details</button></form></section>
        <section data-content="history" hidden><p class="muted">Select a revision to compare it with the current manuscript and codex before restoring.</p><div id="revision-list"></div><h3>Chapters & scenes</h3><div id="outline"></div></section>
    </aside></div>
        <section class="writing-pane" aria-label="Manuscript editor">
            <div class="editor-toolbar"><div class="row"><button id="undo" title="Undo">↶</button><button id="redo" title="Redo">↷</button><button id="bold" title="Bold"><strong>B</strong></button><button id="italic" title="Italic"><em>I</em></button><select id="block-style" aria-label="Paragraph style"><option value="paragraph">Body text</option><option value="chapter">Chapter heading</option><option value="scene">Scene heading</option></select><button id="scene-break" title="Scene separator">⁂</button></div></div>
            <div id="recovery" class="notice" hidden>A recovered local draft is available. <button id="recover-draft">Review local draft</button><button id="discard-draft">Keep server version</button></div>
            <div id="editor-scroll"><div id="editor"></div></div>
            <footer class="page-navigation"><span id="word-count">0 words</span></footer>
        </section>
        <aside class="chat-pane" aria-label="AI writing companion">
            <div class="chat-heading"><div class="eyebrow">A SECOND PAIR OF EYES</div><h2>Your writing companion <span>✧</span></h2><p class="muted">Think aloud. Explore a possibility. Keep your voice.</p></div>
            <details id="model-picker"><summary id="model-summary">Choose an AI model</summary><div class="model-menu"><label class="check model-favorites"><input id="favorites-only" type="checkbox" @checked(auth()->user()->favorites_only)> Favorites only</label><div id="model-search-filters"><input id="model-search" placeholder="Search models…" aria-label="Search models">
                <div class="model-filters">
                    
                    <fieldset class="model-price-range"><legend>Output $ / 1M tokens</legend><label>Min<input id="model-price-min" type="number" min="0" step="any" value="1" aria-label="Minimum model output price in USD per million tokens"></label><label>Max<input id="model-price-max" type="number" min="0" step="any" value="10" aria-label="Maximum model output price in USD per million tokens"></label></fieldset>
                </div></div>
                <div id="model-list"></div><button id="refresh-models">Refresh catalog</button><small id="catalog-status"></small></div></details>

            <div id="chat-messages" aria-live="polite"></div><div id="proposal-list"></div>
            <div id="selection-scope" class="selection-scope" hidden><span id="selection-preview"></span><button type="button" id="clear-selection-scope">Clear selection scope</button></div><form id="chat-form"><div id="mention-list" hidden></div><textarea id="chat-input" rows="3" placeholder="Talk about your story… Type @ to reference your codex." required></textarea><div id="selected-mentions"></div><div class="row chat-actions"><small>All edits are yours to approve.</small><button class="primary" id="send-chat">Send ↗</button></div></form>
            <div class="history-control"><label for="chat-history">Include past conversation</label><select id="chat-history"><option value="all">All history</option><option value="20">Last 20 exchanges</option><option value="10">Last 10 exchanges</option><option value="5">Last 5 exchanges</option><option value="1">Last exchange</option><option value="0">0 — No past history</option></select></div>
            <div class="usage-strip" id="usage">AI usage is loading…</div>
        </aside>
    </div>

</main>
<dialog id="names-dialog" aria-labelledby="names-title">
<div class="dialog-heading"><h2 id="names-title">Names & places</h2><button type="button" data-close-dialog aria-label="Close names and places">×</button></div>
<div class="name-search-toolbar">
<label>First-name country<select id="first-country"></select></label><label>Last-name country<select id="last-country"></select></label><label>Gender<select id="name-gender"><option value="">Both / any</option><option>Male</option><option>Female</option></select></label><label>Search letters<input id="name-search" placeholder="Start typing…"></label><button id="search-names">Search</button><button id="random-names">Random 10</button>
</div>
<form id="apply-codex-name" class="name-selection"><label>First name<input id="selected-first-name" maxlength="200"></label><label>Last name<input id="selected-last-name" maxlength="200"></label><button class="primary" type="submit">Apply name</button></form>
<div id="name-results"></div>
<hr><h3>Place-name inspiration</h3><select id="place-country" aria-label="Place-name country (optional)"></select><textarea id="place-prompt" rows="3" aria-label="Guide the place-name suggestions" placeholder="Guide the suggestions: a weathered coastal town with a forgotten observatory…"></textarea><button id="suggest-places">Ask for 10 place names in chat</button>
</dialog>
<dialog id="custom-type-dialog" aria-labelledby="custom-type-title"><div class="dialog-heading"><h2 id="custom-type-title">Add a custom type</h2><button type="button" data-close-dialog aria-label="Close custom type">×</button></div><form id="custom-type-form"><label>Type name<input id="new-type" maxlength="80" required></label><button type="submit" class="primary">Save type</button></form></dialog>
<dialog id="review-dialog"><div class="row panel-title"><h2>Review proposed changes</h2><button id="close-review" aria-label="Close review">×</button></div><p id="review-description">Nothing is applied until you approve.</p><div id="diff-content"></div><div class="row review-actions"><button id="approve-changes" class="primary">Apply selected changes</button><button id="reject-changes">Reject all</button></div></dialog>
<dialog id="import-dialog"><div class="dialog-heading"><h2>Import your story</h2><button type="button" id="cancel-import" aria-label="Close import">×</button></div><p>Preview the text before replacing the manuscript. A revision is saved first.</p><input id="import-file" type="file" accept=".txt,.docx"><textarea id="import-preview" rows="14" aria-label="Imported text preview"></textarea><div class="row"><button id="confirm-import" class="primary" disabled>Replace manuscript with this text</button></div></dialog>
<dialog id="revision-diff-dialog" aria-labelledby="revision-diff-title"><div class="dialog-heading"><h2 id="revision-diff-title">Revision changes</h2><button type="button" data-close-dialog aria-label="Close revision details">×</button></div><p id="revision-diff-date" class="muted"></p><div id="revision-diff-count" role="status"></div><p class="muted">Revision → current. Each replaced line counts as one removal and one addition. Manuscript formatting is shown as Markdown.</p><div id="revision-diff-content"></div><button type="button" id="restore-inspected-revision">Restore this revision</button></dialog>
<dialog id="large-prompt-dialog" aria-labelledby="large-prompt-title">
<div class="dialog-heading"><h2 id="large-prompt-title">This is a large prompt</h2><button type="button" data-close-dialog aria-label="Close large prompt warning">×</button></div>
<p>This prompt contains approximately <strong id="large-prompt-count"></strong> words. Sending this much text can be expensive.</p>
<p>Consider selecting a passage in the manuscript first. Selection editing sends only that passage and nearby context, along with your codex and chosen chat history.</p>
<form method="dialog"><label class="check"><input type="checkbox" id="disable-large-prompt-warning"> Disable this warning for the next hour</label><div class="row"><button value="cancel" autofocus>Cancel</button><button value="send" class="primary">Send anyway</button></div></form>
</dialog>
<dialog id="writing-welcome" aria-labelledby="writing-welcome-title" aria-describedby="welcome-model welcome-model-advice">
<div class="dialog-heading"><h2 id="writing-welcome-title">Welcome to your writing desk</h2><button type="button" data-welcome-close aria-label="Close welcome">×</button></div>
<p>Start with an idea, a question, or a scene. Tell your writing companion what you want to work on, or write directly in the manuscript.</p>
<p id="welcome-model" class="welcome-model" aria-live="polite"></p>
<p id="welcome-model-advice">Cheaper models may struggle to follow detailed instructions consistently. Consider a mid-range model for complex revisions and book-wide changes. Price isn’t a guarantee of quality—always review the proposed changes.</p>
<p class="muted">Use the model picker to change your companion. Select text in the manuscript for a focused edit. Every AI change is yours to review and approve.</p>
<button type="button" class="primary" data-welcome-close autofocus>Let’s write ↗</button>
</dialog>
@endsection

