<?php

namespace App\Services\Contacts;

use App\Models\ContactMobileNumber;
use App\Models\Customer;
use App\Support\Authorization\PermissionChecker;
use App\Support\Contacts\CustomerAuthorization;
use Illuminate\Support\Facades\DB;

class CustomerPersistenceService
{
    public function __construct(
        protected CustomerCodeGenerator $codeGenerator,
        protected CustomerMobileRegistry $mobileRegistry,
        protected CustomerDataValidator $dataValidator,
        protected CustomerDocumentStorage $documentStorage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Customer
    {
        PermissionChecker::authorizeAbility(fn (): bool => CustomerAuthorization::canCreate());

        $data = $this->prepareData($data);

        $this->dataValidator->validate($data);

        $this->mobileRegistry->assertUnique(
            $this->mobileRegistry->collectEntriesFromFormData($data),
        );

        return DB::transaction(function () use ($data): Customer {
            $customer = Customer::query()->create([
                ...$this->customerAttributes($data),
                'customer_code' => $this->codeGenerator->generate(),
            ]);

            $this->syncChildren($customer, $data);

            return $customer->fresh([
                'bankAccounts',
                'additionalContacts',
                'document',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        PermissionChecker::authorizeAbility(fn (): bool => CustomerAuthorization::canEdit());

        if ($customer->trashed()) {
            abort(404);
        }

        $data = $this->prepareData($data, $customer);

        $this->dataValidator->validate($data);

        $this->mobileRegistry->assertUnique(
            $this->mobileRegistry->collectEntriesFromFormData($data),
            $customer->id,
        );

        return DB::transaction(function () use ($customer, $data): Customer {
            $customer->update($this->customerAttributes($data, $customer));

            $this->syncChildren($customer, $data);

            return $customer->fresh([
                'bankAccounts',
                'additionalContacts',
                'document',
            ]);
        });
    }

    public function delete(Customer $customer): void
    {
        if (! CustomerAuthorization::canDelete()) {
            abort(403);
        }

        $customer->delete();
    }

    public function restore(Customer $customer): Customer
    {
        if (! CustomerAuthorization::canRestore()) {
            abort(403);
        }

        if ($customer->trashed()) {
            $customer->restore();
        }

        return $customer->fresh([
            'bankAccounts',
            'additionalContacts',
            'document',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareData(array $data, ?Customer $customer = null): array
    {
        $data['additional_contacts'] = collect($data['additional_contacts'] ?? [])
            ->filter(fn (array $contact): bool => filled($contact['contact_person'] ?? null) || filled($contact['mobile_number'] ?? null))
            ->values()
            ->all();

        $data['bank_accounts'] = $this->filterBankAccounts($data['bank_accounts'] ?? []);

        if (! array_key_exists('opening_balance_type', $data) || blank($data['opening_balance_type'])) {
            $data['opening_balance_type'] = $customer?->opening_balance_type
                ?? Customer::OPENING_BALANCE_DEBIT;
        }

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $bankAccounts
     * @return array<int, array<string, mixed>>
     */
    protected function filterBankAccounts(array $bankAccounts): array
    {
        return collect($bankAccounts)
            ->filter(fn (array $account): bool => filled($account['bank_name'] ?? null)
                || filled($account['branch_name'] ?? null)
                || filled($account['account_title'] ?? null)
                || filled($account['iban_account_number'] ?? null))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function customerAttributes(array $data, ?Customer $customer = null): array
    {
        $urdu = $data['urdu'] ?? [];

        return [
            'customer_name' => $data['customer_name'],
            'mobile_number' => $data['mobile_number'],
            'country' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            'full_address' => $data['full_address'] ?? null,
            'credit_limit' => $data['credit_limit'] ?? 0,
            'opening_balance' => $data['opening_balance'] ?? 0,
            'opening_balance_type' => $data['opening_balance_type'] ?? Customer::OPENING_BALANCE_DEBIT,
            'urdu_customer_name' => $urdu['customer_name'] ?? null,
            'urdu_city' => $urdu['city'] ?? null,
            'urdu_address' => $urdu['address'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncChildren(Customer $customer, array $data): void
    {
        $this->syncBankAccounts($customer, $data['bank_accounts'] ?? []);
        $contactIdsByIndex = $this->syncAdditionalContacts($customer, $data['additional_contacts'] ?? []);
        $this->syncDocuments($customer, $data['documents'] ?? []);
        $this->syncMobileRegistry($customer, $data, $contactIdsByIndex);
    }

    /**
     * @param  array<int, array<string, mixed>>  $bankAccounts
     */
    protected function syncBankAccounts(Customer $customer, array $bankAccounts): void
    {
        $customer->bankAccounts()->delete();

        foreach (array_values($bankAccounts) as $index => $account) {
            $customer->bankAccounts()->create([
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
    protected function syncAdditionalContacts(Customer $customer, array $contacts): array
    {
        $customer->additionalContacts()->delete();

        $idsByIndex = [];

        foreach (array_values($contacts) as $index => $contact) {
            $created = $customer->additionalContacts()->create([
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
    protected function syncDocuments(Customer $customer, array $documents): void
    {
        $newPaths = $this->documentStorage->resolvePaths($documents);
        $existing = $customer->document;

        if ($existing !== null) {
            $this->documentStorage->cleanupReplacedFiles($existing, $newPaths);
        }

        $customer->document()->updateOrCreate(
            ['customer_id' => $customer->id],
            $newPaths,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $contactIdsByIndex
     */
    protected function syncMobileRegistry(Customer $customer, array $data, array $contactIdsByIndex): void
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

        $this->mobileRegistry->syncForCustomer($customer->id, $entries);
    }
}
