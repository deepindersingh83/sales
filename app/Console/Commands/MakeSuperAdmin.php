<?php

namespace App\Console\Commands;

use App\Actions\ProvisionWorkspace;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Create or promote the platform super admin. Safe to run repeatedly: an
 * existing account keeps its password and is just verified and promoted; a
 * new account gets a one-time password printed here. Either way the account
 * gets a company to land in if it has none.
 */
class MakeSuperAdmin extends Command
{
    protected $signature = 'app:super-admin
        {email? : The account email (defaults to SUPER_ADMIN_EMAIL)}
        {--name=Administrator : Name for a newly created account}
        {--company= : Company name if the account has none (defaults to APP_NAME)}';

    protected $description = 'Create or promote the platform super admin account';

    public function handle(ProvisionWorkspace $provision): int
    {
        $email = strtolower(trim((string) ($this->argument('email') ?? config('app.super_admin_email'))));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Give an email address, or set SUPER_ADMIN_EMAIL in .env.');

            return self::INVALID;
        }

        $user = User::where('email', $email)->first();
        $password = null;

        if (! $user) {
            $password = Str::password(16, symbols: false);
            $user = User::create([
                'name' => $this->option('name'),
                'email' => $email,
                'password' => Hash::make($password),
            ]);
        }

        $user->forceFill([
            'is_super_admin' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        if (! $user->workspaces()->exists()) {
            $company = $this->option('company') ?: config('app.name');
            $provision->handle($user, $company);
            $this->line("Created company “{$company}” with {$email} as Full Admin.");
        }

        $this->info("{$email} is a verified super admin.");

        if ($password) {
            $this->warn("Temporary password (shown once): {$password}");
            $this->line('Sign in and change it under Profile.');
        }

        return self::SUCCESS;
    }
}
