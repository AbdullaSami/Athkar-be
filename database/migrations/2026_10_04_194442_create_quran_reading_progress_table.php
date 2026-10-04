<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create application-owned records with explicit dependency rules. */
    public function up(): void
    {
        Schema::create('quran_reading_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('surah_number');
            $table->unsignedSmallInteger('ayah_number');
            $table->timestamp('last_read_at');
            $table->timestamps();
        });
    }

    /** Drop this table; rollback permanently removes its data. */
    public function down(): void
    {
        Schema::dropIfExists('quran_reading_progress');
    }
};
