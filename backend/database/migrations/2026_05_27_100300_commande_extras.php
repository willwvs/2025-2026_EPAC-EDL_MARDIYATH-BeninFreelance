<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commande_livraisons')) {
            Schema::create('commande_livraisons', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('commande_id');
                $table->unsignedSmallInteger('version')->default(1);
                $table->text('message')->nullable();
                $table->string('attachment_path', 255)->nullable();
                $table->string('attachment_name', 255)->nullable();
                $table->unsignedInteger('attachment_size')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('commande_id');
            });
        }

        if (Schema::hasTable('prestataires')) {
            Schema::table('prestataires', function (Blueprint $table) {
                if (! Schema::hasColumn('prestataires', 'mm_phone')) {
                    $table->string('mm_phone', 20)->nullable()->after('telephone');
                }
                if (! Schema::hasColumn('prestataires', 'mm_mode')) {
                    $table->enum('mm_mode', ['mtn-benin', 'moov-benin'])->nullable()->after('mm_phone');
                }
            });
        }

        if (Schema::hasTable('avis') && ! Schema::hasColumn('avis', 'commande_id')) {
            Schema::table('avis', function (Blueprint $table) {
                $table->unsignedBigInteger('commande_id')->nullable()->unique()->after('prestataire_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('commande_livraisons')) {
            Schema::dropIfExists('commande_livraisons');
        }

        if (Schema::hasTable('prestataires')) {
            Schema::table('prestataires', function (Blueprint $table) {
                if (Schema::hasColumn('prestataires', 'mm_mode')) {
                    $table->dropColumn('mm_mode');
                }
                if (Schema::hasColumn('prestataires', 'mm_phone')) {
                    $table->dropColumn('mm_phone');
                }
            });
        }

        if (Schema::hasTable('avis') && Schema::hasColumn('avis', 'commande_id')) {
            Schema::table('avis', function (Blueprint $table) {
                $table->dropColumn('commande_id');
            });
        }
    }
};
