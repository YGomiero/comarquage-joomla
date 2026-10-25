<?php
/**
 * Comarquage — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Site\Service;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;

defined('_JEXEC') or die;

class Router extends RouterView
{
    public function __construct(SiteApplication $app, AbstractMenu $menu)
    {
        foreach (['particuliers', 'associations', 'entreprises', 'recherche'] as $view) {
            $this->registerView(new RouterViewConfiguration($view));
        }

        parent::__construct($app, $menu);
        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    public function build(&$query): array
    {
        $itemId = isset($query['Itemid']) ? (int) $query['Itemid'] : 0;

        if ($itemId > 0) {
            $item = $this->menu->getItem($itemId);

            if ($item && isset($item->link)) {
                $menuQuery = [];
                parse_str((string) parse_url($item->link, PHP_URL_QUERY), $menuQuery);

                foreach (['view', 'theme'] as $key) {
                    if (isset($query[$key], $menuQuery[$key]) && (string) $query[$key] === (string) $menuQuery[$key]) {
                        unset($query[$key]);
                    }
                }
            }
        }

        return parent::build($query);
    }
}
