<?php
declare(strict_types=1);

namespace App\Core;

/**
 * i18n / Localization — translation and timezone support.
 *
 * Usage:
 *   Locale::set('en');
 *   echo Locale::trans('messages.welcome');           // "Welcome"
 *   echo Locale::trans('messages.count', ['count' => 5]); // "5 items"
 *   echo Locale::now('Y-m-d H:i:s');                 // formatted datetime in current tz
 *
 * Files: resources/lang/{locale}/{file}.php returning ['key' => 'value']
 */
class Locale
{
    protected static string $current = 'en';
    protected static string $fallback = 'en';
    protected static array $translations = [];
    protected static string $timezone = 'UTC';

    /**
     * Set the active locale.
     */
    public static function set(string $locale): void
    {
        self::$current = $locale;
        self::loadTranslations($locale);
    }

    /**
     * Get the active locale.
     */
    public static function get(): string
    {
        return self::$current;
    }

    /**
     * Set the fallback locale.
     */
    public static function setFallback(string $locale): void
    {
        self::$fallback = $locale;
    }

    /**
     * Set the default timezone.
     */
    public static function setTimezone(string $tz): void
    {
        self::$timezone = $tz;
        date_default_timezone_set($tz);
    }

    /**
     * Get the current timezone.
     */
    public static function getTimezone(): string
    {
        return self::$timezone;
    }

    /**
     * Translate a key.
     *
     * @param string $key Dot-notation key (e.g. 'messages.welcome')
     * @param array $replace Replacement placeholders
     * @return string
     */
    public static function trans(string $key, array $replace = []): string
    {
        $value = self::getTranslation($key);

        if ($value === null) {
            return $key;
        }

        foreach ($replace as $placeholder => $replacement) {
            $value = str_replace(':'.strtolower((string)$placeholder), (string)$replacement, $value);
        }

        return $value;
    }

    /**
     * Translate with pluralization.
     *
     * @param string $single Singular form key
     * @param string $plural Plural form key
     * @param int $number Number of items
     * @param array $replace
     * @return string
     */
    public static function transChoice(string $single, string $plural, int $number, array $replace = []): string
    {
        $key = $number === 1 ? $single : $plural;
        return self::trans($key, $replace + ['count' => $number]);
    }

    /**
     * Format a datetime in the current timezone.
     */
    public static function now(string $format = 'Y-m-d H:i:s'): string
    {
        return date($format);
    }

    /**
     * Format a datetime string in the current timezone.
     */
    public static function formatDateTime(string $datetime, string $fromFormat = 'Y-m-d H:i:s', string $toFormat = 'Y-m-d H:i:s'): string
    {
        $date = \DateTime::createFromFormat($fromFormat, $datetime);
        if ($date === false) {
            return $datetime;
        }
        $date->setTimezone(new \DateTimeZone(self::$timezone));
        return $date->format($toFormat);
    }

    /**
     * Get a translated value by key.
     */
    protected static function getTranslation(string $key): ?string
    {
        // Try current locale first
        if (isset(self::$translations[self::$current][$key])) {
            return self::$translations[self::$current][$key];
        }

        // Fallback to base locale
        if (self::$current !== self::$fallback) {
            if (isset(self::$translations[self::$fallback][$key])) {
                return self::$translations[self::$fallback][$key];
            }
        }

        return null;
    }

    /**
     * Load translation files for a locale.
     */
    protected static function loadTranslations(string $locale): void
    {
        $langPath = dirname(__DIR__) . '/resources/lang';

        // Load all files for this locale
        if (is_dir("{$langPath}/{$locale}")) {
            foreach (glob("{$langPath}/{$locale}/*.php") as $file) {
                $data = require $file;
                if (is_array($data)) {
                    self::$translations[$locale] = array_merge(self::$translations[$locale] ?? [], $data);
                }
            }
        }

        // Also load fallback
        if ($locale !== self::$fallback) {
            $fallbackDir = "{$langPath}/" . self::$fallback;
            if (is_dir($fallbackDir)) {
                foreach (glob("{$fallbackDir}/*.php") as $file) {
                    $data = require $file;
                    if (is_array($data)) {
                        self::$translations[self::$fallback] = array_merge(self::$translations[self::$fallback] ?? [], $data);
                    }
                }
            }
        }
    }

    /**
     * Get list of available locales.
     */
    public static function available(): array
    {
        $langPath = dirname(__DIR__) . '/resources/lang';
        if (!is_dir($langPath)) {
            return [self::$fallback];
        }
        return array_merge(
            array_map('basename', glob("{$langPath}/*/")),
            [self::$fallback]
        );
    }
}
