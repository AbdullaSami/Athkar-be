<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create application-owned records with explicit dependency rules. */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('icon_path', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /** Drop this table; rollback permanently removes its data. */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
