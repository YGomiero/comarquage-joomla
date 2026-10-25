<?php
/**
 * Comarquage — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\View\Dashboard;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    public array $status = [];

    public function display($tpl = null): void
    {
        $this->status = $this->getModel()->getStatus();

        ToolbarHelper::title(Text::_('COM_COMARQUAGE_DASHBOARD'), 'share-alt');
        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->standardButton('download', 'COM_COMARQUAGE_IMPORT_NOW', 'importNow')->task('importNow');
        $toolbar->standardButton('refresh', 'COM_COMARQUAGE_CLEAR_CACHE', 'clearCache')->task('clearCache');
        $toolbar->standardButton('loop', 'COM_COMARQUAGE_REBUILD_INDEX', 'rebuildIndex')->task('rebuildIndex');

        if ($this->getCurrentUser()->authorise('core.admin', 'com_comarquage')) {
            $toolbar->preferences('com_comarquage');
        }

        $this->getDocument()->getWebAssetManager()->registerAndUseScript('com_comarquage.admin', 'com_comarquage/admin.js', ['version' => 'auto'], ['defer' => true], ['core']);
        foreach (['COM_COMARQUAGE_IMPORT_RUNNING', 'COM_COMARQUAGE_IMPORT_COMPLETE', 'COM_COMARQUAGE_IMPORT_FAILED'] as $key) {
            Text::script($key);
        }
        parent::display($tpl);
    }
}
