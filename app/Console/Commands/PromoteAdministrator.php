<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('users:promote-admin {email : Email address of the existing account}')]
#[Description('Grant administrator access to an existing user')]
class PromoteAdministrator extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user found with that email address.');

            return self::FAILURE;
        }

        $user->update(['user_type' => User::TYPE_ADMIN]);
        $this->info('Administrator access granted.');

        return self::SUCCESS;
    }
}
