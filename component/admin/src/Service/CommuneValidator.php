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
        return (bool) preg_match('/^[A-Za-z0-9]{5}$/', $code);
    }

    public function exists(string $code): ?bool
    {
        return null;
    }
}