<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('A thoughtful workspace for literary fiction. Write by hand, develop your story with AI, keep a living codex, and review every revision before it becomes your own.') }}">
    <title>{{ config('app.name') }} {{ __('— Your story. Your voice.') }}</title>
    @vite(['resources/css/landing.css', 'resources/js/landing.js'])
@if(config('app.favicon'))<link rel="icon" href="{{ asset(config('app.favicon')) }}">@endif
@include('partials.seo')
@include('partials.translations')
</head>
<body>
<a class="skip-link" href="#main">{{ __('Skip to content') }}</a>
<header class="landing-nav wrap">
    <a class="wordmark" href="{{ route('home') }}">@include('partials.brand')</a>
    <nav aria-label="{{ __('Main navigation') }}">@include('partials.language')<a href="#inside">{{ __('Inside the desk') }}</a><a href="#process">{{ __('The writing process') }}</a>@auth<a class="button small" href="{{ route('dashboard') }}">{{ __('My library ↗') }}</a>@else<a href="{{ route('login') }}">{{ __('Sign in') }}</a><a class="button small" href="{{ route('register') }}">{{ __('Start writing ↗') }}</a>@endauth</nav>
</header>
@php
$screenshot = fn (string $file) => asset('images/landing/'.str_replace('.png', app()->getLocale() === 'tr' ? '-TR.png' : '.png', $file));
$screenshotBook = app()->getLocale() === 'tr' ? 'Bir varmış bir yokmuş' : 'Star Trek meets Star Wars';
@endphp
<main id="main">
<section class="hero wrap">
    <p class="eyebrow">{{ __('A WORKSPACE FOR LITERARY FICTION') }}</p>
    <h1>{{ __('A place for your words.') }}<br><em>{{ __('A little room for possibility.') }}</em></h1>
    <p class="hero-copy">{{ __('For the first sentence, the difficult chapter, and the world that won’t leave you alone. Write with an AI companion beside you—and keep your voice at the heart of every page.') }}</p>
    <div class="hero-actions"><a class="button" href="{{ auth()->check() ? route('dashboard') : route('register') }}">{{ __('Take a seat at the desk') }} <span>↗</span></a><a class="text-link" href="#inside">{{ __('See how it works ↓') }}</a></div>
    <p class="quiet">{{ __('Your manuscript. Your world. Every edit yours to approve.') }}</p>
    <figure class="hero-shot"><button class="screenshot" data-preview="{{ $screenshot('manuscript.png') }}" aria-label="{{ __('Enlarge the manuscript screenshot') }}"><img src="{{ $screenshot('manuscript.png') }}" width="1600" height="1000" alt="{{ __('The real :book manuscript open in :app beside its AI conversation', ['book' => $screenshotBook, 'app' => config('app.name')]) }}" fetchpriority="high"></button><figcaption><span>{{ __('ON THE DESK') }}</span> {{ $screenshotBook }} <span>{{ __('Actual workspace · click any screenshot to explore') }}</span></figcaption></figure>
</section>
<section class="intro wrap" id="inside"><p class="eyebrow">{{ __('MEET YOUR WRITING ROOM') }}</p><h2>{{ __('One desk.') }}<br>{{ __('Every part of your story.') }}</h2><p>{{ __('A manuscript to shape. A companion to think with. A codex to keep the details close. Move between them without losing the thread.') }}</p></section>
@php
$features = [
 ['id'=>'manuscript','number'=>'01','label'=>__('THE MANUSCRIPT'),'title'=>__('Stay close to the page.'),'copy'=>__('Write directly in a quiet, serif editor. Shape a paragraph, mark a new chapter, or find your rhythm one sentence at a time. Page boundaries and generous margins give your draft room to breathe.'),'points'=>[__('Bold, italics, chapters, scenes, and an outline.'),__('Autosave, local draft recovery, and live word counts.'),__('Light, Dark, and warm Paper modes.')],'image'=>'manuscript.png','alt'=>__('Manuscript editor showing the book’s opening, page layout, formatting toolbar, and AI companion')],
 ['id'=>'conversation','number'=>'02','label'=>__('THE WRITING COMPANION'),'title'=>__('Think aloud. Find what happens next.'),'copy'=>__('Tell the companion what you’re trying to do. Explore a character’s motivation, question a scene, or ask for a different direction. Keep talking: your chosen conversation history travels with the next request.'),'points'=>[__('Regular chat receives the whole book and codex.'),__('The area around your cursor guides the focus without limiting broader edits.'),__('Include all past conversation, a few exchanges, or none.')],'image'=>'conversation.png','alt'=>__('Real writing companion conversation with the book and View Changes controls')],
 ['id'=>'selection','number'=>'03','label'=>__('A LITTLE CHANGE. OR A BIG ONE.'),'title'=>__('Choose how much to reimagine.'),'copy'=>__('Ask for a revision across the book, or highlight a passage to work on just those words. Selection editing sends the passage and nearby context while keeping everything outside the selection untouched.'),'points'=>[__('Manual writing and AI revisions share the same manuscript.'),__('Selected text comes with roughly 500 words of context on each side.'),__('Ask for a wider rewrite without a selection; inspect the proposal before applying it.')],'image'=>'selection.png','alt'=>__('Selected manuscript text with the selection-only editing indicator above chat')],
 ['id'=>'codex','number'=>'04','label'=>__('THE WORLD BEHIND THE WORDS'),'title'=>__('Keep your people and places close.'),'copy'=>__('Build a living reference for your book’s characters, places, objects, and lore. Add your own types, capture aliases and nicknames, and open an entry by clicking its name in the manuscript.'),'points'=>[__('Collapsible groups and editable descriptions.'),__('Use @ mentions to bring an entry into a conversation.'),__('Scan an existing story or ask chat to propose new codex entries.')],'image'=>'codex.png','alt'=>__('Actual codex entries for :book grouped alongside the manuscript', ['book' => $screenshotBook])],
 ['id'=>'names','number'=>'05','label'=>__('A NAME TO BEGIN WITH'),'title'=>__('Meet someone you haven’t written yet.'),'copy'=>__('Search real name datasets or generate fresh first-and-last-name combinations. Choose each name’s country independently, then apply your choice to the codex entry you’re working on.'),'points'=>[__('Search by letters, country, and optional gender.'),__('Random personal names use local data and no AI credits.'),__('For places, give the companion a direction and ask for ten suggestions.')],'image'=>'names.png','alt'=>__('The actual Names and places dialog with country and gender controls')],
 ['id'=>'revisions','number'=>'06','label'=>__('THE FINAL WORD IS YOURS'),'title'=>__('See the difference. Keep what feels right.'),'copy'=>__('Every AI edit begins as a proposal. Read the additions and removals, approve the changes you want, and reject the rest. Saved revisions let you look back at how the manuscript and codex have changed.'),'points'=>[__('New proposals open in a diff view.'),__('View Changes stays beside the assistant reply it belongs to.'),__('Compare saved revisions with the current draft and restore earlier work.')],'image'=>'ai-diff.png','alt'=>__('A real approved AI revision with original and proposed text side by side, showing removed and added words')],
 ['id'=>'transparency','number'=>'07','label'=>__('NO GUESSING WHAT WAS SENT'),'title'=>__('A clear view of your AI work.'),'copy'=>__('Choose an OpenRouter model, favorite the ones you return to, and filter by output price. Track spending by book and account, then open the LLM log to inspect individual requests and responses.'),'points'=>[__('Use your own API key or the account’s $1 demo allowance.'),__('Inspect full, formatted request and response JSON.'),__('Prompts over 30,000 words warn you before paid calls.')],'image'=>'llm-log.png','alt'=>__('Real LLM call history for this book with model, token usage, status, and cost')],
];
@endphp
@foreach ($features as $feature)
<section class="feature wrap {{ $loop->even ? 'reverse' : '' }}" id="{{ $feature['id'] }}">
    <div class="feature-copy"><p class="eyebrow"><span>{{ $feature['number'] }}</span> {{ $feature['label'] }}</p><h2>{!! nl2br(e($feature['title'])) !!}</h2><p>{{ $feature['copy'] }}</p><ul>@foreach ($feature['points'] as $point)<li>{{ $point }}</li>@endforeach</ul></div>
    <figure><button class="screenshot" data-preview="{{ $screenshot($feature['image']) }}" aria-label="{{ __('Enlarge :section screenshot', ['section' => $feature['label']]) }}"><img src="{{ $screenshot($feature['image']) }}" width="1600" height="1000" alt="{{ $feature['alt'] }}" loading="lazy"></button><figcaption>{{ $feature['label'] }} <span>{{ __('View full screenshot ↗') }}</span></figcaption>@if ($feature['id'] === 'revisions')<button class="revision-preview-link" data-preview="{{ $screenshot('revisions.png') }}" data-alt="{{ __('Saved revision compared against the current manuscript with total change counts') }}">{{ __('See the saved-revision comparison too ↗') }}</button>@endif</figure>
</section>
@endforeach
<section class="process" id="process"><div class="wrap"><p class="eyebrow">{{ __('FROM A SPARK TO A SECOND DRAFT') }}</p><h2>{{ __('Make it a conversation.') }}<br><em>{{ __('Then make it yours.') }}</em></h2><div class="steps">
<article><span>{{ __('01 / BEGIN') }}</span><h3>{{ __('Give the story a home.') }}</h3><p>{{ __('Name your book. Add a synopsis and style notes, or import an existing TXT or DOCX draft. Start typing wherever the story begins.') }}</p></article>
<article><span>{{ __('02 / EXPLORE') }}</span><h3>{{ __('Talk through the possibilities.') }}</h3><p>{{ __('Ask what a character wants, what a scene is missing, or how to carry an idea through the book. Bring codex entries into the conversation with @ mentions.') }}</p></article>
<article><span>{{ __('03 / SHAPE') }}</span><h3>{{ __('Work at your own scale.') }}</h3><p>{{ __('Edit by hand. Highlight a sentence for a focused rewrite. Or ask the companion to carry a change across the manuscript.') }}</p></article>
<article><span>{{ __('04 / DECIDE') }}</span><h3>{{ __('Read. Compare. Keep.') }}</h3><p>{{ __('Review the diff and choose what belongs in your story. Keep writing, revisit earlier revisions, and export your work as TXT or DOCX.') }}</p></article>
</div></div></section>
<section class="closing wrap"><p class="eyebrow">{{ __('THE NEXT SENTENCE IS WAITING') }}</p><h2>{{ __('Pull up a chair.') }}</h2><p>{{ __('You bring the story. We’ll keep a place for it.') }}</p><a class="button" href="{{ auth()->check() ? route('dashboard') : route('register') }}">{{ __('Start your next chapter ↗') }}</a><small>{{ __('No subscription checkout. Bring an OpenRouter key or start with the $1 demo allowance.') }}</small></section>
</main>
@include('partials.public-footer')
<dialog id="screenshot-preview" aria-label="{{ __('Full-size application screenshot') }}"><button type="button" id="close-screenshot" aria-label="{{ __('Close screenshot') }}">×</button><img id="preview-image" alt=""><p>{{ __('Actual') }} {{ config('app.name') }} {{ __('screenshot · Escape to close') }}</p></dialog>
</body></html>
