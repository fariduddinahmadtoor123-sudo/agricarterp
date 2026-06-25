<?php

namespace App\Services\Users;

use App\Models\User;
use App\Support\Users\UserAuthorization;
use Illuminate\Support\Facades\DB;

class UserPersistenceService
{
    public function __construct(
        protected UserCodeGenerator $codeGenerator,
        protected UserDataValidator $dataValidator,
        protected UserDocumentStorage $documentStorage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        if (! UserAuthorization::canCreate()) {
            abort(403);
        }

        $data = $this->prepareData($data);
        $this->dataValidator->validate($data, requirePassword: true);

        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                ...$this->userAttributes($data),
                'user_number' => $this->codeGenerator->generate(),
                'password' => $data['password'],
            ]);

            $this->syncChildren($user, $data);

            return $user->fresh(['role', 'phones', 'bankAccounts', 'document']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        if (! UserAuthorization::canEdit()) {
            abort(403);
        }

        $data = $this->prepareData($data, $user);
        $this->dataValidator->validate($data, $user);

        if ($user->isSuperAdmin()) {
            $data['role_id'] = $user->role_id;
            $data['status'] = User::STATUS_ACTIVE;
        }

        return DB::transaction(function () use ($user, $data): User {
            $attributes = $this->userAttributes($data, $user);

            if (filled($data['password'] ?? null)) {
                $attributes['password'] = $data['password'];
            }

            $user->update($attributes);
            $this->syncChildren($user, $data);

            return $user->fresh(['role', 'phones', 'bankAccounts', 'document']);
        });
    }

    public function deactivate(User $user): User
    {
        if (! UserAuthorization::canDeactivate()) {
            abort(403);
        }

        if ($user->isSuperAdmin()) {
            abort(422, 'Super Admin cannot be deactivated.');
        }

        $user->update(['status' => User::STATUS_INACTIVE]);

        return $user->fresh(['role']);
    }

    public function activate(User $user): User
    {
        if (! UserAuthorization::canEdit()) {
            abort(403);
        }

        $user->update(['status' => User::STATUS_ACTIVE]);

        return $user->fresh(['role']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareData(array $data, ?User $user = null): array
    {
        $data['phones'] = collect($data['phones'] ?? [])
            ->filter(fn (array $phone): bool => filled($phone['phone_number'] ?? null))
            ->values()
            ->all();

        $data['bank_accounts'] = $this->filterBankAccounts($data['bank_accounts'] ?? []);

        if (! UserAuthorization::canDeactivate()) {
            $data['status'] = $user?->status ?? User::STATUS_ACTIVE;
        }

        $data['status'] = $data['status'] ?? User::STATUS_ACTIVE;

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
    protected function userAttributes(array $data, ?User $user = null): array
    {
        return [
            'name' => $data['name'],
            'full_address' => $data['full_address'] ?? null,
            'email' => $data['email'],
            'role_id' => $data['role_id'],
            'status' => $data['status'] ?? User::STATUS_ACTIVE,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncChildren(User $user, array $data): void
    {
        $this->syncPhones($user, $data['phones'] ?? []);
        $this->syncBankAccounts($user, $data['bank_accounts'] ?? []);
        $this->syncDocuments($user, $data['documents'] ?? []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $phones
     */
    protected function syncPhones(User $user, array $phones): void
    {
        $user->phones()->delete();

        foreach (array_values($phones) as $index => $phone) {
            $user->phones()->create([
                'contact_person' => $phone['contact_person'] ?? null,
                'phone_number' => $phone['phone_number'],
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $bankAccounts
     */
    protected function syncBankAccounts(User $user, array $bankAccounts): void
    {
        $user->bankAccounts()->delete();

        foreach (array_values($bankAccounts) as $index => $account) {
            $user->bankAccounts()->create([
                'bank_name' => $account['bank_name'] ?? null,
                'branch_name' => $account['branch_name'] ?? null,
                'account_title' => $account['account_title'] ?? null,
                'iban_account_number' => $account['iban_account_number'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $documents
     */
    protected function syncDocuments(User $user, array $documents): void
    {
        $newPaths = $this->documentStorage->resolvePaths($documents);
        $existing = $user->document;

        if ($existing !== null) {
            $this->documentStorage->cleanupReplacedFiles($existing, $newPaths);
        }

        $user->document()->updateOrCreate(
            ['user_id' => $user->id],
            $newPaths,
        );
    }
}
