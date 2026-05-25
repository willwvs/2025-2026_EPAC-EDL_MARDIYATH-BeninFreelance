<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commandes')) {
            return;
        }

        Schema::table('commandes', function (Blueprint $table) {
            if (! Schema::hasColumn('commandes', 'litige_at')) {
                $table->timestamp('litige_at')->nullable()->after('remboursee_at');
            }
            if (! Schema::hasColumn('commandes', 'litige_motif')) {
                $table->text('litige_motif')->nullable()->after('litige_at');
            }
            if (! Schema::hasColumn('commandes', 'litige_resolution')) {
                $table->string('litige_resolution', 40)->nullable()->after('litige_motif');
            }
            if (! Schema::hasColumn('commandes', 'litige_resolution_note')) {
                $table->text('litige_resolution_note')->nullable()->after('litige_resolution');
            }
            if (! Schema::hasColumn('commandes', 'litige_resolved_at')) {
                $table->timestamp('litige_resolved_at')->nullable()->after('litige_resolution_note');
            }
            if (! Schema::hasColumn('commandes', 'reminders_sent')) {
                $table->json('reminders_sent')->nullable()->after('review_until');
            }
        });

        // Étend l'enum de statut pour inclure 'litige'.
        try {
            DB::statement("ALTER TABLE commandes MODIFY COLUMN statut ENUM(
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
                'litige'
            ) DEFAULT 'brouillon'");
        } catch (\Throwable $e) {
            // Si SGBD ne supporte pas (SQLite) on ignore.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('commandes')) {
            return;
        }

        Schema::table('commandes', function (Blueprint $table) {
            foreach ([
                'litige_at', 'litige_motif', 'litige_resolution',
                'litige_resolution_note', 'litige_resolved_at', 'reminders_sent',
            ] as $col) {
                if (Schema::hasColumn('commandes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
