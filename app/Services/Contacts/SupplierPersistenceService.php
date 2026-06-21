<?php

namespace App\Services\Contacts;

use App\Models\ContactMobileNumber;
use App\Models\Supplier;
use App\Models\SupplierBankAccount;
use App\Models\SupplierContact;
use App\Models\SupplierDocument;
use App\Support\Contacts\SupplierAuthorization;
use Illuminate\Support\Facades\DB;

class SupplierPersistenceService
{
    public function __construct(
        protected SupplierCodeGenerator $codeGenerator,
        protected SupplierMobileRegistry $mobileRegistry,
        protected SupplierDataValidator $dataValidator,
        protected SupplierDocumentStorage $documentStorage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Supplier
    {
        $data = $this->prepareData($data);

        $this->dataValidator->validate($data);

        $this->mobileRegistry->assertUnique(
            $this->mobileRegistry->collectEntriesFromFormData($data),
        );

        return DB::transaction(function () use ($data): Supplier {
            $supplier = Supplier::query()->create([
                ...$this->supplierAttributes($data),
                'supplier_code' => $this->codeGenerator->generate(),
            ]);

            $this->syncChildren($supplier, $data);

            return $supplier->fresh([
                'bankAccounts',
                'additionalContacts',
                'document',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        $data = $this->prepareData($data, $supplier);

        $this->dataValidator->validate($data);

        $this->mobileRegistry->assertUnique(
            $this->mobileRegistry->collectEntriesFromFormData($data),
            $supplier->id,
        );

        return DB::transaction(function () use ($supplier, $data): Supplier {
            $supplier->update($this->supplierAttributes($data, $supplier));

            $this->syncChildren($supplier, $data);

            return $supplier->fresh([
                'bankAccounts',
                'additionalContacts',
                'document',
            ]);
        });
    }

    public function delete(Supplier $supplier): void
    {
        if (! SupplierAuthorization::canDelete()) {
            abort(403);
        }

        $supplier->delete();
    }

    public function restore(Supplier $supplier): Supplier
    {
        if (! SupplierAuthorization::canRestore()) {
            abort(403);
        }

        if ($supplier->trashed()) {
            $supplier->restore();
        }

        return $supplier->fresh([
            'bankAccounts',
            'additionalContacts',
            'document',
        ]);
    }

    public function setInactive(Supplier $supplier): Supplier
    {
        if (! SupplierAuthorization::canInactivate()) {
            abort(403);
        }

        $supplier->update([
            'status' => Supplier::STATUS_INACTIVE,
        ]);

        return $supplier->fresh();
    }

    public function setActive(Supplier $supplier): Supplier
    {
        $supplier->update([
            'status' => Supplier::STATUS_ACTIVE,
        ]);

        return $supplier->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareData(array $data, ?Supplier $supplier = null): array
    {
        $data['additional_contacts'] = collect($data['additional_contacts'] ?? [])
            ->filter(fn (array $contact): bool => filled($contact['contact_person'] ?? null) || filled($contact['mobile_number'] ?? null))
            ->values()
            ->all();

        if (! array_key_exists('opening_balance_type', $data) || blank($data['opening_balance_type'])) {
            $data['opening_balance_type'] = $supplier?->opening_balance_type
                ?? Supplier::OPENING_BALANCE_CREDIT;
        }

        $data['status'] = $this->resolveStatus($data, $supplier);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveStatus(array $data, ?Supplier $supplier = null): string
    {
        if (! SupplierAuthorization::canInactivate()) {
            return $supplier?->status ?? Supplier::STATUS_ACTIVE;
        }

        $status = $data['status'] ?? Supplier::STATUS_ACTIVE;

        return in_array($status, [Supplier::STATUS_ACTIVE, Supplier::STATUS_INACTIVE], true)
            ? $status
            : Supplier::STATUS_ACTIVE;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function supplierAttributes(array $data, ?Supplier $supplier = null): array
    {
        $urdu = $data['urdu'] ?? [];

        return [
            'supplier_type' => $data['supplier_type'] ?? null,
            'status' => $data['status'] ?? Supplier::STATUS_ACTIVE,
            'country' => $data['country'],
            'city' => $data['city'],
            'full_address' => $data['full_address'],
            'business_name' => $data['business_name'],
            'contact_name' => $data['contact_name'],
            'mobile_number' => $data['mobile_number'],
            'credit_limit' => $data['credit_limit'] ?? 0,
            'opening_balance' => $data['opening_balance'] ?? 0,
            'opening_balance_type' => $data['opening_balance_type'] ?? Supplier::OPENING_BALANCE_CREDIT,
            'urdu_business_name' => $urdu['business_name'] ?? null,
            'urdu_contact_name' => $urdu['contact_name'] ?? null,
            'urdu_city' => $urdu['city'] ?? null,
            'urdu_account_title' => $urdu['account_title'] ?? null,
            'urdu_address' => $urdu['address'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncChildren(Supplier $supplier, array $data): void
    {
        $this->syncBankAccounts($supplier, $data['bank_accounts'] ?? []);
        $contactIdsByIndex = $this->syncAdditionalContacts($supplier, $data['additional_contacts'] ?? []);
        $this->syncDocuments($supplier, $data['documents'] ?? []);
        $this->syncMobileRegistry($supplier, $data, $contactIdsByIndex);
    }

    /**
     * @param  array<int, array<string, mixed>>  $bankAccounts
     */
    protected function syncBankAccounts(Supplier $supplier, array $bankAccounts): void
    {
        $supplier->bankAccounts()->delete();

        foreach (array_values($bankAccounts) as $index => $account) {
            $supplier->bankAccounts()->create([
                'bank_name' => $account['bank_name'] ?? null,
                'branch_name' => $account['branch_name'] ?? null,
                'account_title' => $account['account_title'] ?? null,
                'iban_account_number' => $account['iban_account_number'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $contacts
     * @return array<int, int>
     */
    protected function syncAdditionalContacts(Supplier $supplier, array $contacts): array
    {
        $supplier->additionalContacts()->delete();

        $idsByIndex = [];

        foreach (array_values($contacts) as $index => $contact) {
            $created = $supplier->additionalContacts()->create([
                'contact_person' => $contact['contact_person'] ?? null,
                'mobile_number' => $contact['mobile_number'] ?? null,
                'sort_order' => $index,
            ]);

            $idsByIndex[$index] = $created->id;
        }

        return $idsByIndex;
    }

    /**
     * @param  array<string, mixed>  $documents
     */
    protected function syncDocuments(Supplier $supplier, array $documents): void
    {
        $newPaths = $this->documentStorage->resolvePaths($documents);
        $existing = $supplier->document;

        if ($existing !== null) {
            $this->documentStorage->cleanupReplacedFiles($existing, $newPaths);
        }

        $supplier->document()->updateOrCreate(
            ['supplier_id' => $supplier->id],
            $newPaths,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $contactIdsByIndex
     */
    protected function syncMobileRegistry(Supplier $supplier, array $data, array $contactIdsByIndex): void
    {
        $entries = [];
        $formEntries = $this->mobileRegistry->collectEntriesFromFormData($data);
        $additionalIndex = 0;

        foreach ($formEntries as $entry) {
            if ($entry['category'] === ContactMobileNumber::CATEGORY_ADDITIONAL) {
                $entry['contact_person_id'] = $contactIdsByIndex[$additionalIndex] ?? null;
                $additionalIndex++;
            }

            $entries[] = $entry;
        }

        $this->mobileRegistry->syncForSupplier($supplier->id, $entries);
    }
}
