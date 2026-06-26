<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetUserPasswordCommand extends Command
{
    protected $signature = 'users:set-password {email} {password}';

    protected $description = 'Set a user login password (for recovery after restore)';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user found for that email.');

            return self::FAILURE;
        }

        $user->password = $this->argument('password');
        $user->status = User::STATUS_ACTIVE;
        $user->save();

        $this->info('Password updated for '.$user->email.'.');

        return self::SUCCESS;
    }
}
