<?php
/**
 * Comarquage — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Component\Comarquage\Administrator\Service\CommuneValidator;
use Joomla\Component\Comarquage\Administrator\Service\LayoutThemeService;
use Joomla\Component\Comarquage\Administrator\Service\SchedulerService;

defined('_JEXEC') or die;

class DashboardModel extends BaseDatabaseModel
{
    public function getStatus(): array
    {
        $params = ComponentHelper::getParams('com_comarquage');
        $db = $this->getDatabase();
        $validator = new CommuneValidator();
        $code = (string) $params->get('code_insee', '');

        $counts = [];
        foreach (['particuliers', 'associations', 'entreprises'] as $universe) {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__comarquage_items'))
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('universe') . ' = :universe')
                ->bind(':universe', $universe);
            $counts[$universe] = (int) $db->setQuery($query)->loadResult();
        }

        $last = $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__comarquage_metadata'))
                ->where($db->quoteName('scope') . ' = ' . $db->quote('import'))
                ->order($db->quoteName('created') . ' DESC'),
            0,
            1
        )->loadAssoc() ?: [];

        $feeds = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName([
                    'universe',
                    'last_checked',
                    'last_downloaded',
                    'last_imported',
                    'last_status',
                    'last_error',
                ]))
                ->from($db->quoteName('#__comarquage_feeds'))
                ->order($db->quoteName('universe') . ' ASC')
        )->loadAssocList() ?: [];

        return [
            'code' => $code,
            'name' => (string) $params->get('nom_commune', ''),
            'codeValid' => $code === '' || $validator->isValidFormat($code),
            'mode' => (string) $params->get('fetch_mode', 'remote'),
            'targetVersion' => (string) $params->get('target_version', '3.5'),
            'counts' => $counts,
            'lastImport' => $last,
            'feeds' => $feeds,
            'scheduler' => (new SchedulerService($db))->getStatus(),
            'activeTheme' => (string) $params->get('layout_theme', 'default'),
            'layoutThemes' => (new LayoutThemeService())->list(),
            'layoutThemeDetails' => array_map(fn ($id) => (new LayoutThemeService())->get($id), array_keys((new LayoutThemeService())->list())),
        ];
    }
}
