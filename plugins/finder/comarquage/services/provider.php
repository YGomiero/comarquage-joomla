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
use Joomla\Plugin\Finder\Comarquage\Extension\Comarquage;

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(PluginInterface::class, $container->lazy(Comarquage::class, function (Container $container) {
            $plugin = new Comarquage((array) PluginHelper::getPlugin('finder', 'comarquage'));
            $plugin->setApplication(Factory::getApplication());
            $plugin->setDatabase($container->get(DatabaseInterface::class));
            return $plugin;
        }));
    }
};