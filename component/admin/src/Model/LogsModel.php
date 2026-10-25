<?php
/**
 * Distro — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\Model;

use Joomla\CMS\MVC\Model\ListModel;

defined('_JEXEC') or die;

class LogsModel extends ListModel
{
    public function __construct($config = [])
    {
        $config['filter_fields'] = $config['filter_fields'] ?? [
            'created',
            'scope',
            'name',
        ];

        parent::__construct($config);
    }

    protected function populateState($ordering = 'created', $direction = 'DESC'): void
    {
        parent::populateState($ordering, $direction);
    }

    protected function getListQuery()
    {
        $db = $this->getDatabase();

        return $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__comarquage_metadata'))
            ->order($db->quoteName('created') . ' DESC');
    }
}
