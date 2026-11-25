<?php

use App\Support\CategoryIcons;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('icon', 40)->nullable()->after('name');
        });

        foreach (DB::table('categories')->select('id', 'name', 'type')->get() as $category) {
            DB::table('categories')->where('id', $category->id)->update([
                'icon' => CategoryIcons::guess($category->name, (string) $category->type),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
