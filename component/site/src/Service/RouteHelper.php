<?php
/**
 * Distro — Service-Public Open Data for Joomla.
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

    public static function getViewRoute(string $view, array $query = []): string
    {
        $query = ['option' => 'com_comarquage', 'view' => $view] + $query;
        $itemid = self::getItemid($view);

        if ($itemid > 0) {
            $query['Itemid'] = $itemid;
        }

        return Route::_('index.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986), false);
    }

    public static function getFicheRoute(int|string $id, string $universe = 'particuliers', array $query = [], string $language = '*'): string
    {
        return self::getViewRoute($universe, ['id' => (string) $id] + $query);
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

    private static function getItemid(string $view): int
    {
        if (array_key_exists($view, self::$itemids)) {
            return self::$itemids[$view];
        }

        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $link = 'index.php?option=com_comarquage&view=' . $view;
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('link') . ' = :link')
            ->where($db->quoteName('parent_id') . ' = 1')
            ->order($db->quoteName('id') . ' DESC')
            ->bind(':link', $link);

        self::$itemids[$view] = (int) $db->setQuery($query, 0, 1)->loadResult();

        return self::$itemids[$view];
    }
}
