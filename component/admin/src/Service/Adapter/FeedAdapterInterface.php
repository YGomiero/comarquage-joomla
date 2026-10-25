<?php
/**
 * Distro — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\Service\Adapter;

defined('_JEXEC') or die;

interface FeedAdapterInterface
{
    public function supports(string $version): bool;

    public function adapt(\DOMDocument $document, string $universe, callable $log): array;
}