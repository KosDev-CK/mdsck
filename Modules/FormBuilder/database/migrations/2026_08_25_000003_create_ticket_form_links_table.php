<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_form_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->restrictOnDelete();
            $table->string('ticket_number');
            $table->string('recipient_email');
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedTinyInteger('failed_verify_attempts')->default(0);
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['created_by', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_form_links');
    }
};
