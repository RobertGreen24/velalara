<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hosting_accounts', function (Blueprint $table) {
            $table->id(); $table->string('label', 100); $table->string('username', 15)->unique();
            $table->string('status')->default('pending'); $table->timestamps();
        });
        Schema::create('domains', function (Blueprint $table) {
            $table->id(); $table->foreignId('hosting_account_id')->constrained()->restrictOnDelete();
            $table->string('name', 253)->unique(); $table->string('status')->default('pending');
            $table->boolean('ssl')->default(false); $table->timestamps();
        });
        Schema::create('hosting_databases', function (Blueprint $table) {
            $table->id(); $table->foreignId('hosting_account_id')->constrained()->restrictOnDelete();
            $table->string('name')->unique(); $table->string('db_user')->unique(); $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('hosting_databases'); Schema::dropIfExists('domains'); Schema::dropIfExists('hosting_accounts');
    }
};
