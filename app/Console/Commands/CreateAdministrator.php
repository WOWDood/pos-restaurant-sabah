<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('users:create-admin {email : Administrator email} {name : Administrator name} {code : Unique staff code}')]
#[Description('Create an administrator and prompt for a password')]
class CreateAdministrator extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Run this command interactively to set the password.');

            return self::FAILURE;
        }

        $password = $this->secret('Choose a password');
        $confirmation = $this->secret('Confirm the password');
        $validator = Validator::make([
            'email' => $this->argument('email'), 'name' => $this->argument('name'),
            'code' => $this->argument('code'), 'password' => $password, 'password_confirmation' => $confirmation,
        ], [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', 'unique:users,code'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $data = $validator->validated();
        User::create([
            'name' => $data['name'], 'email' => $data['email'], 'code' => $data['code'],
            'password' => $data['password'], 'user_type' => User::TYPE_ADMIN, 'email_verified_at' => now(),
        ]);
        $this->info('Administrator account created.');

        return self::SUCCESS;
    }
}
