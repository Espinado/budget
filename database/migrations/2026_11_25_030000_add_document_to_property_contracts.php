<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_contracts', function (Blueprint $table) {
            $table->string('document_path')->nullable();
            $table->string('document_mime', 100)->nullable();
            $table->string('document_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('property_contracts', function (Blueprint $table) {
            $table->dropColumn(['document_path', 'document_mime', 'document_name']);
        });
    }
};
