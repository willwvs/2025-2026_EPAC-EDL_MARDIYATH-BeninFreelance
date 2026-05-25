<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payouts')) {
            return;
        }

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('commande_id');
            $table->enum('type', ['prestataire', 'remboursement_client']);
            $table->unsignedInteger('destinataire_id');
            $table->string('phone', 20);
            $table->string('withdraw_mode', 40);
            $table->string('mf_token_pay', 64)->nullable()->unique();
            $table->unsignedInteger('montant_fcfa');
            $table->enum('statut', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->json('webhook_log')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();

            $table->index('commande_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
