<?php
/**
 * Comarquage — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Plugin\Task\Comarquageimport\Extension\Comarquageimport;

require_once JPATH_ADMINISTRATOR . '/components/com_scheduler/src/Traits/TaskPluginTrait.php';
require_once __DIR__ . '/../src/Extension/Comarquageimport.php';

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(PluginInterface::class, $container->lazy(Comarquageimport::class, function (Container $container) {
            $plugin = new Comarquageimport((array) PluginHelper::getPlugin('task', 'comarquageimport'), $container->get(DatabaseInterface::class));
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        }));
    }
};
