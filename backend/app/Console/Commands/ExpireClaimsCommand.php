<?php

namespace App\Console\Commands;

use App\Services\ClaimService;
use Illuminate\Console\Command;

class ExpireClaimsCommand extends Command
{
    protected $signature   = 'claims:expire';
    protected $description = 'Expire overdue claims and advance queues';

    public function handle(ClaimService $claimService): int
    {
        $count = $claimService->expireOverdueClaims();

        if ($count > 0) {
            $this->info("Expired {$count} overdue claim(s) and advanced queues.");
        }

        return Command::SUCCESS;
    }
}
