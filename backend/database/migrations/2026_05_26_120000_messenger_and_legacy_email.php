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
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'email_verified_at')) {
                continue;
            }
            DB::table($table)
                ->whereNull('email_verified_at')
                ->update(['email_verified_at' => DB::raw('COALESCE(created_at, NOW())')]);
        }

        if (Schema::hasTable('messages') && ! Schema::hasColumn('messages', 'expediteur')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->string('expediteur', 20)->default('client')->after('service_id');
            });
            DB::table('messages')->update(['expediteur' => 'client']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'expediteur')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn('expediteur');
            });
        }
    }
};
