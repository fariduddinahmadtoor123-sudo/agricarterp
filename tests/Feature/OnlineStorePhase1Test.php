<?php

namespace Tests\Feature;

use App\Models\OnlineStore\StoreContactMessage;
use App\Models\OnlineStore\StorePage;
use App\Models\User;
use App\Services\OnlineStore\StoreFrontSettingsPersistenceService;
use App\Services\OnlineStore\StoreFrontSettingsResolver;
use App\Services\OnlineStore\StorePagePersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OnlineStorePhase1Test extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_list_loads(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/online-store/pages')
            ->assertOk();
    }

    public function test_admin_store_front_settings_loads(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/online-store/theme-settings')
            ->assertOk();
    }

    public function test_can_create_and_view_published_page(): void
    {
        $page = app(StorePagePersistenceService::class)->create([
            'title_en' => 'About Us',
            'title_ur' => 'ہمارے بارے میں',
            'slug' => 'about-us',
            'content_en' => '<p>About Agricart</p>',
            'content_ur' => '<p>اگرکارٹ کے بارے میں</p>',
            'is_published' => true,
        ]);

        $this->get(route('store.page', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('About Us')
            ->assertSee('About Agricart');
    }

    public function test_draft_page_is_not_public(): void
    {
        app(StorePagePersistenceService::class)->create([
            'title_en' => 'Draft Page',
            'title_ur' => 'ڈرافٹ',
            'slug' => 'draft-page',
            'content_en' => '<p>Hidden</p>',
            'content_ur' => '',
            'is_published' => false,
        ]);

        $this->get(route('store.page', ['slug' => 'draft-page']))
            ->assertNotFound();
    }

    public function test_store_front_settings_appear_on_homepage(): void
    {
        app(StoreFrontSettingsPersistenceService::class)->save([
            'top_bar_left' => 'Test Left',
            'top_bar_center' => 'Test Center',
            'top_bar_right' => 'Test Right',
            'ticker_en' => 'Ticker EN',
            'ticker_ur' => 'Ticker UR',
            'homepage_categories_per_row' => '4',
            'social_links' => [],
            'header_navigation' => [],
            'footer_logo' => [],
            'footer_logo_removed' => false,
            'footer_about_en' => 'Trusted Catalog',
            'footer_about_ur' => 'قابل اعتماد کیٹلاگ',
            'footer_quick_links' => [],
            'footer_legal_links' => [],
            'contact_email' => 'shop@example.com',
            'contact_phone' => '+923001234567',
            'map_embed_url' => null,
            'copyright_line' => '© Test Store',
        ]);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('Test Center')
            ->assertSee('Ticker EN')
            ->assertSee('Trusted Catalog')
            ->assertSee('shop@example.com');
    }

    public function test_contact_form_stores_message(): void
    {
        $this->post(route('store.contact'), [
            'name' => 'Ali',
            'email' => 'ali@example.com',
            'message' => 'Hello from the storefront',
        ])->assertRedirect();

        $this->assertDatabaseHas('store_contact_messages', [
            'name' => 'Ali',
            'email' => 'ali@example.com',
        ]);
    }

    public function test_map_embed_iframe_code_is_normalized_for_display(): void
    {
        $iframe = '<iframe src="https://www.google.com/maps/embed?pb=example" width="600" height="450"></iframe>';

        app(StoreFrontSettingsPersistenceService::class)->save([
            'top_bar_left' => 'L',
            'top_bar_center' => 'C',
            'top_bar_right' => 'R',
            'ticker_en' => '',
            'ticker_ur' => '',
            'homepage_categories_per_row' => '5',
            'social_links' => [],
            'header_navigation' => [],
            'footer_logo' => [],
            'footer_logo_removed' => false,
            'footer_about_en' => 'About',
            'footer_about_ur' => '',
            'footer_quick_links' => [],
            'footer_legal_links' => [],
            'contact_email' => null,
            'contact_phone' => null,
            'map_embed_url' => $iframe,
            'copyright_line' => null,
        ]);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('https://www.google.com/maps/embed?pb=example', false);
    }

    public function test_footer_logo_is_served_from_storage(): void
    {
        Storage::fake('local');

        $path = 'online-store/footer/test-logo.webp';
        Storage::disk('local')->put($path, 'logo-image');

        app(StoreFrontSettingsPersistenceService::class)->save([
            'top_bar_left' => 'L',
            'top_bar_center' => 'C',
            'top_bar_right' => 'R',
            'ticker_en' => '',
            'ticker_ur' => '',
            'homepage_categories_per_row' => '5',
            'social_links' => [],
            'header_navigation' => [],
            'footer_logo' => [$path],
            'footer_logo_removed' => false,
            'footer_about_en' => 'About',
            'footer_about_ur' => '',
            'footer_quick_links' => [],
            'footer_legal_links' => [],
            'contact_email' => null,
            'contact_phone' => null,
            'map_embed_url' => null,
            'copyright_line' => null,
        ]);

        $logoUrl = app(\App\Services\OnlineStore\StoreFrontSettingsResolver::class)->forStorefront()['footer_logo_url'];

        $this->assertNotNull($logoUrl);

        $this->get($logoUrl)->assertOk();
        $this->get(route('catalog.index'))->assertOk()->assertSee($logoUrl, false);
    }

    public function test_footer_logo_url_includes_app_subdirectory_when_configured(): void
    {
        Storage::fake('local');

        $path = 'online-store/footer/subdir-logo.webp';
        Storage::disk('local')->put($path, 'logo-image');

        config(['app.url' => 'http://localhost/agricarterp/public']);

        app(StoreFrontSettingsPersistenceService::class)->save([
            'top_bar_left' => 'L',
            'top_bar_center' => 'C',
            'top_bar_right' => 'R',
            'ticker_en' => '',
            'ticker_ur' => '',
            'homepage_categories_per_row' => '5',
            'social_links' => [],
            'header_navigation' => [],
            'footer_logo' => [$path],
            'footer_logo_removed' => false,
            'footer_about_en' => 'About',
            'footer_about_ur' => '',
            'footer_quick_links' => [],
            'footer_legal_links' => [],
            'contact_email' => null,
            'contact_phone' => null,
            'map_embed_url' => null,
            'copyright_line' => null,
        ]);

        $logoUrl = $this->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->get(route('catalog.index'))
            ->assertOk()
            ->viewData('storefront')['footer_logo_url'];

        $this->assertStringStartsWith('http://localhost/agricarterp/public/store/footer-logo', $logoUrl);

        $this->get('/store/footer-logo?' . http_build_query(['path' => $path]))->assertOk();
    }

    public function test_catalog_stylesheet_url_includes_app_subdirectory_when_configured(): void
    {
        config(['app.url' => 'http://localhost/agricarterp/public']);

        $html = $this->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->get(route('catalog.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '#href="http://localhost/agricarterp/public/css/catalog\.css\?v=[^"]+"#',
            $html,
        );
    }

    public function test_footer_uses_five_column_grid_when_map_is_absent(): void
    {
        app(StoreFrontSettingsPersistenceService::class)->save([
            'top_bar_left' => 'L',
            'top_bar_center' => 'C',
            'top_bar_right' => 'R',
            'ticker_en' => '',
            'ticker_ur' => '',
            'homepage_categories_per_row' => '5',
            'social_links' => [],
            'header_navigation' => [],
            'footer_logo' => [],
            'footer_logo_removed' => false,
            'footer_about_en' => 'About',
            'footer_about_ur' => '',
            'footer_quick_links' => [],
            'footer_legal_links' => [],
            'contact_email' => 'shop@example.com',
            'contact_phone' => null,
            'map_embed_url' => null,
            'copyright_line' => '© Test Store',
        ]);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('store-footer__columns--no-map', false);
    }

    public function test_header_navigation_resolves_published_page(): void
    {
        $page = StorePage::query()->create([
            'title_en' => 'Home',
            'title_ur' => 'ہوم',
            'slug' => 'home',
            'content_en' => '<p>Home page</p>',
            'content_ur' => '',
            'is_published' => true,
        ]);

        app(StoreFrontSettingsPersistenceService::class)->save([
            'top_bar_left' => 'L',
            'top_bar_center' => 'C',
            'top_bar_right' => 'R',
            'ticker_en' => '',
            'ticker_ur' => '',
            'homepage_categories_per_row' => '5',
            'social_links' => [],
            'header_navigation' => [
                ['store_page_id' => $page->id],
            ],
            'footer_logo' => [],
            'footer_logo_removed' => false,
            'footer_about_en' => 'About',
            'footer_about_ur' => '',
            'footer_quick_links' => [],
            'footer_legal_links' => [],
            'contact_email' => null,
            'contact_phone' => null,
            'map_embed_url' => null,
            'copyright_line' => null,
        ]);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee(route('store.page', ['slug' => 'home']), false)
            ->assertSee('Home');
    }

    public function test_page_picker_lists_published_and_draft_pages(): void
    {
        $published = StorePage::query()->create([
            'title_en' => 'About Us',
            'title_ur' => 'ہمارے بارے میں',
            'slug' => 'about-us',
            'content_en' => '<p>About</p>',
            'content_ur' => '',
            'is_published' => true,
        ]);

        StorePage::query()->create([
            'title_en' => 'Draft Policy',
            'title_ur' => 'ڈرافٹ',
            'slug' => 'draft-policy',
            'content_en' => '<p>Draft</p>',
            'content_ur' => '',
            'is_published' => false,
        ]);

        $resolver = app(\App\Services\OnlineStore\StorePageLinkResolver::class);

        $options = $resolver->pageOptionsForPicker();

        $this->assertArrayHasKey((string) $published->id, $options);
        $this->assertSame('About Us', $options[(string) $published->id]);
        $this->assertContains('Draft Policy (Draft)', $options);

        $search = $resolver->searchPagesForPicker('about');

        $this->assertSame('About Us', $search[(string) $published->id]);
    }

    public function test_draft_page_links_are_saved_but_not_shown_on_storefront(): void
    {
        $page = StorePage::query()->create([
            'title_en' => 'About Us',
            'title_ur' => 'ہمارے بارے میں',
            'slug' => 'about-us',
            'content_en' => '<p>About</p>',
            'content_ur' => '',
            'is_published' => false,
        ]);

        app(StoreFrontSettingsPersistenceService::class)->save([
            'top_bar_left' => 'L',
            'top_bar_center' => 'C',
            'top_bar_right' => 'R',
            'ticker_en' => '',
            'ticker_ur' => '',
            'homepage_categories_per_row' => '5',
            'social_links' => [],
            'header_navigation' => [
                ['store_page_id' => $page->id],
            ],
            'footer_logo' => [],
            'footer_logo_removed' => false,
            'footer_about_en' => 'About',
            'footer_about_ur' => '',
            'footer_quick_links' => [
                ['store_page_id' => $page->id],
            ],
            'footer_legal_links' => [
                ['store_page_id' => $page->id],
            ],
            'contact_email' => null,
            'contact_phone' => null,
            'map_embed_url' => null,
            'copyright_line' => null,
        ]);

        $settings = app(StoreFrontSettingsResolver::class)->model();
        $storefront = app(StoreFrontSettingsResolver::class)->forStorefront();

        $this->assertCount(1, $settings->header_navigation);
        $this->assertSame($page->id, $settings->header_navigation[0]['store_page_id']);
        $this->assertSame([], $storefront['header_navigation']);
        $this->assertSame([], $storefront['footer_quick_links']);
        $this->assertSame([], $storefront['footer_legal_links']);

        $page->update(['is_published' => true]);

        $storefront = app(StoreFrontSettingsResolver::class)->forStorefront();

        $this->assertCount(1, $storefront['header_navigation']);
        $this->assertSame('About Us', $storefront['header_navigation'][0]['label']);
        $this->assertCount(1, $storefront['footer_quick_links']);
        $this->assertCount(1, $storefront['footer_legal_links']);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('About Us')
            ->assertSee(route('store.page', ['slug' => 'about-us']), false);
    }

    public function test_navigation_label_uses_page_title_automatically(): void
    {
        $page = StorePage::query()->create([
            'title_en' => 'Become A Partner',
            'title_ur' => 'پارٹنر بنیں',
            'slug' => 'become-a-partner',
            'content_en' => '<p>Partner</p>',
            'content_ur' => '',
            'is_published' => true,
        ]);

        $links = app(\App\Services\OnlineStore\StorePageLinkResolver::class)->resolveLinks([
            ['store_page_id' => $page->id],
        ]);

        $this->assertSame('Become A Partner', $links[0]['label']);
    }
}
