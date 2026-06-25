<?php

namespace App\Livewire;

use App\Services\Users\UserApplicationPersistenceService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class StaffRegistration extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $full_address = '';

    /** @var array<int, array{contact_person: string, phone_number: string}> */
    public array $phones = [
        ['contact_person' => '', 'phone_number' => ''],
    ];

    /** @var array<int, array{bank_name: string, branch_name: string, account_title: string, iban_account_number: string}> */
    public array $bank_accounts = [
        [
            'bank_name' => '',
            'branch_name' => '',
            'account_title' => '',
            'iban_account_number' => '',
        ],
    ];

    public $profile_photo;

    public $card_front;

    public $card_back;

    public bool $submitted = false;

    public function addPhone(): void
    {
        $this->phones[] = ['contact_person' => '', 'phone_number' => ''];
    }

    public function removePhone(int $index): void
    {
        if (count($this->phones) <= 1) {
            return;
        }

        unset($this->phones[$index]);
        $this->phones = array_values($this->phones);
    }

    public function addBankAccount(): void
    {
        $this->bank_accounts[] = [
            'bank_name' => '',
            'branch_name' => '',
            'account_title' => '',
            'iban_account_number' => '',
        ];
    }

    public function removeBankAccount(int $index): void
    {
        if (count($this->bank_accounts) <= 1) {
            return;
        }

        unset($this->bank_accounts[$index]);
        $this->bank_accounts = array_values($this->bank_accounts);
    }

    public function submit(UserApplicationPersistenceService $persistence): void
    {
        $documents = [];

        if ($this->profile_photo) {
            $documents['profile_photo'] = $this->profile_photo->store(
                config('users.application_documents_directory', 'users/applications/documents'),
                config('users.document_disk', 'local'),
            );
        }

        if ($this->card_front) {
            $documents['card_front'] = $this->card_front->store(
                config('users.application_documents_directory', 'users/applications/documents'),
                config('users.document_disk', 'local'),
            );
        }

        if ($this->card_back) {
            $documents['card_back'] = $this->card_back->store(
                config('users.application_documents_directory', 'users/applications/documents'),
                config('users.document_disk', 'local'),
            );
        }

        try {
            $persistence->submit([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'full_address' => $this->full_address,
                'phones' => $this->phones,
                'bank_accounts' => $this->bank_accounts,
                'documents' => $documents,
            ]);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());

            return;
        }

        $this->submitted = true;
        $this->reset([
            'name',
            'email',
            'password',
            'password_confirmation',
            'full_address',
            'phones',
            'bank_accounts',
            'profile_photo',
            'card_front',
            'card_back',
        ]);

        $this->phones = [['contact_person' => '', 'phone_number' => '']];
        $this->bank_accounts = [[
            'bank_name' => '',
            'branch_name' => '',
            'account_title' => '',
            'iban_account_number' => '',
        ]];
    }

    public function render()
    {
        return view('livewire.staff-registration');
    }
}
