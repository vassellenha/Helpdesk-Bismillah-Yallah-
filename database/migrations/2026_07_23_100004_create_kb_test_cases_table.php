<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contoh pertanyaan uji. Polymorphic karena tiga pemilik yang sah:
 * kb_articles, kb_faqs, dan service_catalog_subjects.
 *
 * Uji dianggap lolos bila EVA menemukan sumber yang sama persis dengan
 * pemilik test case ini (runEvalCase di mockup).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_test_cases', function (Blueprint $table) {
            $table->id();
            $table->string('testable_type');
            $table->unsignedBigInteger('testable_id');
            $table->string('question', 500);
            $table->timestamps();

            $table->index(['testable_type', 'testable_id'], 'kb_test_cases_owner_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_test_cases');
    }
};
