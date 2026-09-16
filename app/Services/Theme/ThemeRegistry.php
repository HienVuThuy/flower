<?php

namespace App\Services\Theme;

use App\Models\Setting;
use Illuminate\Support\Facades\Vite;

/** Nơi DUY NHẤT biết theme nào đang bật và mỗi theme gồm những gì. */
class ThemeRegistry
{
    private ?array $themes = null;

    public function all(): array
    {
        return $this->themes ??= config('theme.themes', []);
    }

    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function activeKey(): string
    {
        $key = Setting::get('theme', config('theme.active', 'default'));

        return $this->exists($key) ? $key : 'default';
    }

    public function get(string $key): array
    {
        return $this->all()[$key] ?? $this->all()['default'] ?? [];
    }

    public function active(): array
    {
        return $this->get($this->activeKey());
    }

    public function label(?string $key): string
    {
        if ($key === null) {
            return '—';
        }

        return $this->get($key)['label'] ?? $key;
    }

    public function activeEffect(): ?string
    {
        return $this->active()['effect'] ?? null;
    }

    public function heroImages(): array
    {
        $custom = HeroImages::forTheme($this->activeKey());

        if ($custom) {
            return array_map(
                fn (array $i) => ['url' => $i['url'], 'alt' => $i['alt']],
                $custom,
            );
        }

        $images = $this->active()['hero'] ?? [];

        $out = [];

        foreach ($images as $item) {
            if (! is_array($item) || empty($item['file'])) {
                continue;
            }

            $out[] = [
                'url' => Vite::asset('resources/images/hero/' . $item['file']),
                'alt' => (string) ($item['alt'] ?? ''),
            ];
        }

        return $out;
    }

    public function customHeroImages(string $themeKey): array
    {
        return HeroImages::forTheme($themeKey);
    }

    public function options(): array
    {
        return array_map(fn (array $t) => $t['label'], $this->all());
    }
}
