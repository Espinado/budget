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
            $table->unsignedTinyInteger('euribor_tenor_months')->nullable()->after('euribor_rate_bps');
            $table->date('rate_reset_on')->nullable()->after('euribor_tenor_months');
            $table->unsignedInteger('residual_cents')->default(0)->after('issuance_fee_cents');
        });

        DB::table('debts')->update([
            'annual_rate_bps' => DB::raw('annual_rate_bps * 10'),
            'fixed_rate_bps' => DB::raw('fixed_rate_bps * 10'),
            'euribor_rate_bps' => DB::raw('euribor_rate_bps * 10'),
        ]);
    }

    public function down(): void
    {
        DB::table('debts')->update([
            'annual_rate_bps' => DB::raw('annual_rate_bps / 10'),
            'fixed_rate_bps' => DB::raw('fixed_rate_bps / 10'),
            'euribor_rate_bps' => DB::raw('euribor_rate_bps / 10'),
        ]);

        Schema::table('debts', function (Blueprint $table) {
            $table->dropColumn(['euribor_tenor_months', 'rate_reset_on', 'residual_cents']);
        });
    }
};
