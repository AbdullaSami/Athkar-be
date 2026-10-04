<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create application-owned records with explicit dependency rules. */
    public function up(): void
    {
        Schema::create('worship_identities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->string('guest_token_hash', 64)->nullable()->unique();
            $table->timestamps();
        });
    }

    /** Drop this table; rollback permanently removes its data. */
    public function down(): void
    {
        Schema::dropIfExists('worship_identities');
    }
};
