<?php
/**
 * Distro — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\View\Logs;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    public array $items = [];
    public $pagination;

    public function display($tpl = null): void
    {
        $model = $this->getModel();
        $this->items = $model->getItems();
        $this->pagination = $model->getPagination();

        ToolbarHelper::title(Text::_('COM_COMARQUAGE_LOGS'), 'list');
        $toolbar = Toolbar::getInstance('toolbar');
        $toolbar->standardButton('trash', 'COM_COMARQUAGE_CLEAR_LOGS', 'clearLogs')->task('clearLogs');

        parent::display($tpl);
    }
}
