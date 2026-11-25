<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_objects', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->unique()->constrained()->nullOnDelete();
        });

        Schema::create('property_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_object_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('property_contract_plan_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_line_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique('plan_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_contract_plan_lines');
        Schema::dropIfExists('property_contracts');
        Schema::table('property_objects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
