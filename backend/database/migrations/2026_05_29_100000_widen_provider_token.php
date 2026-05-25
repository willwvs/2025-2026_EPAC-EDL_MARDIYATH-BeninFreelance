<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('paiements') && Schema::hasColumn('paiements', 'provider_token')) {
            Schema::table('paiements', function (Blueprint $table) {
                $table->text('provider_token')->nullable()->change();
            });
        }

        if (Schema::hasTable('paiements') && Schema::hasColumn('paiements', 'provider_ref')) {
            Schema::table('paiements', function (Blueprint $table) {
                $table->string('provider_ref', 191)->nullable()->change();
            });
        }

        if (Schema::hasTable('payouts') && Schema::hasColumn('payouts', 'provider_ref')) {
            Schema::table('payouts', function (Blueprint $table) {
                $table->string('provider_ref', 191)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('paiements') && Schema::hasColumn('paiements', 'provider_token')) {
            Schema::table('paiements', function (Blueprint $table) {
                $table->string('provider_token', 120)->nullable()->change();
            });
        }
    }
};
