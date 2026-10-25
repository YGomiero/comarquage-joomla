<?php
/**
 * Comarquage — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Site\Service;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;

defined('_JEXEC') or die;

class ViewCacheService
{
    public function get(): ?string
    {
        $file = $this->getFile();

        if (!is_file($file)) {
            return null;
        }

        $params = ComponentHelper::getParams('com_comarquage');
        $ttl = max(1, (int) $params->get('cache_hours', 24)) * 3600;

        if ((time() - (int) filemtime($file)) > $ttl) {
            return null;
        }

        $html = file_get_contents($file);

        return is_string($html) ? $html : null;
    }

    public function set(string $html): void
    {
        if ($html === '') {
            return;
        }

        $directory = $this->getDirectory();

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return;
        }

        @file_put_contents($this->getFile(), $html, LOCK_EX);
    }

    private function getFile(): string
    {
        return $this->getDirectory() . '/view_' . $this->getKey() . '.html';
    }

    private function getKey(): string
    {
        $input = Factory::getApplication()->getInput();
        $params = ComponentHelper::getParams('com_comarquage');
        $keys = ['option', 'view', 'id', 'theme', 'subtheme', 'q', 'start', 'limitstart', 'Itemid', 'return'];
        $parts = [
            'view_cache_schema=comarquage-1.0.0-v2',
            'language=' . Factory::getApplication()->getLanguage()->getTag(),
            'params=' . hash('sha256', $params->toString()),
            'layout_theme=' . (string) $params->get('layout_theme', 'default'),
            'results_per_page=' . (string) $params->get('results_per_page', 20),
        ];

        foreach ($keys as $key) {
            $parts[] = $key . '=' . trim($input->getString($key, ''));
        }

        return sha1(implode('|', $parts));
    }

    private function getDirectory(): string
    {
        return JPATH_ROOT . '/tmp/com_comarquage/cache';
    }
}
