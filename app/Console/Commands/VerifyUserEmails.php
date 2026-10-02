<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Operator escape hatch for email verification: mark one account (or, when
 * rolling verification out to an existing install, every account) as
 * verified without sending email.
 */
class VerifyUserEmails extends Command
{
    protected $signature = 'users:verify
        {email? : Verify the account with this email address}
        {--all : Verify every account that is not yet verified}';

    protected $description = 'Mark user email addresses as verified without sending email';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (($email === null) === (! $this->option('all'))) {
            $this->error('Give an email address, or --all (not both).');

            return self::INVALID;
        }

        $query = User::whereNull('email_verified_at')
            ->when($email, fn ($q) => $q->where('email', $email));

        if ($email && ! User::where('email', $email)->exists()) {
            $this->error("No account with email {$email}.");

            return self::FAILURE;
        }

        if ($this->option('all') && ! $this->confirm("Mark {$query->count()} unverified account(s) as verified? Only do this for accounts you trust.", true)) {
            return self::FAILURE;
        }

        $count = $query->update(['email_verified_at' => now()]);
        $this->info("Verified {$count} account(s).");

        return self::SUCCESS;
    }
}
