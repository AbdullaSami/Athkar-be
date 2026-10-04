<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create application-owned records with explicit dependency rules. */
    public function up(): void
    {
        Schema::create('personal_wirds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('worship_identity_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->unsignedInteger('target_count');
            $table->unsignedBigInteger('current_count')->default(0);
            $table->index(['worship_identity_id', 'created_at']);
            $table->timestamps();
        });
    }

    /** Drop this table; rollback permanently removes its data. */
    public function down(): void
    {
        Schema::dropIfExists('personal_wirds');
    }
};
