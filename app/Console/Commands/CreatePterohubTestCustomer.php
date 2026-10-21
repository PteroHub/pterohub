<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Local-only account creation for manually testing the customer portal. */
final class CreatePterohubTestCustomer extends Command
{
    protected $signature = 'pterohub:create-test-customer {email : Test account email} {--name=Test Customer : Display name} {--verified : Mark email as verified for dashboard testing}';
    protected $description = 'Interactively create a non-admin customer for PteroHub UI testing';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $name = trim((string) $this->option('name'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || $name === '' || mb_strlen($name) > 150) {
            $this->error('Invalid name or email. No changes made.');
            return self::FAILURE;
        }
        if (DB::table('users')->where('email', $email)->exists()) {
            $this->error('An account with this email already exists. No changes made.');
            return self::FAILURE;
        }
        $this->warn('This creates a real login account in the currently configured database.');
        if (!$this->confirm('Create this TEST customer account?', false)) {
            $this->line('Cancelled.');
            return self::SUCCESS;
        }
        $password = (string) $this->secret('Test customer password (at least 14 characters)');
        $confirmation = (string) $this->secret('Confirm test customer password');
        if (strlen($password) < 14 || $password !== $confirmation) {
            $this->error('Password too short or confirmation does not match. No changes made.');
            return self::FAILURE;
        }
        DB::table('users')->insert([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_pterohub_admin' => false,
            'email_verified_at' => $this->option('verified') ? now() : null,
            'remember_token' => Str::random(60),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->info('Test customer created as a non-admin account.');
        $this->line($this->option('verified') ? 'Email marked verified for manual dashboard testing.' : 'Email unverified; normal verification will be required.');
        return self::SUCCESS;
    }
}
