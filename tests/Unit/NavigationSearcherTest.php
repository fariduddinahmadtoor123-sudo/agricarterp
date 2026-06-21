<?php

namespace Tests\Unit;

use App\Support\Navigation\NavigationSearcher;
use PHPUnit\Framework\TestCase;

class NavigationSearcherTest extends TestCase
{
  protected NavigationSearcher $searcher;

  protected function setUp(): void
  {
    parent::setUp();

    $this->searcher = new NavigationSearcher;
  }

  public function test_empty_query_returns_no_results(): void
  {
    $this->assertSame([], $this->searcher->search('', $this->sampleEntries()));
  }

  public function test_finds_brands_under_product_catalog(): void
  {
    $results = $this->searcher->search('brand', $this->sampleEntries());

    $this->assertNotEmpty($results);
    $this->assertSame('Product Catalog > Brands', $results[0]['breadcrumb']);
  }

  public function test_finds_users_in_settings_and_approvals(): void
  {
    $results = $this->searcher->search('user', $this->sampleEntries());
    $breadcrumbs = array_column($results, 'breadcrumb');

    $this->assertContains('Settings > Users', $breadcrumbs);
    $this->assertContains('Approvals > Staff > Users', $breadcrumbs);
  }

  public function test_finds_expense_pages_in_finance_and_reports(): void
  {
    $results = $this->searcher->search('expense', $this->sampleEntries());
    $breadcrumbs = array_column($results, 'breadcrumb');

    $this->assertContains('Finance & Accounts > Expenses', $breadcrumbs);
    $this->assertContains('Reports & Analytics > Expense Report', $breadcrumbs);
  }

  /**
   * @return array<int, array{id: string, label: string, breadcrumb: string, module: string, url: string, keywords: string, scopes: array<int, string>}>
   */
  protected function sampleEntries(): array
  {
    return [
      [
        'id' => 'product-catalog.brands',
        'label' => 'Brands',
        'breadcrumb' => 'Product Catalog > Brands',
        'module' => 'Product Catalog',
        'url' => '/admin/product-catalog/brands',
        'keywords' => 'product catalog brands',
        'scopes' => ['modules', 'pages'],
      ],
      [
        'id' => 'settings.users',
        'label' => 'Users',
        'breadcrumb' => 'Settings > Users',
        'module' => 'Settings',
        'url' => '/admin/settings/users',
        'keywords' => 'settings users',
        'scopes' => ['modules', 'pages', 'settings'],
      ],
      [
        'id' => 'approvals.staff.users',
        'label' => 'Users',
        'breadcrumb' => 'Approvals > Staff > Users',
        'module' => 'Approvals',
        'url' => '/admin/approvals/staff/users',
        'keywords' => 'approvals staff users',
        'scopes' => ['modules', 'pages', 'approvals'],
      ],
      [
        'id' => 'finance-accounts.expenses',
        'label' => 'Expenses',
        'breadcrumb' => 'Finance & Accounts > Expenses',
        'module' => 'Finance & Accounts',
        'url' => '/admin/finance-accounts/expenses',
        'keywords' => 'finance accounts expenses',
        'scopes' => ['modules', 'pages'],
      ],
      [
        'id' => 'reports-analytics.expense-report',
        'label' => 'Expense Report',
        'breadcrumb' => 'Reports & Analytics > Expense Report',
        'module' => 'Reports & Analytics',
        'url' => '/admin/reports-analytics/expense-report',
        'keywords' => 'reports analytics expense report',
        'scopes' => ['modules', 'pages', 'reports'],
      ],
    ];
  }
}
