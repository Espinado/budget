<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('direction');
            $table->string('counterparty');
            $table->string('contract_number')->nullable();
            $table->string('reference')->nullable();
            $table->unsignedInteger('annual_rate_bps')->default(0);
            $table->unsignedInteger('monthly_fee_cents')->default(0);
            $table->timestamps();
        });

        Schema::create('debt_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->date('due_on');
            $table->unsignedInteger('principal_before_cents');
            $table->unsignedInteger('principal_cents');
            $table->unsignedInteger('interest_cents');
            $table->unsignedInteger('other_cents')->default(0);
            $table->unsignedInteger('fee_cents')->default(0);
            $table->unsignedInteger('total_cents');
            $table->foreignId('plan_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('paid_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamps();
            $table->unique(['debt_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_installments');
        Schema::dropIfExists('debts');
    }
};
