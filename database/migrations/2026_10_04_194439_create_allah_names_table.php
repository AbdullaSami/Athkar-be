<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create application-owned records with explicit dependency rules. */
    public function up(): void
    {
        Schema::create('allah_names', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('number');
            $table->string('arabic_name', 160);
            $table->string('transliteration', 160);
            $table->text('meaning');
            $table->string('language', 12);
            $table->unique(['number', 'language']);
            $table->timestamps();
        });
    }

    /** Drop this table; rollback permanently removes its data. */
    public function down(): void
    {
        Schema::dropIfExists('allah_names');
    }
};
