@extends('layouts.writer')
@section('title', $book->title.' · '.config('app.name'))
@section('content')
<main id="workspace" data-book="{{ $book->id }}" data-model="{{ auth()->user()->selected_model }}" data-favorites="{{ json_encode(auth()->user()->favorite_models ?? []) }}">
    <div class="book-bar"><div><h1 id="book-heading">{{ $book->title }}</h1></div><div class="row"><span id="save-status" role="status">{{ __('Loading…') }}</span><button data-panel="details">{{ __('Book details') }}</button><button data-panel="codex">{{ __('Codex') }}</button><button data-panel="history">{{ __('Revisions') }}</button><a class="toolbar-link" href="{{ route('books.llm-log', $book) }}">{{ __('LLM Log') }}</a></div></div>
    <div class="workspace-tabs"><button type="button" id="show-writing" aria-pressed="true">{{ __('Manuscript') }}</button><button type="button" id="show-chat" aria-pressed="false">{{ __('AI companion') }}</button></div>
    <div class="workspace-grid">
<div class="side-panel-shell">    <aside id="side-panel" inert aria-hidden="true"><div class="row panel-title"><h2 id="panel-title">{{ __('Codex') }}</h2><button id="close-panel" aria-label="{{ __('Close panel') }}">×</button></div>
        <section data-content="codex" hidden>
            <div id="codex-browser">
                <div id="codex-recovery" class="notice" hidden>{{ __('A local codex draft is available.') }} <button id="recover-codex">{{ __('Recover draft') }}</button><button id="discard-codex">{{ __('Discard local draft') }}</button></div>
                <button id="new-entry" class="primary">{{ __('New entry') }}</button>
                <div id="codex-list"></div>
                <div class="codex-scan"><button id="scan-document">{{ __('Scan document to create codex ✧') }}</button></div>
            </div>
            <form id="entry-form" hidden>
                <input name="id" type="hidden">
                <label>{{ __('Name') }}<input name="name" required maxlength="200"></label>
                <button type="button" class="field-link" id="open-names">{{ __('Find names & places') }}</button>
                <label>{{ __('Type') }}<select name="type"></select></label>
                <button type="button" class="field-link" id="open-custom-type">{{ __('Add a custom type') }}</button>
                <label>{{ __('Aliases, separated by commas') }}<input name="aliases" maxlength="4000"></label>
                <label>{{ __('Description') }}<textarea name="content" rows="8"></textarea></label>
                <div class="row"><button class="primary">{{ __('Save entry') }}</button><button type="button" id="delete-entry">{{ __('Delete entry') }}</button></div>
                <button type="button" class="field-link" id="back-to-codex">{{ __('Back to all entries') }}</button>
            </form>
        </section>

        <section data-content="details" hidden><form id="details-form"><label>{{ __('Book title') }}<input name="title" required maxlength="200"></label>@foreach (['synopsis'=>'Synopsis','genre'=>'Genre','point_of_view'=>'Point of view','tense'=>'Tense','style_notes'=>'Style notes'] as $key=>$label)<label>{{ __($label) }}<textarea name="{{ $key }}" rows="{{ in_array($key,['synopsis','style_notes']) ? 4 : 1 }}"></textarea></label>@endforeach<button class="primary">{{ __('Save book details') }}</button></form></section>
        <section data-content="history" hidden><p class="muted">{{ __('Select a revision to compare it with the current manuscript and codex before restoring.') }}</p><div id="revision-list"></div><h3>{{ __('Chapters & scenes') }}</h3><div id="outline"></div></section>
    </aside><div id="panel-resizer" role="separator" tabindex="0" aria-label="{{ __('Resize side panel') }}" aria-orientation="vertical" aria-valuemin="180" aria-valuemax="560" aria-valuenow="300"></div></div>
        <section class="writing-pane" aria-label="{{ __('Manuscript editor') }}">
            <div class="editor-toolbar"><div class="row"><button id="undo" title="{{ __('Undo') }}">↶</button><button id="redo" title="{{ __('Redo') }}">↷</button><button id="bold" title="{{ __('Bold') }}"><strong>B</strong></button><button id="italic" title="{{ __('Italic') }}"><em>I</em></button><select id="block-style" aria-label="{{ __('Paragraph style') }}"><option value="paragraph">{{ __('Body text') }}</option><option value="chapter">{{ __('Chapter heading') }}</option><option value="scene">{{ __('Scene heading') }}</option></select><button id="scene-break" title="{{ __('Scene separator') }}">⁂</button></div></div>
            <div id="recovery" class="notice" hidden>{{ __('A recovered local draft is available.') }} <button id="recover-draft">{{ __('Review local draft') }}</button><button id="discard-draft">{{ __('Keep server version') }}</button></div>
            <div id="editor-scroll"><div id="editor"></div></div>
            <footer class="page-navigation"><span id="word-count">{{ __('0 words') }}</span><span id="ai-thinking-status" role="status" hidden>{{ __('AI is thinking. Please don’t write anything.') }}</span></footer>
        </section>
        <aside class="chat-pane" aria-label="{{ __('AI writing companion') }}"><div id="chat-resizer" role="separator" tabindex="0" aria-label="{{ __('Resize chat panel') }}" aria-orientation="vertical" aria-valuemin="260" aria-valuemax="640" aria-valuenow="360"></div>
            <div class="chat-heading"><div class="eyebrow">{{ __('A SECOND PAIR OF EYES') }}</div><h2>{{ __('Your writing companion') }} <span>✧</span></h2><p class="muted">{{ __('Think aloud. Explore a possibility. Keep your voice.') }}</p></div>
            <details id="model-picker"><summary id="model-summary">{{ __('Choose an AI model') }}</summary><div class="model-menu"><label class="check model-favorites"><input id="favorites-only" type="checkbox" @checked(auth()->user()->favorites_only)> {{ __('Favorites only') }}</label><div id="model-search-filters"><input id="model-search" placeholder="{{ __('Search models…') }}" aria-label="{{ __('Search models') }}">
                <div class="model-filters">
                    
                    <fieldset class="model-price-range"><legend>{{ __('Output $ / 1M tokens') }}</legend><label>{{ __('Min') }}<input id="model-price-min" type="number" min="0" step="any" value="1" aria-label="{{ __('Minimum model output price in USD per million tokens') }}"></label><label>{{ __('Max') }}<input id="model-price-max" type="number" min="0" step="any" value="10" aria-label="{{ __('Maximum model output price in USD per million tokens') }}"></label></fieldset>
                </div></div>
                <div id="model-list"></div><button id="refresh-models">{{ __('Refresh catalog') }}</button><small id="catalog-status"></small></div></details>

            <div id="chat-messages" aria-live="polite"></div><div id="proposal-list"></div>
            <div id="selection-scope" class="selection-scope" hidden><span id="selection-preview"></span><button type="button" id="clear-selection-scope">{{ __('Clear selection scope') }}</button></div><form id="chat-form"><div id="mention-list" hidden></div><textarea id="chat-input" rows="3" placeholder="{{ __('Talk about your story… Type @ to reference your codex.') }}" required></textarea><div id="selected-mentions"></div><div class="row chat-actions"><small>{{ __('All edits are yours to approve.') }}</small><button class="primary" id="send-chat">{{ __('Send ↗') }}</button></div></form>
            <div class="history-control"><label for="chat-history">{{ __('Include past conversation') }}</label><select id="chat-history"><option value="all">{{ __('All history') }}</option><option value="20">{{ __('Last 20 exchanges') }}</option><option value="10">{{ __('Last 10 exchanges') }}</option><option value="5">{{ __('Last 5 exchanges') }}</option><option value="1">{{ __('Last exchange') }}</option><option value="0">{{ __('0 — No past history') }}</option></select></div>
            <div class="usage-strip" id="usage">{{ __('AI usage is loading…') }}</div>
        </aside>
    </div>

</main>
<dialog id="names-dialog" aria-labelledby="names-title">
<div class="dialog-heading"><h2 id="names-title">{{ __('Names & places') }}</h2><button type="button" data-close-dialog aria-label="{{ __('Close names and places') }}">×</button></div>
<div class="name-search-toolbar">
<label>{{ __('First-name country') }}<select id="first-country"></select></label><label>{{ __('Last-name country') }}<select id="last-country"></select></label><label>{{ __('Gender') }}<select id="name-gender"><option value="">{{ __('Both / any') }}</option><option value="Male">{{ __('Male') }}</option><option value="Female">{{ __('Female') }}</option></select></label><label>{{ __('Search letters') }}<input id="name-search" placeholder="{{ __('Start typing…') }}"></label><button id="search-names">{{ __('Search') }}</button><button id="random-names">{{ __('Random 10') }}</button>
</div>
<form id="apply-codex-name" class="name-selection"><label>{{ __('First name') }}<input id="selected-first-name" maxlength="200"></label><label>{{ __('Last name') }}<input id="selected-last-name" maxlength="200"></label><button class="primary" type="submit">{{ __('Apply name') }}</button></form>
<div id="name-results"></div>
<hr><h3>{{ __('Place-name inspiration') }}</h3><select id="place-country" aria-label="{{ __('Place-name country (optional)') }}"></select><textarea id="place-prompt" rows="3" aria-label="{{ __('Guide the place-name suggestions') }}" placeholder="{{ __('Guide the suggestions: a weathered coastal town with a forgotten observatory…') }}"></textarea><button id="suggest-places">{{ __('Ask for 10 place names in chat') }}</button>
</dialog>
<dialog id="custom-type-dialog" aria-labelledby="custom-type-title"><div class="dialog-heading"><h2 id="custom-type-title">{{ __('Add a custom type') }}</h2><button type="button" data-close-dialog aria-label="{{ __('Close custom type') }}">×</button></div><form id="custom-type-form"><label>{{ __('Type name') }}<input id="new-type" maxlength="80" required></label><button type="submit" class="primary">{{ __('Save type') }}</button></form></dialog>
<dialog id="review-dialog"><div class="row panel-title"><h2>{{ __('Review proposed changes') }}</h2><button id="close-review" aria-label="{{ __('Close review') }}">×</button></div><p id="review-description">{{ __('Nothing is applied until you approve.') }}</p><div id="diff-content"></div><div class="row review-actions"><button id="approve-changes" class="primary">{{ __('Apply selected changes') }}</button><button id="reject-changes">{{ __('Reject all') }}</button></div></dialog>
<dialog id="import-dialog"><div class="dialog-heading"><h2>{{ __('Import your story') }}</h2><button type="button" id="cancel-import" aria-label="{{ __('Close import') }}">×</button></div><p>{{ __('Preview the text before replacing the manuscript. A revision is saved first.') }}</p><input id="import-file" type="file" accept=".txt,.docx"><textarea id="import-preview" rows="14" aria-label="{{ __('Imported text preview') }}"></textarea><div class="row"><button id="confirm-import" class="primary" disabled>{{ __('Replace manuscript with this text') }}</button></div></dialog>
<dialog id="revision-diff-dialog" aria-labelledby="revision-diff-title"><div class="dialog-heading"><h2 id="revision-diff-title">{{ __('Revision changes') }}</h2><button type="button" data-close-dialog aria-label="{{ __('Close revision details') }}">×</button></div><p id="revision-diff-date" class="muted"></p><div id="revision-diff-count" role="status"></div><p class="muted">{{ __('Revision → current. Each replaced line counts as one removal and one addition. Manuscript formatting is shown as Markdown.') }}</p><div id="revision-diff-content"></div><button type="button" id="restore-inspected-revision">{{ __('Restore this revision') }}</button></dialog>
<dialog id="large-prompt-dialog" aria-labelledby="large-prompt-title">
<div class="dialog-heading"><h2 id="large-prompt-title">{{ __('This is a large prompt') }}</h2><button type="button" data-close-dialog aria-label="{{ __('Close large prompt warning') }}">×</button></div>
<p>{{ __('This prompt contains approximately') }} <strong id="large-prompt-count"></strong> {{ __('words. Sending this much text can be expensive.') }}</p>
<p>{{ __('Consider selecting a passage in the manuscript first. Selection editing sends only that passage and nearby context, along with your codex and chosen chat history.') }}</p>
<form method="dialog"><label class="check"><input type="checkbox" id="disable-large-prompt-warning"> {{ __('Disable this warning for the next hour') }}</label><div class="row"><button value="cancel" autofocus>{{ __('Cancel') }}</button><button value="send" class="primary">{{ __('Send anyway') }}</button></div></form>
</dialog>
<dialog id="writing-welcome" aria-labelledby="writing-welcome-title" aria-describedby="welcome-model welcome-model-advice">
<div class="dialog-heading"><h2 id="writing-welcome-title">{{ __('Welcome to your writing desk') }}</h2><button type="button" data-welcome-close aria-label="{{ __('Close welcome') }}">×</button></div>
<p>{{ __('Start with an idea, a question, or a scene. Tell your writing companion what you want to work on, or write directly in the manuscript.') }}</p>
<p id="welcome-model" class="welcome-model" aria-live="polite"></p>
<p id="welcome-model-advice">{{ __('Cheaper models may struggle to follow detailed instructions consistently. Consider a mid-range model for complex revisions and book-wide changes. Price isn’t a guarantee of quality—always review the proposed changes.') }}</p>
<p class="muted">{{ __('Use the model picker to change your companion. Select text in the manuscript for a focused edit. Every AI change is yours to review and approve.') }}</p>
<button type="button" class="primary" data-welcome-close autofocus>{{ __('Let’s write ↗') }}</button>
</dialog>
<dialog id="typography-dialog" aria-labelledby="typography-title"><div class="dialog-heading"><h2 id="typography-title">{{ __('Typography settings') }}</h2><button type="button" data-close-dialog aria-label="{{ __('Close typography settings') }}">×</button></div><div class="typography-grid"><label>{{ __('Font family') }}<select id="type-font"><option value="Georgia">Georgia</option><option value="Palatino Linotype">Palatino</option><option value="Times New Roman">Times New Roman</option><option value="Arial">Arial</option><option value="Verdana">Verdana</option><option value="Courier New">Courier</option></select></label><label>{{ __('Text size') }}<select id="type-size"><option value="16">{{ __('Small') }}</option><option value="18">{{ __('Standard') }}</option><option value="20">{{ __('Large') }}</option><option value="24">{{ __('Extra large') }}</option></select></label><label>{{ __('Line height') }}<select id="type-line"><option value="1.4">1.4</option><option value="1.6">1.6</option><option value="1.8">1.8</option><option value="2">2</option></select></label><label>{{ __('Text indent') }}<select id="type-indent"><option value="0">{{ __('None') }}</option><option value="1">1 em</option><option value="1.5">1.5 em</option><option value="2">2 em</option></select></label><label>{{ __('Paragraph spacing') }}<select id="type-spacing"><option value="0">{{ __('None') }}</option><option value="0.5">{{ __('Compact') }}</option><option value="1">{{ __('Standard') }}</option><option value="1.5">{{ __('Spacious') }}</option></select></label><label>{{ __('Page width') }}<select id="type-width"><option value="640">{{ __('Narrow') }}</option><option value="760">{{ __('Standard') }}</option><option value="880">{{ __('Wide') }}</option><option value="1000">{{ __('Extra wide') }}</option></select></label><label>{{ __('Text alignment') }}<select id="type-align"><option value="left">{{ __('Left') }}</option><option value="justify">{{ __('Justify') }}</option></select></label><label>{{ __('UI size') }} <output id="type-ui-output">100%</output><input id="type-ui" type="range" min="80" max="130" step="5" value="100"><small>{{ __('Changes interface sizes only.') }}</small></label></div><button type="button" id="reset-typography">{{ __('Reset defaults') }}</button></dialog>
<dialog id="thinking-dialog" aria-labelledby="thinking-title"><div class="dialog-heading"><h2 id="thinking-title">{{ __('Your writing companion is still working') }}</h2><button type="button" data-close-dialog aria-label="{{ __('Close progress message') }}">×</button></div><p>{{ __('Book-writing LLM calls can take longer than ordinary chat because the AI needs to think with much more context.') }}</p><p>{{ __('You can close this message and keep waiting. It will disappear automatically when the AI responds.') }}</p><button type="button" data-close-dialog>{{ __('Close') }}</button></dialog>
@endsection

