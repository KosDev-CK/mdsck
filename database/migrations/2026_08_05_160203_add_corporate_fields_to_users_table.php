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
        Schema::table('users', function (Blueprint $table) {
            $table->string('company')->nullable()->after('name');
            $table->string('cedis')->nullable()->after('company');
            $table->string('area')->nullable()->after('cedis');
            $table->string('employee_number')->nullable()->after('area');
            $table->string('location')->nullable()->after('employee_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['company', 'cedis', 'area', 'employee_number', 'location']);
        });
    }
};
