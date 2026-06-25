<?php

namespace App\Support\Settings;

use App\Models\Backup;
use App\Models\CompanySetting;
use App\Models\Role;
use App\Models\User;
use App\Services\Settings\AiSettingResolver;
use App\Services\Settings\PrintingSettingResolver;
use App\Services\Settings\PurchasePricingSettingResolver;
use App\Support\Navigation\ModulePageRegistry;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Schema;

class SettingsOverviewPresenter
{
    public function __construct(
        protected ModulePageRegistry $pageRegistry,
        protected AiSettingResolver $aiSettings,
        protected PrintingSettingResolver $printingSettings,
        protected PurchasePricingSettingResolver $purchasePricingSettings,
    ) {}

    /**
     * @return list<array{key: string, label: string, value: int, hint: string, icon: mixed, tone?: string}>
     */
    public function stats(): array
    {
        $lastBackup = Backup::query()
            ->where('status', Backup::STATUS_COMPLETED)
            ->latest('completed_at')
            ->first();

        return [
            [
                'key' => 'staff_users',
                'label' => 'Staff Users',
                'value' => Schema::hasTable('users') ? User::query()->count() : 0,
                'hint' => 'Registered ERP user accounts',
                'icon' => Heroicon::OutlinedUsers,
            ],
            [
                'key' => 'roles',
                'label' => 'Roles',
                'value' => Schema::hasTable('roles') ? Role::query()->count() : 0,
                'hint' => 'Permission roles available in the system',
                'icon' => Heroicon::OutlinedShieldCheck,
            ],
            [
                'key' => 'backups',
                'label' => 'Completed Backups',
                'value' => Schema::hasTable('backups')
                    ? Backup::query()->where('status', Backup::STATUS_COMPLETED)->count()
                    : 0,
                'hint' => $lastBackup
                    ? 'Last backup: ' . $lastBackup->completed_at?->format('d M Y H:i')
                    : 'No completed backup yet',
                'icon' => Heroicon::OutlinedArchiveBoxArrowDown,
            ],
            [
                'key' => 'modules_ready',
                'label' => 'Configured Modules',
                'value' => $this->configuredModuleCount(),
                'hint' => 'Store, pricing, printing, and AI readiness out of 4',
                'icon' => Heroicon::OutlinedCheckCircle,
            ],
        ];
    }

    protected function configuredModuleCount(): int
    {
        $count = 0;

        if (CompanySetting::query()->exists()) {
            $count++;
        }

        if ($this->purchasePricingSettings->settings() !== null) {
            $count++;
        }

        if ($this->printingSettings->settings() !== null) {
            $count++;
        }

        if ($this->aiSettings->hasApiKey() && $this->aiSettings->isEnabled()) {
            $count++;
        }

        return $count;
    }

    /**
     * @return list<array{key: string, title: string, icon: mixed, status: string, rows: list<array{label: string, value: string}>, url: string}>
     */
    public function configurationCards(): array
    {
        return [
            $this->storeCard(),
            $this->purchasePricingCard(),
            $this->aiCard(),
            $this->printingCard(),
            $this->accessCard(),
            $this->backupCard(),
        ];
    }

    /**
     * @return list<array{label: string, description: string, icon: mixed, url: string}>
     */
    public function quickLinks(): array
    {
        $module = 'settings';

        return [
            [
                'label' => 'Store Setting',
                'description' => 'Company profile, currency, tax IDs, and contact details',
                'icon' => Heroicon::OutlinedCog6Tooth,
                'url' => $this->pageRegistry->submenuUrl($module, 'general-settings'),
            ],
            [
                'label' => 'Purchase Pricing',
                'description' => 'Markup tiers and purchase price update rules',
                'icon' => Heroicon::OutlinedCurrencyDollar,
                'url' => $this->pageRegistry->submenuUrl($module, 'purchase-pricing'),
            ],
            [
                'label' => 'AI Settings',
                'description' => 'OpenRouter model, API key, and enrichment batch size',
                'icon' => Heroicon::OutlinedSparkles,
                'url' => $this->pageRegistry->submenuUrl($module, 'ai-configuration'),
            ],
            [
                'label' => 'Printing',
                'description' => 'Documents, purchase sheets, price tags, and POS receipts',
                'icon' => Heroicon::OutlinedPrinter,
                'url' => $this->pageRegistry->submenuUrl($module, 'universal-printing'),
            ],
            [
                'label' => 'System',
                'description' => 'Application runtime, infrastructure, and health checks',
                'icon' => Heroicon::OutlinedServer,
                'url' => $this->pageRegistry->submenuUrl($module, 'system'),
            ],
            [
                'label' => 'Backups',
                'description' => 'Create, download, restore, and schedule ERP backups',
                'icon' => Heroicon::OutlinedArchiveBoxArrowDown,
                'url' => $this->pageRegistry->submenuUrl($module, 'system-backups'),
            ],
        ];
    }

    /**
     * @return array{key: string, title: string, icon: mixed, status: string, rows: list<array{label: string, value: string}>, url: string}
     */
    protected function storeCard(): array
    {
        $store = CompanySetting::query()->first();

        return [
            'key' => 'store',
            'title' => 'Store Setting',
            'icon' => Heroicon::OutlinedBuildingStorefront,
            'status' => $store ? 'configured' : 'missing',
            'rows' => $store
                ? [
                    ['label' => 'Store name', 'value' => (string) $store->name_en],
                    ['label' => 'Currency', 'value' => (string) $store->currency],
                    ['label' => 'Timezone', 'value' => (string) ($store->timezone ?: config('app.timezone'))],
                    ['label' => 'Website', 'value' => (string) ($store->website_url ?: '—')],
                ]
                : [
                    ['label' => 'Status', 'value' => 'Not configured'],
                ],
            'url' => $this->pageRegistry->submenuUrl('settings', 'general-settings'),
        ];
    }

    /**
     * @return array{key: string, title: string, icon: mixed, status: string, rows: list<array{label: string, value: string}>, url: string}
     */
    protected function purchasePricingCard(): array
    {
        $setting = $this->purchasePricingSettings->settings();
        $markups = $this->purchasePricingSettings->markupPercentagesForRows();

        return [
            'key' => 'purchase-pricing',
            'title' => 'Purchase Pricing',
            'icon' => Heroicon::OutlinedCurrencyDollar,
            'status' => $setting ? 'configured' : 'missing',
            'rows' => [
                ['label' => 'Update prices from purchases', 'value' => $setting?->update_product_prices_from_purchases ? 'Yes' : 'No'],
                ['label' => 'Wholesale markup', 'value' => $markups['wholesale'] . '%'],
                ['label' => 'Super wholesale markup', 'value' => $markups['super_wholesale'] . '%'],
                ['label' => 'Distributor markup', 'value' => $markups['distributor'] . '%'],
            ],
            'url' => $this->pageRegistry->submenuUrl('settings', 'purchase-pricing'),
        ];
    }

    /**
     * @return array{key: string, title: string, icon: mixed, status: string, rows: list<array{label: string, value: string}>, url: string}
     */
    protected function aiCard(): array
    {
        $resolver = $this->aiSettings;

        return [
            'key' => 'ai',
            'title' => 'AI Settings',
            'icon' => Heroicon::OutlinedSparkles,
            'status' => $resolver->hasApiKey() ? 'configured' : 'missing',
            'rows' => [
                ['label' => 'Enrichment', 'value' => $resolver->isEnabled() ? 'Enabled' : 'Disabled'],
                ['label' => 'Vision model', 'value' => $resolver->resolvedVisionModel()],
                ['label' => 'Batch limit', 'value' => (string) $resolver->batchLimit()],
                ['label' => 'API key', 'value' => $resolver->hasApiKey() ? 'Configured' : 'Missing'],
            ],
            'url' => $this->pageRegistry->submenuUrl('settings', 'ai-configuration'),
        ];
    }

    /**
     * @return array{key: string, title: string, icon: mixed, status: string, rows: list<array{label: string, value: string}>, url: string}
     */
    protected function printingCard(): array
    {
        $resolver = $this->printingSettings;
        $label = $resolver->priceTagLabel();
        $papers = $resolver->documentPaperOptions();
        $docPaper = $papers[$resolver->defaultDocumentPaper()] ?? strtoupper($resolver->defaultDocumentPaper());
        $purchasePaper = $papers[$resolver->defaultPurchaseInvoicePaper()] ?? strtoupper($resolver->defaultPurchaseInvoicePaper());
        $labelPresets = $resolver->labelPresetOptions();
        $labelPresetLabel = $labelPresets[$label['preset']] ?? $label['preset'];
        $pos = $resolver->posReceiptProfile();
        $thermalOptions = $resolver->thermalReceiptOptions();

        return [
            'key' => 'printing',
            'title' => 'Printing',
            'icon' => Heroicon::OutlinedPrinter,
            'status' => $resolver->settings() ? 'configured' : 'defaults',
            'rows' => [
                ['label' => 'Default document paper', 'value' => $docPaper],
                ['label' => 'Purchase invoice paper', 'value' => $purchasePaper],
                ['label' => 'Price tag label', 'value' => $labelPresetLabel . ' (' . $label['width_mm'] . '×' . $label['height_mm'] . ' mm)'],
                ['label' => 'POS receipt', 'value' => $thermalOptions[$pos['profile']] ?? $pos['profile']],
            ],
            'url' => $this->pageRegistry->submenuUrl('settings', 'universal-printing'),
        ];
    }

    /**
     * @return array{key: string, title: string, icon: mixed, status: string, rows: list<array{label: string, value: string}>, url: string}
     */
    protected function accessCard(): array
    {
        $activeUsers = Schema::hasTable('users')
            ? User::query()->where('status', User::STATUS_ACTIVE)->count()
            : 0;

        return [
            'key' => 'access',
            'title' => 'Users & Permission',
            'icon' => Heroicon::OutlinedShieldCheck,
            'status' => 'configured',
            'rows' => [
                ['label' => 'Active users', 'value' => (string) $activeUsers],
                ['label' => 'Total users', 'value' => (string) (Schema::hasTable('users') ? User::query()->count() : 0)],
                ['label' => 'Roles', 'value' => (string) (Schema::hasTable('roles') ? Role::query()->count() : 0)],
            ],
            'url' => $this->pageRegistry->submenuUrl('settings', 'users'),
        ];
    }

    /**
     * @return array{key: string, title: string, icon: mixed, status: string, rows: list<array{label: string, value: string}>, url: string}
     */
    protected function backupCard(): array
    {
        $lastBackup = Backup::query()
            ->where('status', Backup::STATUS_COMPLETED)
            ->latest('completed_at')
            ->first();

        $scheduleEnabled = Schema::hasTable('backup_schedules')
            && \App\Models\BackupSchedule::query()->where('enabled', true)->exists();

        return [
            'key' => 'backups',
            'title' => 'Backups',
            'icon' => Heroicon::OutlinedArchiveBoxArrowDown,
            'status' => $lastBackup ? 'configured' : 'missing',
            'rows' => [
                ['label' => 'Last completed backup', 'value' => $lastBackup?->completed_at?->format('d M Y H:i') ?? '—'],
                ['label' => 'Last file', 'value' => $lastBackup?->file_name ?? '—'],
                ['label' => 'Auto schedule', 'value' => $scheduleEnabled ? 'Enabled' : 'Disabled'],
            ],
            'url' => $this->pageRegistry->submenuUrl('settings', 'system-backups'),
        ];
    }
}
