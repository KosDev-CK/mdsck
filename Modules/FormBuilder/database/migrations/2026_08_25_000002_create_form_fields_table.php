<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('parent_field_id')->nullable()->constrained('form_fields')->cascadeOnDelete();
            $table->string('type');
            $table->string('label');
            $table->string('help_text')->nullable();
            $table->string('field_key');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['form_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};
