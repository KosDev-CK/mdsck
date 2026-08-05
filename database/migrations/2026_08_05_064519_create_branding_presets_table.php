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
        Schema::create('branding_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('primary_color', 7);
            $table->string('success_color', 7);
            $table->string('danger_color', 7);
            $table->string('warning_color', 7);
            $table->string('info_color', 7);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branding_presets');
    }
};
