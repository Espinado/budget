<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind');
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('property_object_debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_object_id')->constrained()->cascadeOnDelete();
            $table->foreignId('debt_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique('debt_id');
        });

        Schema::create('property_object_plan_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_object_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_line_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique('plan_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_object_plan_lines');
        Schema::dropIfExists('property_object_debts');
        Schema::dropIfExists('property_objects');
    }
};
