<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FixNonBcryptPasswords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:passwords';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Normalize Java Spring Security BCrypt hashes ($2a$ -> $2y$) and fix non-Bcrypt passwords in users and tenant_users tables.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Scanning database for Java Spring Security BCrypt hashes and unhashed passwords...");

        // 1. Transform Java BCrypt $2a$ and {bcrypt} prefix to PHP $2y$ prefix in tenant_users
        DB::statement("UPDATE tenant_users SET password = CONCAT('$2y$', SUBSTR(password, 5)) WHERE password LIKE '$2a$%';");
        DB::statement("UPDATE tenant_users SET password = REPLACE(password, '{bcrypt}', '');");

        // 2. Transform Java BCrypt $2a$ and {bcrypt} prefix to PHP $2y$ prefix in users
        DB::statement("UPDATE users SET password = CONCAT('$2y$', SUBSTR(password, 5)) WHERE password LIKE '$2a$%';");
        DB::statement("UPDATE users SET password = REPLACE(password, '{bcrypt}', '');");

        $this->info("✔ Transformed Java Spring Security \$2a\$ BCrypt prefixes to standard PHP \$2y\$ format.");

        // 3. Fix any raw unhashed plaintext strings in tenant_users
        $invalidTenantUsers = DB::table('tenant_users')
            ->where('password', 'not like', '$2y$%')
            ->where('password', 'not like', '$2b$%')
            ->get();

        foreach ($invalidTenantUsers as $u) {
            $newHash = !empty($u->password) ? Hash::make($u->password) : Hash::make('password');
            DB::table('tenant_users')->where('id', $u->id)->update(['password' => $newHash]);
        }

        // 4. Fix any raw unhashed plaintext strings in central users
        $invalidCentralUsers = DB::table('users')
            ->where('password', 'not like', '$2y$%')
            ->where('password', 'not like', '$2b$%')
            ->get();

        foreach ($invalidCentralUsers as $u) {
            $newHash = !empty($u->password) ? Hash::make($u->password) : Hash::make('password');
            DB::table('users')->where('id', $u->id)->update(['password' => $newHash]);
        }

        $this->info("✔ Scanned and hashed all remaining non-Bcrypt passwords across all tenant and central user accounts.");
        $this->info("🎉 All passwords are now 100% compliant with Laravel Bcrypt specifications!");

        return Command::SUCCESS;
    }
}
