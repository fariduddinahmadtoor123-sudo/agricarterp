<?php

namespace Tests\Feature;

use App\Support\Navigation\NavigationSearchIndex;
use App\Support\Navigation\NavigationSearcher;
use Tests\TestCase;

class NavigationSearchTest extends TestCase
{
    public function test_index_contains_expected_navigation_targets(): void
    {
        $entries = app(NavigationSearchIndex::class)->all();
        $breadcrumbs = array_column($entries, 'breadcrumb');

        $this->assertContains('Product Catalog > Brands', $breadcrumbs);
        $this->assertContains('Settings > Users', $breadcrumbs);
        $this->assertContains('Approvals > Staff > Users', $breadcrumbs);
        $this->assertContains('Finance & Accounts > Expenses', $breadcrumbs);
        $this->assertContains('Reports & Analytics > Expense Report', $breadcrumbs);
        $this->assertContains('Documentation > User Guide', $breadcrumbs);
    }

    public function test_searcher_finds_results_from_live_index(): void
    {
        $entries = app(NavigationSearchIndex::class)->all();
        $searcher = app(NavigationSearcher::class);

        $brandResults = array_column($searcher->search('brand', $entries), 'breadcrumb');
        $this->assertContains('Product Catalog > Brands', $brandResults);

        $userResults = array_column($searcher->search('user', $entries), 'breadcrumb');
        $this->assertContains('Settings > Users', $userResults);
        $this->assertContains('Approvals > Staff > Users', $userResults);

        $expenseResults = array_column($searcher->search('expense', $entries), 'breadcrumb');
        $this->assertContains('Finance & Accounts > Expenses', $expenseResults);
        $this->assertContains('Reports & Analytics > Expense Report', $expenseResults);
    }
}
