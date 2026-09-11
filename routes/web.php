<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\NameController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt', function () {
    $sitemap = rtrim(config('app.url'), '/').'/sitemap.xml';
    return response("User-agent: *\nAllow: /\nDisallow: /books\nDisallow: /api/\nDisallow: /dashboard\nDisallow: /account\nDisallow: /profile\n\nSitemap: ".$sitemap."\n", 200)->header('Content-Type', 'text/plain; charset=UTF-8');
});
Route::get('/sitemap.xml', function () {
    $base = rtrim(config('app.url'), '/');
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach (['/', '/privacy', '/terms'] as $path) {
        $xml .= '<url><loc>'.htmlspecialchars($base.$path, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></url>';
    }
    return response($xml.'</urlset>', 200)->header('Content-Type', 'application/xml; charset=UTF-8');
});
Route::view('/', 'welcome')->name('home');
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');
Route::post('/contact', [\App\Http\Controllers\ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [BookController::class, 'index'])->name('dashboard');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');
    Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');
    Route::get('/books/{book}/llm-log', [BookController::class, 'llmLogPage'])->name('books.llm-log');
    Route::get('/books/{book}/llm-log/{id}', [BookController::class, 'llmCallPage'])->name('books.llm-call');
    Route::get('/books/{book}/export/{format}', [BookController::class, 'export'])->name('books.export');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');
    Route::post('/books/{id}/recover', [BookController::class, 'recover'])->name('books.recover');
    Route::get('/account', [SettingsController::class, 'edit'])->name('settings');
    Route::patch('/account', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/profile', fn () => redirect()->route('settings'))->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::prefix('api')->group(function () {
        Route::get('/countries', [NameController::class, 'countries']);
        Route::get('/names', [NameController::class, 'index']);
        Route::get('/models', [SettingsController::class, 'models']);
        Route::post('/models/refresh', [SettingsController::class, 'refresh'])->middleware('throttle:6,1');
        Route::get('/books/{book}', [BookController::class, 'state']);
        Route::patch('/books/{book}', [BookController::class, 'update']);
        Route::post('/books/{book}/entries/{id?}', [BookController::class, 'entry']);
        Route::delete('/books/{book}/entries/{id}', [BookController::class, 'deleteEntry']);
        Route::post('/books/{book}/revisions/{id}/restore', [BookController::class, 'restore']);
        Route::get('/books/{book}/revisions/{id}', [BookController::class, 'revision']);
        Route::get('/books/{book}/llm-log', [BookController::class, 'llmLog']);
        Route::get('/books/{book}/llm-log/{id}', [BookController::class, 'llmCall']);
        Route::delete('/books/{book}/messages/{id}', [BookController::class, 'deleteMessage']);
        Route::post('/books/{book}/chat', [ChatController::class, 'send'])->middleware('throttle:12,1');
        Route::post('/books/{book}/proposals/{id}', [ChatController::class, 'approve']);
    });
});
require __DIR__.'/auth.php';
