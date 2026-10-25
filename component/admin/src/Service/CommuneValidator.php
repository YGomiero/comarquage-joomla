<?php
/**
 * Comarquage — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\Service;

defined('_JEXEC') or die;

class CommuneValidator
{
    public function isValidFormat(string $code): bool
    {
        return (bool) preg_match('/^(?:[0-9]{5}|2[AB][0-9]{3})$/iD', $code);
    }

    public function exists(string $code): ?bool
    {
        return null;
    }
}
