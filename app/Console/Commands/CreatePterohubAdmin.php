<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
class CreatePterohubAdmin extends Command {
    protected $signature = 'pterohub:create-admin';
    protected $description = 'Interactively create a dedicated PteroHub admin account (CLI only)';
    public function handle(): int {
        $name = trim((string) $this->ask('Full name'));
        $email = strtolower(trim((string) $this->ask('Email')));
        $password = (string) $this->secret('New password (minimum 14 characters)');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 14) {
            $this->error('Invalid name/email/password; no changes made.'); return self::FAILURE;
        }
        if (DB::table('users')->where('email', $email)->exists()) {
            $this->error('That email already exists. No changes made.'); return self::FAILURE;
        }
        DB::table('users')->insert(['name'=>$name,'email'=>$email,'password'=>Hash::make($password),
            'is_pterohub_admin'=>true,'email_verified_at'=>now(),'remember_token'=>Str::random(60),
            'created_at'=>now(),'updated_at'=>now()]);
        $this->info('PteroHub administrator created.'); return self::SUCCESS;
    }
}
