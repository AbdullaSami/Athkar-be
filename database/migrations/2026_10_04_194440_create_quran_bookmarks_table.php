<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create application-owned records with explicit dependency rules. */
    public function up(): void
    {
        Schema::create('quran_bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('surah_number');
            $table->unsignedSmallInteger('ayah_number');
            $table->string('label', 160)->nullable();
            $table->unique(['user_id', 'surah_number', 'ayah_number']);
            $table->timestamps();
        });
    }

    /** Drop this table; rollback permanently removes its data. */
    public function down(): void
    {
        Schema::dropIfExists('quran_bookmarks');
    }
};
