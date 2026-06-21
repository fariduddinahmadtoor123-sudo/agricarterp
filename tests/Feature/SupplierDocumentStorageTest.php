<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Services\Contacts\SupplierDocumentStorage;
use App\Services\Contacts\SupplierPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupplierDocumentStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_saves_profile_photo_on_create(): void
    {
        $path = 'contacts/suppliers/documents/profile.jpg';
        Storage::disk('local')->put($path, 'profile-image');

        $supplier = app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'documents' => [
                'profile_photo' => $path,
                'card_front' => null,
                'card_back' => null,
            ],
        ]));

        $this->assertSame($path, $supplier->document?->profile_photo_path);
        $this->assertTrue(Storage::disk('local')->exists($path));
    }

    public function test_from_model_includes_profile_photo_for_view(): void
    {
        $path = 'contacts/suppliers/documents/profile.jpg';
        Storage::disk('local')->put($path, 'profile-image');

        $supplier = app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'documents' => [
                'profile_photo' => $path,
                'card_front' => null,
                'card_back' => null,
            ],
        ]));

        $state = \App\Filament\Contacts\Schemas\SupplierForm::fromModel($supplier);

        $this->assertSame($path, $state['documents']['profile_photo']);
    }

    public function test_replacing_profile_photo_deletes_previous_file(): void
    {
        $oldPath = 'contacts/suppliers/documents/old-profile.jpg';
        $newPath = 'contacts/suppliers/documents/new-profile.jpg';

        Storage::disk('local')->put($oldPath, 'old');
        Storage::disk('local')->put($newPath, 'new');

        $supplier = app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'documents' => [
                'profile_photo' => $oldPath,
                'card_front' => null,
                'card_back' => null,
            ],
        ]));

        app(SupplierPersistenceService::class)->update($supplier, $this->supplierPayload([
            'documents' => [
                'profile_photo' => $newPath,
                'card_front' => null,
                'card_back' => null,
            ],
        ]));

        $this->assertFalse(Storage::disk('local')->exists($oldPath));
        $this->assertTrue(Storage::disk('local')->exists($newPath));
        $this->assertSame($newPath, $supplier->fresh()->document?->profile_photo_path);
    }

    public function test_clearing_card_front_deletes_previous_file(): void
    {
        $cardFrontPath = 'contacts/suppliers/documents/card-front.jpg';
        Storage::disk('local')->put($cardFrontPath, 'card-front');

        $supplier = app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'documents' => [
                'profile_photo' => null,
                'card_front' => $cardFrontPath,
                'card_back' => null,
            ],
        ]));

        app(SupplierPersistenceService::class)->update($supplier, $this->supplierPayload([
            'documents' => [
                'profile_photo' => null,
                'card_front' => null,
                'card_back' => null,
            ],
        ]));

        $this->assertFalse(Storage::disk('local')->exists($cardFrontPath));
        $this->assertNull($supplier->fresh()->document?->card_front_path);
    }

    public function test_replacing_card_back_deletes_previous_file(): void
    {
        $oldPath = 'contacts/suppliers/documents/old-back.jpg';
        $newPath = 'contacts/suppliers/documents/new-back.jpg';

        Storage::disk('local')->put($oldPath, 'old-back');
        Storage::disk('local')->put($newPath, 'new-back');

        $supplier = app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'documents' => [
                'profile_photo' => null,
                'card_front' => null,
                'card_back' => $oldPath,
            ],
        ]));

        app(SupplierPersistenceService::class)->update($supplier, $this->supplierPayload([
            'documents' => [
                'profile_photo' => null,
                'card_front' => null,
                'card_back' => $newPath,
            ],
        ]));

        $this->assertFalse(Storage::disk('local')->exists($oldPath));
        $this->assertTrue(Storage::disk('local')->exists($newPath));
    }

    public function test_cleanup_replaced_files_skips_when_path_unchanged(): void
    {
        $path = 'contacts/suppliers/documents/stable.jpg';
        Storage::disk('local')->put($path, 'stable');

        $supplier = app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'documents' => [
                'profile_photo' => $path,
                'card_front' => null,
                'card_back' => null,
            ],
        ]));

        app(SupplierPersistenceService::class)->update($supplier, $this->supplierPayload([
            'business_name' => 'Renamed Supplier',
            'documents' => [
                'profile_photo' => $path,
                'card_front' => null,
                'card_back' => null,
            ],
        ]));

        $this->assertTrue(Storage::disk('local')->exists($path));
    }

    public function test_document_storage_delete_if_exists_is_safe_for_missing_files(): void
    {
        app(SupplierDocumentStorage::class)->deleteIfExists('contacts/suppliers/documents/missing.jpg');

        $this->assertTrue(true);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function supplierPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'supplier_type' => 'local',
            'status' => Supplier::STATUS_ACTIVE,
            'country' => 'Pakistan',
            'city' => 'Lahore',
            'full_address' => 'Test Address',
            'business_name' => 'Test Supplier',
            'contact_name' => 'John Doe',
            'mobile_number' => '03111234567',
            'credit_limit' => 1000,
            'opening_balance' => 500,
            'bank_accounts' => [
                [
                    'bank_name' => 'HBL',
                    'branch_name' => 'Main',
                    'account_title' => 'Test Supplier',
                    'iban_account_number' => 'PK00HABB0000001123456701',
                ],
            ],
            'urdu' => [],
            'additional_contacts' => [],
            'documents' => [
                'profile_photo' => null,
                'card_front' => null,
                'card_back' => null,
            ],
        ], $overrides);
    }
}
