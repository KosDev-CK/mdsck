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
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('topbar_color', 7)->default('#FFFFFF')->after('info_color');
            $table->string('sidebar_header_color', 7)->default('#111827')->after('topbar_color');
            $table->string('sidebar_body_color', 7)->default('#111827')->after('sidebar_header_color');
        });

        Schema::table('branding_presets', function (Blueprint $table) {
            $table->string('topbar_color', 7)->default('#FFFFFF')->after('info_color');
            $table->string('sidebar_header_color', 7)->default('#111827')->after('topbar_color');
            $table->string('sidebar_body_color', 7)->default('#111827')->after('sidebar_header_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['topbar_color', 'sidebar_header_color', 'sidebar_body_color']);
        });

        Schema::table('branding_presets', function (Blueprint $table) {
            $table->dropColumn(['topbar_color', 'sidebar_header_color', 'sidebar_body_color']);
        });
    }
};
