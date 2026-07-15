<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prestataires')) {
            Schema::table('prestataires', function (Blueprint $table) {
                if (! Schema::hasColumn('prestataires', 'plan')) {
                    $table->string('plan', 20)->default('gratuit')->after('statut_verification');
                }
                if (! Schema::hasColumn('prestataires', 'plan_expires_at')) {
                    $table->timestamp('plan_expires_at')->nullable()->after('plan');
                }
            });
        }

        // Orphan table from a failed run (FK type mismatch) — recreate if empty.
        if (Schema::hasTable('abonnements') && DB::table('abonnements')->count() === 0) {
            Schema::drop('abonnements');
        }

        if (! Schema::hasTable('abonnements')) {
            // prestataires.id is signed INT (legacy schema) — match type for FK.
            Schema::create('abonnements', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('prestataire_id')->index();
                $table->string('plan', 20);
                $table->unsignedInteger('montant_fcfa');
                $table->unsignedSmallInteger('periode_jours')->default(30);
                $table->string('fedapay_transaction_id', 64)->nullable()->index();
                $table->string('payment_url', 500)->nullable();
                $table->string('provider_token', 500)->nullable();
                $table->string('statut', 20)->default('pending')->index();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->json('webhook_log')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('prestataire_id')->references('id')->on('prestataires')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('abonnements');

        if (Schema::hasTable('prestataires')) {
            Schema::table('prestataires', function (Blueprint $table) {
                if (Schema::hasColumn('prestataires', 'plan_expires_at')) {
                    $table->dropColumn('plan_expires_at');
                }
                if (Schema::hasColumn('prestataires', 'plan')) {
                    $table->dropColumn('plan');
                }
            });
        }
    }
};
