<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two-stage claim lifecycle.
     *
     *   CLAIM period   — the claimant holds the item, others may Steal it.
     *          ↓  (claim_expires_at reached, no Steal)
     *   PAYMENT period — only now may the claimant pay.
     *          ↓  (payment_expires_at reached unpaid)
     *   next queued claimant gets a brand new CLAIM period.
     *
     * `expires_at` is kept as "the deadline of the CURRENT phase" so every
     * existing reader (timers, admin views) keeps working, while the explicit
     * columns below record both stages separately.
     */
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            // 'claim' | 'payment' (null while WAITING) — values come from Claim::PHASE_*
            $table->string('phase', 10)->nullable()->after('status');
            $table->timestamp('claim_expires_at')->nullable()->after('expires_at');
            $table->timestamp('payment_starts_at')->nullable()->after('claim_expires_at');
            $table->timestamp('payment_expires_at')->nullable()->after('payment_starts_at');
        });

        // Every claim that is ACTIVE today is still in its claim stage.
        DB::table('claims')
            ->where('status', 'active')
            ->update([
                'phase'            => 'claim',
                'claim_expires_at' => DB::raw('expires_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropColumn(['phase', 'claim_expires_at', 'payment_starts_at', 'payment_expires_at']);
        });
    }
};
