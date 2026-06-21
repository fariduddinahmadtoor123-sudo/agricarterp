<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\Contacts\CustomerPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerDocumentStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_saves_profile_photo_on_create(): void
    {
        $path = 'contacts/customers/documents/profile.jpg';
        Storage::disk('local')->put($path, 'profile-image');

        $customer = app(CustomerPersistenceService::class)->create($this->customerPayload([
            'documents' => [
                'profile_photo' => $path,
            ],
        ]));

        $this->assertSame($path, $customer->document?->profile_photo_path);
    }

    public function test_replacing_profile_photo_deletes_previous_file(): void
    {
        $oldPath = 'contacts/customers/documents/old.jpg';
        $newPath = 'contacts/customers/documents/new.jpg';

        Storage::disk('local')->put($oldPath, 'old');
        Storage::disk('local')->put($newPath, 'new');

        $customer = app(CustomerPersistenceService::class)->create($this->customerPayload([
            'documents' => ['profile_photo' => $oldPath],
        ]));

        app(CustomerPersistenceService::class)->update($customer, $this->customerPayload([
            'documents' => ['profile_photo' => $newPath],
        ]));

        $this->assertFalse(Storage::disk('local')->exists($oldPath));
        $this->assertTrue(Storage::disk('local')->exists($newPath));
    }

    public function test_restore_returns_soft_deleted_customer(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $customer = app(CustomerPersistenceService::class)->create($this->customerPayload([
            'mobile_number' => '03001234567',
        ]));

        $code = $customer->customer_code;

        app(CustomerPersistenceService::class)->delete($customer);

        $restored = app(CustomerPersistenceService::class)->restore($customer->fresh());

        $this->assertFalse($restored->trashed());
        $this->assertSame($code, $restored->customer_code);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function customerPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'customer_name' => 'Test Customer',
            'mobile_number' => '03111234567',
            'bank_accounts' => [],
            'urdu' => [],
            'additional_contacts' => [],
            'documents' => [],
        ], $overrides);
    }
}
