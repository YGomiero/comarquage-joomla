<?php
/**
 * Distro — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Site\Service;

defined('_JEXEC') or die;

class LayoutThemeService
{
    public static function cssPath(string $id): ?string
    {
        $id = self::sanitize($id);
        if ($id === '') {
            return null;
        }

        $file = JPATH_ROOT . '/media/com_comarquage/themes/' . $id . '.css';

        return is_file($file) ? '/media/com_comarquage/themes/' . $id . '.css' : null;
    }

    public static function config(string $id): array
    {
        static $cache = [];

        $id = self::sanitize($id);

        if (isset($cache[$id])) {
            return $cache[$id];
        }

        $file = JPATH_ROOT . '/media/com_comarquage/themes/' . $id . '.json';
        $cache[$id] = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];

        return $cache[$id];
    }

    public static function iconFor(string $title, string $fallback, string $themeId): string
    {
        $icons = self::config($themeId)['icons'] ?? [];
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title;

        foreach ($icons as $needle => $icon) {
            if (stripos($normalized, (string) $needle) !== false || stripos($title, (string) $needle) !== false) {
                $icon = (string) $icon;

                if (str_starts_with($icon, 'svg:')) {
                    $filename = basename(substr($icon, 4));

                    return preg_match('/^[a-z0-9_.-]+\.svg$/i', $filename) ? 'svg:' . $filename : $fallback;
                }

                return preg_replace('/[^a-z0-9 -]/i', '', $icon) ?: $fallback;
            }
        }

        return $fallback;
    }

    public static function iconHtml(string $icon, string $extraClass = ''): string
    {
        $extraClass = trim(preg_replace('/[^a-z0-9 _-]/i', '', $extraClass) ?? '');

        if (str_starts_with($icon, 'svg:')) {
            $filename = basename(substr($icon, 4));

            if (preg_match('/^[a-z0-9_.-]+\.svg$/i', $filename)) {
                $class = trim('comarquage-icon-img ' . $extraClass);
                $src = htmlspecialchars(\Joomla\CMS\Uri\Uri::root(true) . '/media/com_comarquage/icons/' . $filename, ENT_QUOTES, 'UTF-8');

                return '<img class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" src="' . $src . '" alt="" loading="lazy" decoding="async">';
            }
        }

        $class = trim('fa-solid ' . (preg_replace('/[^a-z0-9 -]/i', '', $icon) ?: 'fa-circle-info') . ' ' . $extraClass);

        return '<i class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i>';
    }

    private static function sanitize(string $id): string
    {
        return preg_match('/^[a-z0-9_-]+$/', $id) ? $id : '';
    }
}
