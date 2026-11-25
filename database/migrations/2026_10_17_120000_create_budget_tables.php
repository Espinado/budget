<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('currency', 3)->default('EUR');
            $table->string('timezone')->default('Europe/Riga');
            $table->unsignedTinyInteger('anchor_day')->default(1);
            $table->timestamps();
        });

        Schema::create('household_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('owner');
            $table->timestamps();
            $table->unique(['household_id', 'user_id']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->string('color', 7)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->bigInteger('opening_balance_cents')->default(0);
            $table->boolean('opening_manual')->default(false);
            $table->boolean('custom_bounds')->default(false);
            $table->string('status')->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['household_id', 'starts_on']);
            $table->index(['household_id', 'ends_on']);
        });

        Schema::create('plan_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->bigInteger('amount_cents');
            $table->unsignedTinyInteger('expected_day')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('plan_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('budget_periods')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('plan_templates')->nullOnDelete();
            $table->string('title');
            $table->bigInteger('amount_cents');
            $table->date('expected_on')->nullable();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('budget_periods')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->bigInteger('amount_cents');
            $table->date('occurred_on');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['period_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('plan_lines');
        Schema::dropIfExists('plan_templates');
        Schema::dropIfExists('budget_periods');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('household_user');
        Schema::dropIfExists('households');
    }
};
