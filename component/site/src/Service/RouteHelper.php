<?php
/**
 * Comarquage — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Site\Service;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die;

class RouteHelper
{
    private static array $itemids = [];

    public static function getViewRoute(string $view, array $query = [], string $language = '*'): string
    {
        $query = ['option' => 'com_comarquage', 'view' => $view] + $query;
        $itemid = self::getItemid($view, $language);

        if ($itemid > 0) {
            $query['Itemid'] = $itemid;
        }

        $url = 'index.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        // Finder indexes raw site routes in administrator/CLI contexts.
        return Factory::getApplication()->isClient('site') ? (Route::_($url, false) ?? $url) : $url;
    }

    public static function getFicheRoute(int|string $id, string $universe = 'particuliers', array $query = [], string $language = '*'): string
    {
        return self::getViewRoute($universe, ['id' => (string) $id] + $query, $language);
    }

    public static function getReturnToken(): string
    {
        return rtrim(strtr(base64_encode(self::cleanReturnUrl(Uri::getInstance()->toString())), '+/', '-_'), '=');
    }

    public static function getReturnTokenForUrl(string $url): string
    {
        if (!str_starts_with($url, Uri::root())) {
            $url = rtrim(Uri::root(), '/') . '/' . ltrim($url, '/');
        }

        return rtrim(strtr(base64_encode(self::cleanReturnUrl($url)), '+/', '-_'), '=');
    }

    public static function getReturnUrl(string $fallbackView): string
    {
        $token = Factory::getApplication()->getInput()->getString('return', '');

        if ($token !== '') {
            $base64 = strtr($token, '-_', '+/');
            $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
            $decoded = base64_decode($base64, true);
            if (is_string($decoded) && $decoded !== '' && str_starts_with($decoded, Uri::root())) {
                return self::cleanReturnUrl($decoded);
            }
        }

        return self::getViewRoute($fallbackView);
    }

    private static function cleanReturnUrl(string $url): string
    {
        $uri = new Uri($url);
        $query = $uri->getQuery(true);

        unset($query['tmpl'], $query['format']);

        $uri->setQuery($query);

        return $uri->toString();
    }

    private static function getItemid(string $view, string $language): int
    {
        $app = Factory::getApplication();
        $site = $app->isClient('site');
        if ($language === '*' && $site) {
            $language = $app->getLanguage()->getTag();
        }
        $activeId = $site ? (int) ($app->getMenu()->getActive()->id ?? 0) : 0;
        $levels = $site ? $app->getIdentity()->getAuthorisedViewLevels() : [1];
        $cacheKey = $view . ':' . $language . ':' . $activeId . ':' . implode(',', $levels);
        if (array_key_exists($cacheKey, self::$itemids)) {
            return self::$itemids[$cacheKey];
        }

        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $link = 'index.php?option=com_comarquage&view=' . $view;
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'language', 'access']))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('link') . ' = :link')
            ->order($db->quoteName('id') . ' ASC')
            ->bind(':link', $link);

        self::$itemids[$cacheKey] = self::selectMenuItem($db->setQuery($query)->loadAssocList(), $language, $levels, $activeId);

        return self::$itemids[$cacheKey];
    }

    /** Prefer an accessible menu in the requested language, then an all-language fallback. */
    public static function selectMenuItem(array $items, string $language, array $levels, int $activeId = 0): int
    {
        $selected = 0;
        $bestScore = -1;
        foreach ($items as $item) {
            $tag = (string) ($item['language'] ?? '*');
            if (!in_array((int) $item['access'], array_map('intval', $levels), true) || ($tag !== $language && $tag !== '*')) {
                continue;
            }
            $score = ($tag === $language ? 2 : 0) + ((int) $item['id'] === $activeId ? 1 : 0);
            if ($score > $bestScore) {
                $selected = (int) $item['id'];
                $bestScore = $score;
            }
        }
        return $selected;
    }
}
