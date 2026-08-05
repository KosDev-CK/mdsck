<?php

namespace Database\Seeders;

use App\Models\BrandingPreset;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class BrandingPresetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SiteSetting::current();

        $presets = [
            [
                'name' => 'LandIT',
                'primary_color' => '#F36522',
                'success_color' => '#4C9A63',
                'danger_color' => '#D6432A',
                'warning_color' => '#F2B705',
                'info_color' => '#2C7FB8',
                'is_system' => true,
            ],
            [
                'name' => 'Corporativo Kosmos',
                'primary_color' => '#E41D26',
                'success_color' => '#8CB81F',
                'danger_color' => '#9D1E27',
                'warning_color' => '#F39200',
                'info_color' => '#0E6199',
                'is_system' => true,
            ],
        ];

        foreach ($presets as $preset) {
            BrandingPreset::updateOrCreate(['name' => $preset['name']], $preset);
        }
    }
}
