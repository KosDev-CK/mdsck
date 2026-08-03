<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('database_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->string('module')->nullable()->index();
            $table->string('driver');
            $table->enum('mode', ['pool', 'single'])->default('single');
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('database')->nullable();
            $table->text('username')->nullable();
            $table->text('password')->nullable();
            $table->string('base_url')->nullable();
            $table->text('extra')->nullable();
            $table->unsignedInteger('pool_min')->nullable();
            $table->unsignedInteger('pool_max')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_connections');
    }
};
