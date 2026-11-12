<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

#[Signature('pos:create-manager
    {--admin : Assign the unrestricted Admin role instead of Manager}
    {--name= : Account name; prompted when omitted}
    {--email= : Account email; prompted when omitted}
    {--reset-password : Rotate an existing account password and invalidate its sessions, preserving roles}
    {--password-env= : Name of an untracked environment variable containing the password}')]
#[Description('Create a local Manager or Admin account using a hidden password prompt')]
class CreateManagerCommand extends Command
{
    public function handle(): int
    {
        $restaurant = Restaurant::query()->oldest('id')->first();

        if (! $restaurant) {
            $this->components->error('No restaurant exists. Run php artisan db:seed first.');

            return self::FAILURE;
        }

        $roleKey = $this->option('admin') ? 'admin' : 'manager';
        $role = Role::query()->where('key', $roleKey)->first();

        if (! $role) {
            $this->components->error("The {$roleKey} role is missing. Run php artisan db:seed first.");

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: $this->ask('Name')));
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Email'))));
        $existing = $this->option('reset-password') ? User::query()->where('email', $email)->first() : null;
        if ($this->option('reset-password') && ! $existing) {
            $this->components->error('No account exists with that email.');

            return self::FAILURE;
        }
        $passwordEnvironmentVariable = (string) $this->option('password-env');
        $password = $passwordEnvironmentVariable !== ''
            ? (string) getenv($passwordEnvironmentVariable)
            : (string) $this->secret('Password (minimum 12 characters, mixed case, number, and symbol)');
        $confirmation = $passwordEnvironmentVariable !== ''
            ? $password
            : (string) $this->secret('Confirm password');

        $validator = Validator::make(
            compact('name', 'email', 'password', 'confirmation'),
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($existing?->id)],
                'password' => ['required', 'same:confirmation', Password::min(12)->mixedCase()->numbers()->symbols()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($restaurant, $role, $name, $email, $password, $existing): User {
            if ($existing) {
                $existing->forceFill(['password' => $password, 'remember_token' => null])->save();
                DB::table('sessions')->where('user_id', $existing->id)->delete();

                return $existing;
            }
            $user = User::query()->create([
                'restaurant_id' => $restaurant->id,
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'active' => true,
            ]);
            $user->roles()->attach($role);

            return $user;
        });

        $action = $existing ? 'Password rotated' : "{$role->name} account created";
        $this->components->info("{$action} for {$user->email}. The password was not logged.");

        return self::SUCCESS;
    }
}
