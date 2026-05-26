<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clients') && ! Schema::hasColumn('clients', 'statut')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('statut', 20)->default('actif')->after('localite');
                $table->index('statut');
            });
        }

        if (Schema::hasTable('prestataires') && ! Schema::hasColumn('prestataires', 'statut')) {
            Schema::table('prestataires', function (Blueprint $table) {
                $table->string('statut', 20)->default('actif')->after('localite');
                $table->index('statut');
            });
        }

        if (Schema::hasTable('avis')) {
            Schema::table('avis', function (Blueprint $table) {
                if (! Schema::hasColumn('avis', 'signale')) {
                    $table->boolean('signale')->default(false)->after('commentaire');
                    $table->index('signale');
                }
                if (! Schema::hasColumn('avis', 'signale_motif')) {
                    $table->string('signale_motif', 500)->nullable()->after('signale');
                }
                if (! Schema::hasColumn('avis', 'signale_at')) {
                    $table->timestamp('signale_at')->nullable()->after('signale_motif');
                }
            });
        }

        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('nom', 100);
                $table->string('slug', 120)->unique();
                $table->unsignedSmallInteger('ordre')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (Schema::hasTable('categories') && DB::table('categories')->count() === 0) {
            $defaults = [
                'Développement', 'Design', 'Électricité', 'Plomberie',
                'Mobile App', 'Photographie', 'Marketing', 'Maçonnerie',
            ];
            foreach ($defaults as $i => $nom) {
                DB::table('categories')->insert([
                    'nom' => $nom,
                    'slug' => \Illuminate\Support\Str::slug($nom),
                    'ordre' => $i + 1,
                    'active' => true,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('categories')) {
            Schema::dropIfExists('categories');
        }

        if (Schema::hasTable('avis')) {
            Schema::table('avis', function (Blueprint $table) {
                foreach (['signale_at', 'signale_motif', 'signale'] as $col) {
                    if (Schema::hasColumn('avis', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        foreach (['clients', 'prestataires'] as $tbl) {
            if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'statut')) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->dropColumn('statut');
                });
            }
        }
    }
};
