<?php
/**
 * Comarquage — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\Service;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;

defined('_JEXEC') or die;

class CacheService
{
    public function get(string $key, callable $callback, int $ttlHours): mixed
    {
        $cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController('callback', [
            'defaultgroup' => 'com_comarquage_data',
            'lifetime' => max(1, $ttlHours) * 60,
            'caching' => true,
        ]);

        return $cache->get($callback, [], $key);
    }

    public function clear(): void
    {
        $cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController('output', [
            'defaultgroup' => 'com_comarquage_data',
            'caching' => true,
        ]);

        $cache->clean('com_comarquage_data');
        $cache->clean('com_comarquage_lock');

        foreach ([JPATH_CACHE . '/com_comarquage', JPATH_ROOT . '/tmp/com_comarquage/cache'] as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach (glob($directory . '/*') ?: [] as $file) {
                if (is_file($file) && preg_match('/\.(html|json|lock)$/', $file)) {
                    @unlink($file);
                }
            }
        }
    }
}
