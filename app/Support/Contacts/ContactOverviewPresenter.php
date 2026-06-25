<?php

namespace App\Support\Contacts;

use App\Models\ContactMobileNumber;
use App\Models\Customer;
use App\Models\CustomerBankAccount;
use App\Models\CustomerDocument;
use App\Models\Supplier;
use App\Models\SupplierBankAccount;
use App\Models\SupplierDocument;
use App\Support\Navigation\ModulePageRegistry;
use Filament\Support\Icons\Heroicon;

class ContactOverviewPresenter
{
    public function __construct(
        protected ModulePageRegistry $pageRegistry,
    ) {}

    /**
     * @return list<array{key: string, label: string, value: int, hint: string, icon: mixed, tone?: string}>
     */
    public function stats(): array
    {
        $activeSuppliers = Supplier::query()->active()->count();
        $archivedSuppliers = Supplier::onlyTrashed()->count();
        $activeCustomers = Customer::query()->count();
        $archivedCustomers = Customer::onlyTrashed()->count();
        $totalContacts = Supplier::query()->count() + $activeCustomers;

        return [
            [
                'key' => 'active_suppliers',
                'label' => 'Active Suppliers',
                'value' => $activeSuppliers,
                'hint' => 'Available for purchasing and supplier workflows',
                'icon' => Heroicon::OutlinedBuildingStorefront,
            ],
            [
                'key' => 'archived_suppliers',
                'label' => 'Archived Suppliers',
                'value' => $archivedSuppliers,
                'hint' => 'Soft-deleted suppliers with reserved codes',
                'icon' => Heroicon::OutlinedArchiveBox,
                'tone' => 'muted',
            ],
            [
                'key' => 'active_customers',
                'label' => 'Active Customers',
                'value' => $activeCustomers,
                'hint' => 'Live customer records in the directory',
                'icon' => Heroicon::OutlinedUserGroup,
            ],
            [
                'key' => 'archived_customers',
                'label' => 'Archived Customers',
                'value' => $archivedCustomers,
                'hint' => 'Soft-deleted customers with reserved codes',
                'icon' => Heroicon::OutlinedArchiveBox,
                'tone' => 'muted',
            ],
            [
                'key' => 'total_contacts',
                'label' => 'Total Contacts',
                'value' => $totalContacts,
                'hint' => 'All non-archived suppliers and customers',
                'icon' => Heroicon::OutlinedUsers,
            ],
            [
                'key' => 'total_bank_accounts',
                'label' => 'Total Bank Accounts',
                'value' => SupplierBankAccount::query()->count() + CustomerBankAccount::query()->count(),
                'hint' => 'Bank account rows across suppliers and customers',
                'icon' => Heroicon::OutlinedBuildingLibrary,
            ],
            [
                'key' => 'total_documents',
                'label' => 'Total Documents',
                'value' => SupplierDocument::query()->count() + CustomerDocument::query()->count(),
                'hint' => 'Uploaded identity and business documents',
                'icon' => Heroicon::OutlinedDocumentText,
            ],
            [
                'key' => 'total_mobile_numbers',
                'label' => 'Total Mobile Numbers',
                'value' => ContactMobileNumber::query()->count(),
                'hint' => 'Registered numbers including WhatsApp and additional',
                'icon' => Heroicon::OutlinedDevicePhoneMobile,
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, description: string, url: string, icon: mixed}>
     */
    public function quickLinks(): array
    {
        $moduleKey = 'contacts';
        $icons = config("agricart.modules.{$moduleKey}.submenu_icons", []);
        $labels = config("agricart.modules.{$moduleKey}.submenus", []);
        $descriptions = [
            'overview' => 'Contacts dashboard and live KPI summary',
            'suppliers' => 'Manage supplier profiles and purchasing contacts',
            'customers' => 'Manage customer profiles and sales contacts',
        ];

        $order = ['suppliers', 'customers', 'overview'];

        $links = [];

        foreach ($order as $key) {
            $links[] = [
                'key' => $key,
                'label' => $labels[$key] ?? ucfirst($key),
                'description' => $descriptions[$key] ?? 'Open workspace',
                'url' => $this->pageRegistry->submenuUrl($moduleKey, $key),
                'icon' => $icons[$key] ?? Heroicon::OutlinedUserGroup,
            ];
        }

        return $links;
    }
}
