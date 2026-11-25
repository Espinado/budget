<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->unsignedInteger('fixed_rate_bps')->default(0)->after('annual_rate_bps');
            $table->unsignedInteger('euribor_rate_bps')->default(0)->after('fixed_rate_bps');
        });

        DB::table('debts')
            ->where('fixed_rate_bps', 0)
            ->where('euribor_rate_bps', 0)
            ->update(['fixed_rate_bps' => DB::raw('annual_rate_bps')]);
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->dropColumn(['fixed_rate_bps', 'euribor_rate_bps']);
        });
    }
};
