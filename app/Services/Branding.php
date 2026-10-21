<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves the platform brand (admin app) and a client's brand (subscriber-facing pages
 * and emails), and turns a brand colour into the CSS variables the theme uses.
 */
class Branding
{
    public const DEFAULT_COLOR = '#4f46e5';

    public static function appName(): string
    {
        return Setting::get('platform_name') ?: config('app.name');
    }

    public static function appLogo(): ?string
    {
        $path = Setting::get('platform_logo');

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public static function appColor(): string
    {
        return Setting::get('platform_color') ?: self::DEFAULT_COLOR;
    }

    public static function clientLogo(?Workspace $workspace): ?string
    {
        return $workspace?->logo_path ? url(Storage::disk('public')->url($workspace->logo_path)) : null;
    }

    public static function clientColor(?Workspace $workspace): string
    {
        return $workspace?->brand_color ?: self::appColor();
    }

    /** CSS overriding the theme's brand palette with $hex (Tailwind v4 reads these variables). */
    public static function css(string $hex): string
    {
        if (! preg_match('/^#[0-9a-fA-F]{6}$/', $hex) || strtolower($hex) === self::DEFAULT_COLOR) {
            return '';
        }

        return ":root{--color-brand-500:color-mix(in oklab,{$hex} 88%,white);--color-brand-600:{$hex};"
            ."--color-brand-700:color-mix(in oklab,{$hex} 82%,black);--color-brand-50:color-mix(in oklab,{$hex} 7%,white);"
            ."--color-brand-100:color-mix(in oklab,{$hex} 15%,white)}";
    }
}
