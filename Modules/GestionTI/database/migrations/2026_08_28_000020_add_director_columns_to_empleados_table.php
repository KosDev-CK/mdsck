<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->foreignId('director_id')->nullable()->after('jefe_inmediato_id')->constrained('empleados')->nullOnDelete();
            $table->foreignId('director_ejecutivo_id')->nullable()->after('director_id')->constrained('empleados')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropConstrainedForeignId('director_ejecutivo_id');
            $table->dropConstrainedForeignId('director_id');
        });
    }
};
