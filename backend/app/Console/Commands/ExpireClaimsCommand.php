<?php

namespace App\Console\Commands;

use App\Services\ClaimService;
use Illuminate\Console\Command;

class ExpireClaimsCommand extends Command
{
    protected $signature   = 'claims:expire';
    protected $description = 'Advance overdue claims: claim stage → payment window, or payment window → next claimant';

    public function handle(ClaimService $claimService): int
    {
        $count = $claimService->expireOverdueClaims();

        if ($count > 0) {
            $this->info("Advanced {$count} overdue claim(s) to their next stage.");
        }

        return Command::SUCCESS;
    }
}
