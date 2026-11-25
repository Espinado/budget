<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->string('interest_mode')->default('schedule');
            $table->unsignedInteger('principal_cents')->nullable();
            $table->unsignedInteger('accrued_cents')->default(0);
            $table->date('interest_through')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->dropColumn(['interest_mode', 'principal_cents', 'accrued_cents', 'interest_through']);
        });
    }
};
