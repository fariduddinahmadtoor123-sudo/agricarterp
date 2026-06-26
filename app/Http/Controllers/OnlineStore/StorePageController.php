<?php

namespace App\Http\Controllers\OnlineStore;

use App\Http\Controllers\Controller;
use App\Models\OnlineStore\StoreContactMessage;
use App\Models\OnlineStore\StorePage;
use App\Services\OnlineStore\StoreFooterLogoStorage;
use App\Services\OnlineStore\StoreFrontSettingsResolver;
use App\Services\OnlineStore\StorePagePersistenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorePageController extends Controller
{
    public function show(string $slug, StorePagePersistenceService $pages): View
    {
        $page = StorePage::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return view('store.page', [
            'page' => $page,
            'contentHtml' => $pages->renderContent($page->content_en),
            'contentHtmlUr' => $pages->renderContent($page->content_ur),
        ]);
    }

    public function contact(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        StoreContactMessage::query()->create($validated);

        return back()->with('contact_sent', true);
    }

    public function footerLogo(Request $request, StoreFooterLogoStorage $storage): StreamedResponse
    {
        $located = $storage->locate($request->query('path'));

        abort_unless($located !== null, 404);

        $response = Storage::disk($located['disk'])->response($located['path']);
        $response->headers->set('Cache-Control', 'public, max-age=86400');

        return $response;
    }
}
