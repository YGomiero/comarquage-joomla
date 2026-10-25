<?php
/**
 * Comarquage — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\Service;

defined('_JEXEC') or die;

class ContentRendererService
{
    public function excerpt(string $content, int $length = 260): string
    {
        $content = trim(strip_tags($content));

        if (mb_strlen($content) <= $length) {
            return $content;
        }

        return mb_substr($content, 0, $length - 1) . '...';
    }
}