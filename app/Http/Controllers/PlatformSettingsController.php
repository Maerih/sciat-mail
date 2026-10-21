<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admin-only white-label settings for the platform itself. */
class PlatformSettingsController extends Controller
{
    public function edit(): View
    {
        return view('platform-settings', [
            'name' => Setting::get('platform_name'),
            'color' => Setting::get('platform_color'),
            'logo' => Setting::get('platform_logo'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'platform_name' => 'nullable|string|max:60',
            'platform_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'remove_logo' => 'boolean',
        ]);

        $values = ['platform_name' => $data['platform_name'] ?? null, 'platform_color' => $data['platform_color'] ?? null];
        if ($request->hasFile('logo')) {
            $values['platform_logo'] = $request->file('logo')->store('branding', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $values['platform_logo'] = null;
        }
        Setting::put($values);

        return back()->with('success', 'Platform branding saved.');
    }
}
