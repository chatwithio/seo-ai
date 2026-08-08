<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateUser extends Command
{
    protected $signature = 'user:create
        {--name= : Account name}
        {--email= : Unique login email}
        {--password= : Password; omit for hidden interactive entry}
        {--verified : Mark the email address as verified}';

    protected $description = 'Create a SaaS user account from the command line';

    public function handle(): int
    {
        $name = trim((string) ($this->option('name') ?: $this->ask('Name')));
        $email = trim((string) ($this->option('email') ?: $this->ask('Email')));
        $password = (string) ($this->option('password') ?: $this->secret('Password'));

        if ($password === '' && ! $this->input->isInteractive()) {
            $this->error('Provide --password when running non-interactively.');

            return self::FAILURE;
        }

        if (! $this->option('password') && $this->input->isInteractive()) {
            $confirmation = (string) $this->secret('Confirm password');

            if (! hash_equals($password, $confirmation)) {
                $this->error('The passwords do not match.');

                return self::FAILURE;
            }
        }

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'email_verified_at' => $this->option('verified') ? now() : null,
        ]);

        $this->info("User #{$user->id} created: {$user->email}");

        return self::SUCCESS;
    }
}
