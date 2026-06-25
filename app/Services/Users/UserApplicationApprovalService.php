<?php

namespace App\Services\Users;

use App\Models\Role;
use App\Models\User;
use App\Models\UserApplication;
use App\Support\Users\UserApplicationAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserApplicationApprovalService
{
    public function __construct(
        protected UserCodeGenerator $codeGenerator,
        protected UserApplicationDocumentStorage $applicationDocumentStorage,
        protected UserDocumentStorage $userDocumentStorage,
    ) {}

    public function approve(UserApplication $application, int $roleId, ?User $reviewer = null): User
    {
        if (! UserApplicationAuthorization::canApprove()) {
            abort(403);
        }

        if (! $application->isPending()) {
            abort(422, 'This application has already been reviewed.');
        }

        $role = Role::query()->whereKey($roleId)->where('slug', '!=', Role::SLUG_SUPER_ADMIN)->first();

        if ($role === null) {
            throw ValidationException::withMessages([
                'role_id' => ['Please select a valid role.'],
            ]);
        }

        return DB::transaction(function () use ($application, $role, $reviewer): User {
            $application->loadMissing(['phones', 'bankAccounts', 'document']);

            $user = User::query()->create([
                'user_number' => $this->codeGenerator->generate(),
                'name' => $application->name,
                'full_address' => $application->full_address,
                'email' => $application->email,
                'password' => $application->password,
                'role_id' => $role->id,
                'status' => User::STATUS_ACTIVE,
            ]);

            foreach ($application->phones as $phone) {
                $user->phones()->create([
                    'contact_person' => $phone->contact_person,
                    'phone_number' => $phone->phone_number,
                    'sort_order' => $phone->sort_order,
                ]);
            }

            foreach ($application->bankAccounts as $account) {
                $user->bankAccounts()->create([
                    'bank_name' => $account->bank_name,
                    'branch_name' => $account->branch_name,
                    'account_title' => $account->account_title,
                    'iban_account_number' => $account->iban_account_number,
                    'sort_order' => $account->sort_order,
                ]);
            }

            $applicationPaths = $application->document?->only([
                'profile_photo_path',
                'card_front_path',
                'card_back_path',
            ]) ?? [];

            $copiedPaths = $this->applicationDocumentStorage->copyToUserStorage($applicationPaths);

            $user->document()->create($copiedPaths);

            $application->update([
                'status' => UserApplication::STATUS_APPROVED,
                'assigned_role_id' => $role->id,
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now(),
                'approved_user_id' => $user->id,
            ]);

            return $user->fresh(['role', 'phones', 'bankAccounts', 'document']);
        });
    }

    public function reject(UserApplication $application, string $reason, ?User $reviewer = null): UserApplication
    {
        if (! UserApplicationAuthorization::canReject()) {
            abort(403);
        }

        if (! $application->isPending()) {
            abort(422, 'This application has already been reviewed.');
        }

        $validator = Validator::make(['rejection_reason' => $reason], [
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $application->update([
            'status' => UserApplication::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now(),
        ]);

        return $application->fresh();
    }
}
