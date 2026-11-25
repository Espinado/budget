<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secret_document_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('secret_documents', function (Blueprint $table) {
            $table->foreignId('secret_document_section_id')
                ->nullable()
                ->constrained('secret_document_sections')
                ->cascadeOnDelete();
        });

        $now = now();

        foreach (DB::table('secret_documents')->orderBy('id')->get() as $document) {
            $name = trim((string) $document->name);
            if ($name === '') {
                $name = (string) $document->original_name;
            }

            $sectionId = DB::table('secret_document_sections')->insertGetId([
                'user_id' => $document->user_id,
                'name' => $name,
                'sort' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('secret_documents')->where('id', $document->id)->update([
                'secret_document_section_id' => $sectionId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('secret_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('secret_document_section_id');
        });

        Schema::dropIfExists('secret_document_sections');
    }
};
