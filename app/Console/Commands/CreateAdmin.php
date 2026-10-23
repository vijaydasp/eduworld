<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'edu:admin {--email=} {--name=}';

    protected $description = 'Create a super administrator using a hidden password prompt; no default credentials';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Administrator email');
        $name = $this->option('name') ?: $this->ask('Administrator name');
        $password = $this->secret('Password (12+ characters, mixed case, number and symbol)');
        $confirmation = $this->secret('Confirm password');
        $validator = Validator::make(['email' => $email, 'name' => $name, 'password' => $password, 'password_confirmation' => $confirmation], [
            'email' => ['required', 'email', 'unique:users,email'], 'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

return self::FAILURE;
        }
        User::create(['name' => $name, 'email' => strtolower($email), 'password' => $password, 'role' => 'super_admin', 'is_active' => true]);
        $this->info('Administrator created. Sign in at /admin/login.');

        return self::SUCCESS;
    }
}
