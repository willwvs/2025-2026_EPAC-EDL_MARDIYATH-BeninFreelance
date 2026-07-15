<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        $rows = DB::table('categories')->orderBy('ordre')->orderBy('nom')->orderBy('id')->get(['id']);
        $n = 1;
        foreach ($rows as $row) {
            DB::table('categories')->where('id', $row->id)->update(['ordre' => $n]);
            $n++;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM categories'))
            ->pluck('Key_name')
            ->all();

        if (! in_array('categories_ordre_unique', $indexes, true)) {
            Schema::table('categories', function (Blueprint $table) {
                $table->unique('ordre', 'categories_ordre_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM categories'))
            ->pluck('Key_name')
            ->all();

        if (in_array('categories_ordre_unique', $indexes, true)) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropUnique('categories_ordre_unique');
            });
        }
    }
};
