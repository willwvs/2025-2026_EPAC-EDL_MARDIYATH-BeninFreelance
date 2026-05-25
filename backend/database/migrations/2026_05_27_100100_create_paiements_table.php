<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('paiements')) {
            return;
        }

        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('commande_id');
            $table->string('mf_token_pay', 64)->nullable()->unique();
            $table->text('mf_url')->nullable();
            $table->unsignedInteger('montant_fcfa');
            $table->unsignedInteger('frais_mf_fcfa')->default(0);
            $table->string('moyen', 40)->nullable();
            $table->enum('statut', ['pending', 'paid', 'failure', 'no_paid', 'cancelled'])->default('pending');
            $table->json('webhook_log')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('paid_at')->nullable();

            $table->index('commande_id');
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
