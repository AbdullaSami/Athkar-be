<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create application-owned records with explicit dependency rules. */
    public function up(): void
    {
        Schema::create('prayer_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('calculation_method')->nullable();
            $table->unsignedTinyInteger('school')->default(0);
            $table->unsignedTinyInteger('high_latitude_method')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->boolean('notifications_enabled')->default(false);
            $table->timestamps();
        });
    }

    /** Drop this table; rollback permanently removes its data. */
    public function down(): void
    {
        Schema::dropIfExists('prayer_settings');
    }
};
