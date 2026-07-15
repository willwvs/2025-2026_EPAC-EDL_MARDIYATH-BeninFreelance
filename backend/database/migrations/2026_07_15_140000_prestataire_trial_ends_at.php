<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prestataires')) {
            return;
        }

        if (! Schema::hasColumn('prestataires', 'trial_ends_at')) {
            Schema::table('prestataires', function (Blueprint $table) {
                $table->timestamp('trial_ends_at')->nullable()->after('plan_expires_at');
            });
        }

        $days = (int) config('marketplace.trial_days', 7);

        // Prestataires sans plan payant actif → essai gratuit en cours.
        DB::table('prestataires')
            ->where(function ($q) {
                $q->whereNull('plan')
                    ->orWhere('plan', '')
                    ->orWhere('plan', 'gratuit');
            })
            ->whereNull('trial_ends_at')
            ->update([
                'plan' => 'gratuit',
                'trial_ends_at' => now()->addDays($days),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('prestataires') && Schema::hasColumn('prestataires', 'trial_ends_at')) {
            Schema::table('prestataires', function (Blueprint $table) {
                $table->dropColumn('trial_ends_at');
            });
        }
    }
};
