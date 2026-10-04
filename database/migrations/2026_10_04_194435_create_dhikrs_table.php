<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create application-owned records with explicit dependency rules. */
    public function up(): void
    {
        Schema::create('dhikrs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subcategory_id')->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->text('text');
            $table->text('description');
            $table->text('source');
            $table->text('virtue');
            $table->text('explanation')->nullable();
            $table->unsignedInteger('repetition_target')->nullable();
            $table->string('icon_path', 255)->nullable();
            $table->string('audio_path', 255)->nullable();
            $table->enum('publication_status', ['draft', 'published'])->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->index(['subcategory_id', 'publication_status', 'sort_order']);
            $table->timestamps();
        });
    }

    /** Drop this table; rollback permanently removes its data. */
    public function down(): void
    {
        Schema::dropIfExists('dhikrs');
    }
};
