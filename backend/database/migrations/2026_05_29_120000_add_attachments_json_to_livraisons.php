<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commande_livraisons')) {
            return;
        }

        Schema::table('commande_livraisons', function (Blueprint $table) {
            if (! Schema::hasColumn('commande_livraisons', 'attachments')) {
                $table->json('attachments')->nullable()->after('message');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('commande_livraisons')) {
            return;
        }

        Schema::table('commande_livraisons', function (Blueprint $table) {
            if (Schema::hasColumn('commande_livraisons', 'attachments')) {
                $table->dropColumn('attachments');
            }
        });
    }
};
