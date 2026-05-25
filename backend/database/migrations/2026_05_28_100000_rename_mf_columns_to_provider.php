<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('paiements')) {
            Schema::table('paiements', function (Blueprint $table) {
                if (Schema::hasColumn('paiements', 'mf_token_pay') && ! Schema::hasColumn('paiements', 'provider_ref')) {
                    $table->renameColumn('mf_token_pay', 'provider_ref');
                }
            });
            Schema::table('paiements', function (Blueprint $table) {
                if (Schema::hasColumn('paiements', 'mf_url') && ! Schema::hasColumn('paiements', 'payment_url')) {
                    $table->renameColumn('mf_url', 'payment_url');
                }
            });
            Schema::table('paiements', function (Blueprint $table) {
                if (Schema::hasColumn('paiements', 'frais_mf_fcfa') && ! Schema::hasColumn('paiements', 'frais_fcfa')) {
                    $table->renameColumn('frais_mf_fcfa', 'frais_fcfa');
                }
            });
            Schema::table('paiements', function (Blueprint $table) {
                if (! Schema::hasColumn('paiements', 'provider')) {
                    $table->string('provider', 20)->default('fedapay')->after('commande_id');
                }
                if (! Schema::hasColumn('paiements', 'provider_token')) {
                    $table->string('provider_token', 120)->nullable()->after('provider_ref');
                }
            });
        }

        if (Schema::hasTable('payouts')) {
            Schema::table('payouts', function (Blueprint $table) {
                if (Schema::hasColumn('payouts', 'mf_token_pay') && ! Schema::hasColumn('payouts', 'provider_ref')) {
                    $table->renameColumn('mf_token_pay', 'provider_ref');
                }
            });
            Schema::table('payouts', function (Blueprint $table) {
                if (! Schema::hasColumn('payouts', 'provider')) {
                    $table->string('provider', 20)->default('fedapay')->after('commande_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('paiements')) {
            Schema::table('paiements', function (Blueprint $table) {
                if (Schema::hasColumn('paiements', 'provider_ref') && ! Schema::hasColumn('paiements', 'mf_token_pay')) {
                    $table->renameColumn('provider_ref', 'mf_token_pay');
                }
                if (Schema::hasColumn('paiements', 'payment_url') && ! Schema::hasColumn('paiements', 'mf_url')) {
                    $table->renameColumn('payment_url', 'mf_url');
                }
                if (Schema::hasColumn('paiements', 'frais_fcfa') && ! Schema::hasColumn('paiements', 'frais_mf_fcfa')) {
                    $table->renameColumn('frais_fcfa', 'frais_mf_fcfa');
                }
                if (Schema::hasColumn('paiements', 'provider')) {
                    $table->dropColumn('provider');
                }
                if (Schema::hasColumn('paiements', 'provider_token')) {
                    $table->dropColumn('provider_token');
                }
            });
        }

        if (Schema::hasTable('payouts')) {
            Schema::table('payouts', function (Blueprint $table) {
                if (Schema::hasColumn('payouts', 'provider_ref') && ! Schema::hasColumn('payouts', 'mf_token_pay')) {
                    $table->renameColumn('provider_ref', 'mf_token_pay');
                }
                if (Schema::hasColumn('payouts', 'provider')) {
                    $table->dropColumn('provider');
                }
            });
        }
    }
};
