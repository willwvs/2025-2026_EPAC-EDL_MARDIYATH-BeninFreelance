<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['clients', 'prestataires'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'email_verified_at')) {
                    $blueprint->timestamp('email_verified_at')->nullable()->after('email');
                }
                if (! Schema::hasColumn($table, 'email_verify_token')) {
                    $blueprint->string('email_verify_token', 64)->nullable()->after('email_verified_at');
                }
                if (! Schema::hasColumn($table, 'email_verify_expires_at')) {
                    $blueprint->timestamp('email_verify_expires_at')->nullable()->after('email_verify_token');
                }
            });
        }

        if (Schema::hasTable('prestataires')) {
            Schema::table('prestataires', function (Blueprint $table) {
                if (! Schema::hasColumn('prestataires', 'ia_decided_at')) {
                    $table->timestamp('ia_decided_at')->nullable()->after('motif_refus_ia');
                }
                if (! Schema::hasColumn('prestataires', 'admin_decided_at')) {
                    $table->timestamp('admin_decided_at')->nullable()->after('ia_decided_at');
                }
                if (! Schema::hasColumn('prestataires', 'verification_email_sent_at')) {
                    $table->timestamp('verification_email_sent_at')->nullable()->after('admin_decided_at');
                }
            });
        }

        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'email_verified_at')) {
            DB::table('clients')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
        }
        if (Schema::hasTable('prestataires') && Schema::hasColumn('prestataires', 'email_verified_at')) {
            DB::table('prestataires')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('prestataires')) {
            Schema::table('prestataires', function (Blueprint $table) {
                foreach (['verification_email_sent_at', 'admin_decided_at', 'ia_decided_at'] as $col) {
                    if (Schema::hasColumn('prestataires', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        foreach (['clients', 'prestataires'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                foreach (['email_verify_expires_at', 'email_verify_token', 'email_verified_at'] as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        $blueprint->dropColumn($col);
                    }
                }
            });
        }
    }
};
