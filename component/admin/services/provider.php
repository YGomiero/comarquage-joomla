<?php
/**
 * Distro — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Component\Comarquage\Administrator\Extension\ComarquageComponent;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->registerServiceProvider(new MVCFactory('\\Joomla\\Component\\Comarquage'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Joomla\\Component\\Comarquage'));
        $container->registerServiceProvider(new RouterFactory('\\Joomla\\Component\\Comarquage'));

        $container->set(ComponentInterface::class, function (Container $container) {
            $component = new ComarquageComponent($container->get(ComponentDispatcherFactoryInterface::class));
            $component->setMVCFactory($container->get(MVCFactoryInterface::class));
            $component->setRouterFactory($container->get(RouterFactoryInterface::class));

            return $component;
        });
    }
};
