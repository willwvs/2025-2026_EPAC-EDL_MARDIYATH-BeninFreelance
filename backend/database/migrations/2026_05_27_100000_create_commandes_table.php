<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commandes')) {
            return;
        }

        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('client_id');
            $table->unsignedInteger('prestataire_id');
            $table->unsignedInteger('service_id');
            $table->string('titre', 200);
            $table->text('description');
            $table->unsignedInteger('montant_fcfa');
            $table->unsignedSmallInteger('delai_jours');
            $table->enum('statut', [
                'brouillon',
                'attente_prestataire',
                'attente_paiement',
                'payee',
                'livree',
                'en_revision',
                'terminee',
                'paid_out',
                'annulee',
                'remboursee',
            ])->default('brouillon');
            $table->unsignedInteger('commission_fcfa')->default(0);
            $table->unsignedInteger('net_prestataire_fcfa')->default(0);
            $table->string('motif_refus', 255)->nullable();
            $table->string('motif_annulation', 255)->nullable();
            $table->timestamp('acceptee_at')->nullable();
            $table->timestamp('payee_at')->nullable();
            $table->timestamp('livree_at')->nullable();
            $table->timestamp('review_until')->nullable();
            $table->timestamp('terminee_at')->nullable();
            $table->timestamp('paid_out_at')->nullable();
            $table->timestamp('remboursee_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('client_id');
            $table->index('prestataire_id');
            $table->index('service_id');
            $table->index('statut');
            $table->index('review_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commandes');
    }
};
