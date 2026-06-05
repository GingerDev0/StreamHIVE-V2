<?php

declare(strict_types=1);

namespace App\Services;

final class SiteSettings
{
    private const DEFAULTS = [
        'home_alert_enabled' => true,
        'home_alert_message' => 'As people browse the site, more videos unlock over time.',
        'home_alert_subtext' => 'This website is owned and maintained by GingerDev.',
    ];

    public static function all(): array
    {
        $settings = self::read();
        return array_replace(self::DEFAULTS, $settings);
    }

    public static function updateHomeAlert(bool $enabled, string $message, string $subtext): void
    {
        $settings = self::all();
        $settings['home_alert_enabled'] = $enabled;
        $settings['home_alert_message'] = self::cleanText($message, self::DEFAULTS['home_alert_message']);
        $settings['home_alert_subtext'] = self::cleanText($subtext, self::DEFAULTS['home_alert_subtext']);
        self::write($settings);
    }

    private static function cleanText(string $value, string $fallback): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        return $value !== '' ? $value : $fallback;
    }

    private static function read(): array
    {
        $file = self::path();
        if (!is_file($file)) {
            return [];
        }

        $json = json_decode((string)file_get_contents($file), true);
        return is_array($json) ? $json : [];
    }

    private static function write(array $settings): void
    {
        $file = self::path();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);
    }

    private static function path(): string
    {
        return storage_path('site-settings.json');
    }
}
