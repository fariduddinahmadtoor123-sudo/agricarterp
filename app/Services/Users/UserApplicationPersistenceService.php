<?php

namespace App\Services\Users;

use App\Models\UserApplication;
use Illuminate\Support\Facades\DB;

class UserApplicationPersistenceService
{
    public function __construct(
        protected UserApplicationCodeGenerator $codeGenerator,
        protected UserDataValidator $dataValidator,
        protected UserApplicationDocumentStorage $documentStorage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(array $data): UserApplication
    {
        $data = $this->prepareData($data);
        $this->dataValidator->validateApplication($data);

        return DB::transaction(function () use ($data): UserApplication {
            $application = UserApplication::query()->create([
                'application_number' => $this->codeGenerator->generate(),
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'full_address' => $data['full_address'] ?? null,
                'status' => UserApplication::STATUS_PENDING,
            ]);

            $this->syncChildren($application, $data);

            return $application->fresh(['phones', 'bankAccounts', 'document']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareData(array $data): array
    {
        $data['phones'] = collect($data['phones'] ?? [])
            ->filter(fn (array $phone): bool => filled($phone['phone_number'] ?? null))
            ->values()
            ->all();

        $data['bank_accounts'] = collect($data['bank_accounts'] ?? [])
            ->filter(fn (array $account): bool => filled($account['bank_name'] ?? null)
                || filled($account['branch_name'] ?? null)
                || filled($account['account_title'] ?? null)
                || filled($account['iban_account_number'] ?? null))
            ->values()
            ->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncChildren(UserApplication $application, array $data): void
    {
        foreach (array_values($data['phones'] ?? []) as $index => $phone) {
            $application->phones()->create([
                'contact_person' => $phone['contact_person'] ?? null,
                'phone_number' => $phone['phone_number'],
                'sort_order' => $index,
            ]);
        }

        foreach (array_values($data['bank_accounts'] ?? []) as $index => $account) {
            $application->bankAccounts()->create([
                'bank_name' => $account['bank_name'] ?? null,
                'branch_name' => $account['branch_name'] ?? null,
                'account_title' => $account['account_title'] ?? null,
                'iban_account_number' => $account['iban_account_number'] ?? null,
                'sort_order' => $index,
            ]);
        }

        $paths = $this->documentStorage->resolvePaths($data['documents'] ?? []);

        $application->document()->create($paths);
    }
}
