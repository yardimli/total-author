<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="A thoughtful workspace for literary fiction. Write by hand, develop your story with AI, keep a living codex, and review every revision before it becomes your own.">
    <title>{{ config('app.name') }} — Your story. Your voice.</title>
    @vite(['resources/css/landing.css', 'resources/js/landing.js'])
@if(config('app.favicon'))<link rel="icon" href="{{ asset(config('app.favicon')) }}">@endif
@include('partials.seo')
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="landing-nav wrap">
    <a class="wordmark" href="{{ route('home') }}">@include('partials.brand')</a>
    <nav aria-label="Main navigation"><a href="#inside">Inside the desk</a><a href="#process">The writing process</a>@auth<a class="button small" href="{{ route('dashboard') }}">My library ↗</a>@else<a href="{{ route('login') }}">Sign in</a><a class="button small" href="{{ route('register') }}">Start writing ↗</a>@endauth</nav>
</header>
<main id="main">
<section class="hero wrap">
    <p class="eyebrow">A WORKSPACE FOR LITERARY FICTION</p>
    <h1>A place for your words.<br><em>A little room for possibility.</em></h1>
    <p class="hero-copy">For the first sentence, the difficult chapter, and the world that won’t leave you alone. Write with an AI companion beside you—and keep your voice at the heart of every page.</p>
    <div class="hero-actions"><a class="button" href="{{ auth()->check() ? route('dashboard') : route('register') }}">Take a seat at the desk <span>↗</span></a><a class="text-link" href="#inside">See how it works ↓</a></div>
    <p class="quiet">Your manuscript. Your world. Every edit yours to approve.</p>
    <figure class="hero-shot"><button class="screenshot" data-preview="{{ asset('images/landing/manuscript.png') }}" aria-label="Enlarge the manuscript screenshot"><img src="{{ asset('images/landing/manuscript.png') }}" width="1600" height="1000" alt="The real Star Trek meets Star Wars manuscript open in {{ config('app.name') }} beside its AI conversation" fetchpriority="high"></button><figcaption><span>ON THE DESK</span> Star Trek meets Star Wars <span>Actual workspace · click any screenshot to explore</span></figcaption></figure>
</section>
<section class="intro wrap" id="inside"><p class="eyebrow">MEET YOUR WRITING ROOM</p><h2>One desk.<br>Every part of your story.</h2><p>A manuscript to shape. A companion to think with. A codex to keep the details close. Move between them without losing the thread.</p></section>
@php
$features = [
 ['id'=>'manuscript','number'=>'01','label'=>'THE MANUSCRIPT','title'=>'Stay close to the page.','copy'=>'Write directly in a quiet, serif editor. Shape a paragraph, mark a new chapter, or find your rhythm one sentence at a time. Page boundaries and generous margins give your draft room to breathe.','points'=>['Bold, italics, chapters, scenes, and an outline.','Autosave, local draft recovery, and live word counts.','Light, Dark, and warm Paper modes.'],'image'=>'manuscript.png','alt'=>'Manuscript editor showing the book’s opening, page layout, formatting toolbar, and AI companion'],
 ['id'=>'conversation','number'=>'02','label'=>'THE WRITING COMPANION','title'=>'Think aloud.
Find what happens next.','copy'=>'Tell the companion what you’re trying to do. Explore a character’s motivation, question a scene, or ask for a different direction. Keep talking: your chosen conversation history travels with the next request.','points'=>['Regular chat receives the whole book and codex.','The area around your cursor guides the focus without limiting broader edits.','Include all past conversation, a few exchanges, or none.'],'image'=>'conversation.png','alt'=>'Real writing companion conversation with the book and View Changes controls'],
 ['id'=>'selection','number'=>'03','label'=>'A LITTLE CHANGE. OR A BIG ONE.','title'=>'Choose how much
to reimagine.','copy'=>'Ask for a revision across the book, or highlight a passage to work on just those words. Selection editing sends the passage and nearby context while keeping everything outside the selection untouched.','points'=>['Manual writing and AI revisions share the same manuscript.','Selected text comes with roughly 500 words of context on each side.','Ask for a wider rewrite without a selection; inspect the proposal before applying it.'],'image'=>'selection.png','alt'=>'Selected manuscript text with the selection-only editing indicator above chat'],
 ['id'=>'codex','number'=>'04','label'=>'THE WORLD BEHIND THE WORDS','title'=>'Keep your people
and places close.','copy'=>'Build a living reference for your book’s characters, places, objects, and lore. Add your own types, capture aliases and nicknames, and open an entry by clicking its name in the manuscript.','points'=>['Collapsible groups and editable descriptions.','Use @ mentions to bring an entry into a conversation.','Scan an existing story or ask chat to propose new codex entries.'],'image'=>'codex.png','alt'=>'Actual codex entries for Star Trek meets Star Wars grouped alongside the manuscript'],
 ['id'=>'names','number'=>'05','label'=>'A NAME TO BEGIN WITH','title'=>'Meet someone
you haven’t written yet.','copy'=>'Search real name datasets or generate fresh first-and-last-name combinations. Choose each name’s country independently, then apply your choice to the codex entry you’re working on.','points'=>['Search by letters, country, and optional gender.','Random personal names use local data and no AI credits.','For places, give the companion a direction and ask for ten suggestions.'],'image'=>'names.png','alt'=>'The actual Names and places dialog with country and gender controls'],
 ['id'=>'revisions','number'=>'06','label'=>'THE FINAL WORD IS YOURS','title'=>'See the difference.
Keep what feels right.','copy'=>'Every AI edit begins as a proposal. Read the additions and removals, approve the changes you want, and reject the rest. Saved revisions let you look back at how the manuscript and codex have changed.','points'=>['New proposals open in a diff view.','View Changes stays beside the assistant reply it belongs to.','Compare saved revisions with the current draft and restore earlier work.'],'image'=>'ai-diff.png','alt'=>'A real approved AI revision with original and proposed text side by side, showing removed and added words'],
 ['id'=>'transparency','number'=>'07','label'=>'NO GUESSING WHAT WAS SENT','title'=>'A clear view
of your AI work.','copy'=>'Choose an OpenRouter model, favorite the ones you return to, and filter by output price. Track spending by book and account, then open the LLM log to inspect individual requests and responses.','points'=>['Use your own API key or the account’s $1 demo allowance.','Inspect full, formatted request and response JSON.','Prompts over 30,000 words warn you before paid calls.'],'image'=>'llm-log.png','alt'=>'Real LLM call history for this book with model, token usage, status, and cost'],
];
@endphp
@foreach ($features as $feature)
<section class="feature wrap {{ $loop->even ? 'reverse' : '' }}" id="{{ $feature['id'] }}">
    <div class="feature-copy"><p class="eyebrow"><span>{{ $feature['number'] }}</span> {{ $feature['label'] }}</p><h2>{!! nl2br(e($feature['title'])) !!}</h2><p>{{ $feature['copy'] }}</p><ul>@foreach ($feature['points'] as $point)<li>{{ $point }}</li>@endforeach</ul></div>
    <figure><button class="screenshot" data-preview="{{ asset('images/landing/'.$feature['image']) }}" aria-label="Enlarge {{ strtolower($feature['label']) }} screenshot"><img src="{{ asset('images/landing/'.$feature['image']) }}" width="1600" height="1000" alt="{{ $feature['alt'] }}" loading="lazy"></button><figcaption>{{ $feature['label'] }} <span>View full screenshot ↗</span></figcaption>@if ($feature['id'] === 'revisions')<button class="revision-preview-link" data-preview="{{ asset('images/landing/revisions.png') }}" data-alt="Saved revision compared against the current manuscript with total change counts">See the saved-revision comparison too ↗</button>@endif</figure>
</section>
@endforeach
<section class="process" id="process"><div class="wrap"><p class="eyebrow">FROM A SPARK TO A SECOND DRAFT</p><h2>Make it a conversation.<br><em>Then make it yours.</em></h2><div class="steps">
<article><span>01 / BEGIN</span><h3>Give the story a home.</h3><p>Name your book. Add a synopsis and style notes, or import an existing TXT or DOCX draft. Start typing wherever the story begins.</p></article>
<article><span>02 / EXPLORE</span><h3>Talk through the possibilities.</h3><p>Ask what a character wants, what a scene is missing, or how to carry an idea through the book. Bring codex entries into the conversation with @ mentions.</p></article>
<article><span>03 / SHAPE</span><h3>Work at your own scale.</h3><p>Edit by hand. Highlight a sentence for a focused rewrite. Or ask the companion to carry a change across the manuscript.</p></article>
<article><span>04 / DECIDE</span><h3>Read. Compare. Keep.</h3><p>Review the diff and choose what belongs in your story. Keep writing, revisit earlier revisions, and export your work as TXT or DOCX.</p></article>
</div></div></section>
<section class="closing wrap"><p class="eyebrow">THE NEXT SENTENCE IS WAITING</p><h2>Pull up a chair.</h2><p>You bring the story. We’ll keep a place for it.</p><a class="button" href="{{ auth()->check() ? route('dashboard') : route('register') }}">Start your next chapter ↗</a><small>No subscription checkout. Bring an OpenRouter key or start with the $1 demo allowance.</small></section>
</main>
@include('partials.public-footer')
<dialog id="screenshot-preview" aria-label="Full-size application screenshot"><button type="button" id="close-screenshot" aria-label="Close screenshot">×</button><img id="preview-image" alt=""><p>Actual {{ config('app.name') }} screenshot · Escape to close</p></dialog>
</body></html>
