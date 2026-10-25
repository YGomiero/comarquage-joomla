<?php
/**
 * Comarquage — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Site\View\Particuliers;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Component\Comarquage\Site\Service\LayoutThemeService;
use Joomla\Component\Comarquage\Site\Service\ViewCacheService;

defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    public array $items = [];
    public array $themes = [];
    public $pagination = null;
    public ?array $item = null;
    public array $homePanels = [];
    public array $subthemes = [];
    public string $rubrique = '';

    public function display($tpl = null): void
    {
        $this->getDocument()->addStyleSheet(\Joomla\CMS\Uri\Uri::root(true) . '/media/system/css/joomla-fontawesome.css');
        $this->getDocument()->addStyleSheet(\Joomla\CMS\Uri\Uri::root(true) . '/media/com_comarquage/css/site.css');
        $theme = (string) ComponentHelper::getParams('com_comarquage')->get('layout_theme', 'default');
        $themeCss = LayoutThemeService::cssPath($theme);
        if ($themeCss) {
            $this->getDocument()->addStyleSheet(\Joomla\CMS\Uri\Uri::root(true) . $themeCss);
        }
        $script = '/media/com_comarquage/js/site.js';
        $scriptVersion = is_file(JPATH_ROOT . $script) ? '?v=' . filemtime(JPATH_ROOT . $script) : '';
        $this->getDocument()->addScript(\Joomla\CMS\Uri\Uri::root(true) . $script . $scriptVersion);

        $cache = new ViewCacheService();
        $cached = $cache->get();

        if ($cached !== null) {
            echo $cached;
            return;
        }

        $model = $this->getModel();
        $input = Factory::getApplication()->getInput();
        $isHome = trim($input->getString('id', '')) === ''
            && trim($input->getString('q', '')) === ''
            && trim($input->getString('theme', '')) === '';

        $this->items = $model->getItems();
        $this->pagination = method_exists($model, 'getPagination') ? $model->getPagination() : null;
        $this->themes = method_exists($model, 'getThemes') ? $model->getThemes() : [];
        $this->subthemes = method_exists($model, 'getCurrentThemeSubthemes') ? $model->getCurrentThemeSubthemes() : [];
        $this->item = method_exists($model, 'getItem') ? $model->getItem() : null;
        $this->homePanels = method_exists($model, 'getHomePanels') ? $model->getHomePanels() : [];
        $this->rubrique = method_exists($model, 'getRubrique') ? $model->getRubrique() : '';

        ob_start();
        parent::display($tpl);
        $html = (string) ob_get_clean();
        $cache->set($html);
        echo $html;
    }
}
