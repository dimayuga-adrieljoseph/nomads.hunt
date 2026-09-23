<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Claim;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoResetCommand extends Command
{
    protected $signature   = 'demo:reset {--force : Skip confirmation prompt}';
    protected $description = 'Reset all demo data (claims, orders, activity logs) and restore products to AVAILABLE';

    public function handle(): int
    {
        if (! $this->option('force')) {
            if (! $this->confirm('This will delete ALL claims, orders, and activity logs and reset all products to AVAILABLE. Continue?')) {
                $this->line('Aborted.');
                return Command::SUCCESS;
            }
        }

        $this->info('Resetting demo data...');

        // TRUNCATE is DDL in MySQL — cannot run inside a transaction.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('activity_logs')->truncate();
        DB::table('orders')->truncate();
        DB::table('claims')->truncate();
        DB::table('personal_access_tokens')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Reset all products to available (DML — safe)
        Product::query()->update(['status' => 'available']);

        $this->info('Demo data reset. All products are now AVAILABLE.');
        $this->line('Run `php artisan db:seed --class=DatabaseSeeder` to re-seed if needed.');

        return Command::SUCCESS;
    }
}
