<?php
/**
 * Comarquage — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\Service;

use Joomla\CMS\Date\Date;
use Joomla\Database\DatabaseInterface;

defined('_JEXEC') or die;

class ImportLogger
{
    public function __construct(private DatabaseInterface $db)
    {
    }

    public function log(string $scope, string $name, mixed $value): void
    {
        $encodedValue = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $created = (new Date())->toSql();

        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__comarquage_metadata'))
            ->columns([
                $this->db->quoteName('scope'),
                $this->db->quoteName('name'),
                $this->db->quoteName('value'),
                $this->db->quoteName('created'),
            ])
            ->values(':scope, :name, :value, :created')
            ->bind(':scope', $scope)
            ->bind(':name', $name)
            ->bind(':value', $encodedValue)
            ->bind(':created', $created);

        $this->db->setQuery($query)->execute();
    }
}