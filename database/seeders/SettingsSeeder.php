<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('website') as $key => $field) {
            Setting::firstOrCreate(['key' => $key], ['value' => $field['default']]);
        }
    }
}
