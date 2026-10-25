<?php
/**
 * Package prerequisites and non-destructive defaults for existing installations.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

class pkg_comarquageInstallerScript
{
    public function preflight($type, $parent): bool
    {
        if ($type === 'uninstall') { return true; }
        if (version_compare(PHP_VERSION, '8.3', '<') || version_compare(Version::MAJOR_VERSION . '.' . Version::MINOR_VERSION, '6.0', '<') || Version::MAJOR_VERSION >= 7) {
            Factory::getApplication()->enqueueMessage('Distro requires Joomla 6.x and PHP 8.3 or later.', 'error');
            return false;
        }
        foreach (['dom', 'mbstring', 'zip'] as $extension) {
            if (!extension_loaded($extension)) {
                Factory::getApplication()->enqueueMessage('Distro requires PHP extension: ' . $extension, 'error');
                return false;
            }
        }
        return true;
    }

    public function postflight($type, $parent): bool
    {
        if ($type === 'uninstall') { return true; }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $row = $db->setQuery("SELECT extension_id, params FROM #__extensions WHERE type='component' AND element='com_comarquage'")->loadObject();
        $params = new Registry($row->params);
        $config = simplexml_load_file(JPATH_ADMINISTRATOR . '/components/com_comarquage/config.xml');
        foreach ($config->xpath('//field[@default]') as $field) {
            $name = (string) $field['name'];
            if (!$params->exists($name)) { $params->set($name, (string) $field['default']); }
        }
        $row->params = $params->toString();
        $db->updateObject('#__extensions', $row, 'extension_id');
        if ($type === 'install') {
            $db->setQuery("UPDATE #__extensions SET enabled=1 WHERE type='plugin' AND ((folder='task' AND element='comarquageimport') OR (folder='finder' AND element='comarquage'))")->execute();
        }
        return true;
    }
}
