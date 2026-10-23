<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', ['settings' => Setting::pluck('value', 'key')->all()]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            foreach (config('website') as $key => $field) {
                $setting = Setting::firstOrNew(['key' => $key]);
                $before = ['value' => $setting->value];
                $setting->value = $request->validated($key) ?? '';
                $setting->save();
                if ($before['value'] !== $setting->value) {
                    Audit::record('settings.updated', $setting, $before, ['value' => $setting->value]);
                }
            }
            foreach (['logo', 'favicon'] as $asset) {
                if ($request->hasFile($asset)) {
                    $path = $request->file($asset)->store('branding', 'public');
                    $setting = Setting::updateOrCreate(['key' => $asset.'_path'], ['value' => $path]);
                    Audit::record('settings.asset_updated', $setting);
                }
            }
        });

        return back()->with('success', 'Website settings saved.');
    }
}
