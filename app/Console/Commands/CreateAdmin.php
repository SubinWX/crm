<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'crm:create-admin {--name=} {--email=} {--password=} {--phone=}';

    protected $description = 'Create the first (or an additional) Super Admin account';

    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?: $this->ask('Full name'),
            'email' => $this->option('email') ?: $this->ask('Email address'),
            'phone' => $this->option('phone'),
            'password' => $this->option('password') ?: $this->secret('Password (min 8 characters)'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create($data + ['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info("Super Admin {$user->email} created. Sign in at ".route('login'));

        return self::SUCCESS;
    }
}
