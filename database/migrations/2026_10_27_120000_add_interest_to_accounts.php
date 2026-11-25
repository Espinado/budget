<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts', 'annual_rate_bps')) {
                $table->unsignedInteger('annual_rate_bps')->default(0)->after('balance_cents');
            }

            if (! Schema::hasColumn('accounts', 'last_interest_on')) {
                $table->date('last_interest_on')->nullable()->after('annual_rate_bps');
            }
        });

        if (! Schema::hasTable('account_interest_entries')) {
            Schema::create('account_interest_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained()->cascadeOnDelete();
                $table->foreignId('household_id')->constrained()->cascadeOnDelete();
                $table->bigInteger('amount_cents');
                $table->unsignedInteger('annual_rate_bps');
                $table->bigInteger('balance_before_cents');
                $table->date('accrued_on');
                $table->timestamps();

                $table->unique(['account_id', 'accrued_on']);
                $table->index(['household_id', 'accrued_on']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_interest_entries');

        Schema::table('accounts', function (Blueprint $table) {
            if (Schema::hasColumn('accounts', 'annual_rate_bps')) {
                $table->dropColumn('annual_rate_bps');
            }

            if (Schema::hasColumn('accounts', 'last_interest_on')) {
                $table->dropColumn('last_interest_on');
            }
        });
    }
};
