<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'photo_path')) {
                $table->string('photo_path', 255)->nullable()->after('delai_execution');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('services') && Schema::hasColumn('services', 'photo_path')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('photo_path');
            });
        }
    }
};
