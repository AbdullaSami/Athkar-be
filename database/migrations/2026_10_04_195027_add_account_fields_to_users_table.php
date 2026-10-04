<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add SRS account fields without inventing values for existing users. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 20)->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->enum('account_status', ['active', 'suspended'])->default('active');
            $table->string('google_id', 255)->nullable()->unique();
            $table->string('password')->nullable()->change();
        });
    }

    /** Remove account fields only when all users retain password credentials. */
    public function down(): void
    {
        if (DB::table('users')->whereNull('password')->exists()) {
            throw new RuntimeException('Cannot rollback while passwordless accounts exist.');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['google_id']);
            $table->dropColumn(['phone', 'age', 'account_status', 'google_id']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
