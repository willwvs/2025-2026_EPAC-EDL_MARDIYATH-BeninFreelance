<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'kind')) {
                $table->string('kind', 40)->default('chat')->after('expediteur');
                $table->index('kind');
            }
            if (! Schema::hasColumn('messages', 'meta')) {
                $table->json('meta')->nullable()->after('contenu');
            }
            if (! Schema::hasColumn('messages', 'commande_id')) {
                $table->unsignedBigInteger('commande_id')->nullable()->after('service_id');
                $table->index('commande_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'kind')) {
                $table->dropIndex(['kind']);
                $table->dropColumn('kind');
            }
            if (Schema::hasColumn('messages', 'meta')) {
                $table->dropColumn('meta');
            }
            if (Schema::hasColumn('messages', 'commande_id')) {
                $table->dropIndex(['commande_id']);
                $table->dropColumn('commande_id');
            }
        });
    }
};
