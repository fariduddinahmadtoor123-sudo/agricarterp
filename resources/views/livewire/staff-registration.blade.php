<div class="staff-register">
    @if ($submitted)
        <div class="staff-register__success">
            <h2>Application Submitted</h2>
            <p>Your staff registration has been received. An administrator will review your application and notify you by email once approved.</p>
        </div>
    @else
        <form wire:submit="submit" class="staff-register__form">
            <section class="staff-register__section">
                <h2>Personal Information</h2>

                <label>
                    <span>Full Name</span>
                    <input type="text" wire:model="name" required>
                    @error('name') <em>{{ $message }}</em> @enderror
                </label>

                <label>
                    <span>Email</span>
                    <input type="email" wire:model="email" required>
                    @error('email') <em>{{ $message }}</em> @enderror
                </label>

                <label>
                    <span>Password</span>
                    <input type="password" wire:model="password" required>
                    @error('password') <em>{{ $message }}</em> @enderror
                </label>

                <label>
                    <span>Confirm Password</span>
                    <input type="password" wire:model="password_confirmation" required>
                </label>

                <label>
                    <span>Address</span>
                    <textarea wire:model="full_address" rows="3"></textarea>
                    @error('full_address') <em>{{ $message }}</em> @enderror
                </label>
            </section>

            <section class="staff-register__section">
                <h2>Phone Numbers</h2>

                @foreach ($phones as $index => $phone)
                    <div class="staff-register__repeater-row" wire:key="phone-{{ $index }}">
                        <label>
                            <span>Contact Person</span>
                            <input type="text" wire:model="phones.{{ $index }}.contact_person">
                        </label>
                        <label>
                            <span>Phone Number</span>
                            <input type="tel" wire:model="phones.{{ $index }}.phone_number" required>
                            @error('phones.' . $index . '.phone_number') <em>{{ $message }}</em> @enderror
                        </label>
                        @if (count($phones) > 1)
                            <button type="button" wire:click="removePhone({{ $index }})">Remove</button>
                        @endif
                    </div>
                @endforeach

                <button type="button" wire:click="addPhone">Add Phone</button>
            </section>

            <section class="staff-register__section">
                <h2>Bank Accounts</h2>

                @foreach ($bank_accounts as $index => $account)
                    <div class="staff-register__repeater-row" wire:key="bank-{{ $index }}">
                        <label>
                            <span>Bank Name</span>
                            <input type="text" wire:model="bank_accounts.{{ $index }}.bank_name">
                        </label>
                        <label>
                            <span>Branch Name</span>
                            <input type="text" wire:model="bank_accounts.{{ $index }}.branch_name">
                        </label>
                        <label>
                            <span>Account Title</span>
                            <input type="text" wire:model="bank_accounts.{{ $index }}.account_title">
                        </label>
                        <label>
                            <span>IBAN / Account Number</span>
                            <input type="text" wire:model="bank_accounts.{{ $index }}.iban_account_number">
                        </label>
                        @if (count($bank_accounts) > 1)
                            <button type="button" wire:click="removeBankAccount({{ $index }})">Remove</button>
                        @endif
                    </div>
                @endforeach

                <button type="button" wire:click="addBankAccount">Add Bank Account</button>
            </section>

            <section class="staff-register__section">
                <h2>Documents</h2>

                <label>
                    <span>Profile Photo</span>
                    <input type="file" wire:model="profile_photo" accept="image/*">
                    @error('profile_photo') <em>{{ $message }}</em> @enderror
                </label>

                <label>
                    <span>CNIC Front</span>
                    <input type="file" wire:model="card_front" accept="image/*">
                    @error('card_front') <em>{{ $message }}</em> @enderror
                </label>

                <label>
                    <span>CNIC Back</span>
                    <input type="file" wire:model="card_back" accept="image/*">
                    @error('card_back') <em>{{ $message }}</em> @enderror
                </label>
            </section>

            <button type="submit" class="staff-register__submit">Submit Application</button>
        </form>
    @endif
</div>
