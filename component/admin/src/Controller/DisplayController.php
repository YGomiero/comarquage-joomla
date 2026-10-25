<?php
/**
 * Comarquage — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Registry\Registry;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Comarquage\Administrator\Service\CacheService;
use Joomla\Component\Comarquage\Administrator\Service\ImportService;
use Joomla\Component\Comarquage\Administrator\Service\LayoutThemeService;
use Joomla\Component\Comarquage\Administrator\Service\SchedulerService;

defined('_JEXEC') or die;

class DisplayController extends BaseController
{
    protected $default_view = 'dashboard';

    public function display($cachable = false, $urlparams = [])
    {
        $this->requireManage();
        return parent::display($cachable, $urlparams);
    }

    public function clearCache(): void
    {
        $this->checkToken();
        $this->requireManage();
        (new CacheService())->clear();
        $this->setMessage(Text::_('COM_COMARQUAGE_CACHE_CLEARED'), 'message');
        $this->setRedirect('index.php?option=com_comarquage');
    }

    public function refreshLocalInfo(): void
    {
        $this->checkToken();
        $this->requireManage();
        try {
            $count = (new \Joomla\Component\Comarquage\Administrator\Service\LocalContextService(Factory::getContainer()->get(DatabaseInterface::class)))->refresh(true);
            $this->setMessage(Text::sprintf('COM_COMARQUAGE_LOCAL_REFRESHED', $count), 'message');
        } catch (\Throwable $e) { $this->setMessage($e->getMessage(), 'warning'); }
        $this->setRedirect('index.php?option=com_comarquage');
    }

    /** Run a bounded batch; the next request resumes at the saved cursor. */
    public function importNow(): void
    {
        $this->checkToken();
        $this->requireManage();
        $summary = (new ImportService(Factory::getContainer()->get(DatabaseInterface::class)))->import(null, ['max_runtime' => 15, 'max_files' => 250]);
        $this->setMessage(Text::sprintf('COM_COMARQUAGE_IMPORT_DONE', $summary['items'], $summary['errors']), $summary['errors'] ? 'warning' : 'message');
        $this->setRedirect('index.php?option=com_comarquage');
    }

    /** AJAX import endpoint: session token and component permission are both mandatory. */
    public function importBatch(): void
    {
        $this->checkToken();
        $this->requireManage();
        try {
            $summary = (new ImportService(Factory::getContainer()->get(DatabaseInterface::class)))->import(null, ['max_runtime' => 15, 'max_files' => 250]);
            echo new \Joomla\CMS\Response\JsonResponse($summary);
        } catch (\Throwable $e) {
            echo new \Joomla\CMS\Response\JsonResponse(null, $e->getMessage(), true);
        }
        $this->app->close();
    }

    private function requireManage(): void
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_comarquage')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    public function rebuildIndex(): void
    {
        $this->checkToken();
        $this->requireManage();

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_comarquage')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $count = $this->rebuildLocalIndex(Factory::getContainer()->get(DatabaseInterface::class));
        $this->setMessage(Text::sprintf('COM_COMARQUAGE_INDEX_REBUILT', $count), 'message');
        $this->setRedirect('index.php?option=com_comarquage');
    }

    public function useRecommendedSources(): void
    {
        $this->checkToken();
        $this->requireManage();

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_comarquage')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $params = new Registry(ComponentHelper::getParams('com_comarquage')->toArray());
        $params->set('fetch_mode', 'remote');
        $params->set('feed_particuliers', 'https://www.data.gouv.fr/fr/datasets/service-public-fr-guide-vos-droits-et-demarches-particuliers/');
        $params->set('feed_associations', 'https://lecomarquage.service-public.gouv.fr/vdd/3.5/part/zip/vosdroits-latest.zip');
        $params->set('feed_entreprises', 'https://www.data.gouv.fr/fr/datasets/service-public-fr-guide-vos-droits-et-demarches-professionnels-entreprises/');

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_comarquage'))
            ->bind(':params', $params->toString());

        $db->setQuery($query)->execute();

        $this->setMessage(Text::_('COM_COMARQUAGE_RECOMMENDED_SOURCES_APPLIED'), 'message');
        $this->setRedirect('index.php?option=com_comarquage');
    }

    public function repairScheduledTasks(): void
    {
        $this->checkToken();
        $this->requireManage();

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_comarquage')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        (new SchedulerService($db))->repairDefaults((int) $this->app->getIdentity()->id);

        $this->setMessage(Text::_('COM_COMARQUAGE_SCHEDULE_REPAIRED'), 'message');
        $this->setRedirect('index.php?option=com_comarquage');
    }

    public function purgeImportedContent(): void
    {
        $this->checkToken();
        $this->requireManage();

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_comarquage')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        foreach ([
            '#__comarquage_search_index',
            '#__comarquage_item_category_map',
            '#__comarquage_items',
            '#__comarquage_categories',
        ] as $table) {
            $db->setQuery('TRUNCATE TABLE ' . $db->quoteName($table))->execute();
        }

        $empty = '';
        $status = 'empty';
        $message = 'Contenus vides manuellement depuis le tableau de bord.';
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__comarquage_feeds'))
            ->set($db->quoteName('last_status') . ' = :status')
            ->set($db->quoteName('last_error') . ' = :message')
            ->set($db->quoteName('source_etag') . ' = :empty1')
            ->set($db->quoteName('source_last_modified') . ' = :empty2')
            ->set($db->quoteName('source_hash') . ' = :empty3')
            ->set($db->quoteName('last_checked') . ' = NULL')
            ->set($db->quoteName('last_downloaded') . ' = NULL')
            ->set($db->quoteName('last_imported') . ' = NULL')
            ->set($db->quoteName('params') . ' = NULL')
            ->bind(':status', $status)
            ->bind(':message', $message)
            ->bind(':empty1', $empty)
            ->bind(':empty2', $empty)
            ->bind(':empty3', $empty);

        $db->setQuery($query)->execute();
        (new CacheService())->clear();

        $this->setMessage(Text::_('COM_COMARQUAGE_CONTENT_PURGED'), 'message');
        $this->setRedirect('index.php?option=com_comarquage');
    }

    public function clearLogs(): void
    {
        $this->checkToken();
        $this->requireManage();

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_comarquage')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery('TRUNCATE TABLE ' . $db->quoteName('#__comarquage_metadata'))->execute();

        $this->setMessage(Text::_('COM_COMARQUAGE_LOGS_CLEARED'), 'message');
        $this->setRedirect('index.php?option=com_comarquage&view=logs');
    }

    public function importLayoutTheme(): void
    {
        $this->checkToken();
        $this->requireManage();
        if (!$this->app->getIdentity()->authorise('core.admin', 'com_comarquage')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $file = $this->input->files->get('theme_file', null, 'raw');
        if (!$file || empty($file['tmp_name'])) {
            throw new \RuntimeException(Text::_('COM_COMARQUAGE_THEME_IMPORT_MISSING'));
        }

        $id = (new LayoutThemeService())->import($file['tmp_name']);
        $this->setMessage(Text::sprintf('COM_COMARQUAGE_THEME_IMPORTED', $id), 'message');
        $this->setRedirect('index.php?option=com_comarquage');
    }

    public function exportLayoutTheme(): void
    {
        $this->checkToken('get');
        $this->requireManage();
        $id = $this->input->getCmd('theme_id', 'default');
        $file = (new LayoutThemeService())->export($id);

        $this->app->setHeader('Content-Type', 'application/json', true);
        $this->app->setHeader('Content-Disposition', 'attachment; filename="' . basename($file) . '"', true);
        echo file_get_contents($file);
        $this->app->close();
    }

    public function deleteLayoutTheme(): void
    {
        $this->checkToken();
        $this->requireManage();
        if (!$this->app->getIdentity()->authorise('core.admin', 'com_comarquage')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $id = $this->input->getCmd('theme_id', '');
        (new LayoutThemeService())->delete($id);
        $this->setMessage(Text::_('COM_COMARQUAGE_THEME_DELETED'), 'message');
        $this->setRedirect('index.php?option=com_comarquage');
    }
    public function saveLayoutTheme(): void
    {
        $this->checkToken();
        $this->requireManage();
        if (!$this->app->getIdentity()->authorise('core.admin', 'com_comarquage')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $id = $this->input->getCmd('theme_id', '');
        $cssValues = $this->input->get('theme_css', [], 'raw');
        $paramsValues = $this->input->get('theme_params', [], 'raw');
        $iconsValues = $this->input->get('theme_icons', [], 'raw');
        $css = is_array($cssValues) ? (string) ($cssValues[$id] ?? '') : '';
        $params = is_array($paramsValues) ? (string) ($paramsValues[$id] ?? '{}') : '{}';
        $icons = is_array($iconsValues) ? (string) ($iconsValues[$id] ?? '{}') : '{}';
        (new LayoutThemeService())->saveEditable($id, $css, $params, $icons);
        $this->setMessage(Text::_('COM_COMARQUAGE_THEME_SAVED'), 'message');
        $this->setRedirect('index.php?option=com_comarquage');
    }

    private function rebuildLocalIndex(DatabaseInterface $db): int
    {
        $db->setQuery('TRUNCATE TABLE ' . $db->quoteName('#__comarquage_search_index'))->execute();

        $query = 'INSERT IGNORE INTO ' . $db->quoteName('#__comarquage_search_index')
            . ' (' . implode(',', [
                $db->quoteName('item_id'),
                $db->quoteName('universe'),
                $db->quoteName('title'),
                $db->quoteName('content'),
                $db->quoteName('updated'),
            ]) . ') '
            . 'SELECT DISTINCT '
            . implode(',', [
                $db->quoteName('id'),
                $db->quoteName('universe'),
                $db->quoteName('title'),
                'LEFT(' . $db->quoteName('body') . ', 12000)',
                'NOW()',
            ])
            . ' FROM ' . $db->quoteName('#__comarquage_items')
            . ' WHERE ' . $db->quoteName('state') . ' = 1';

        $db->setQuery($query)->execute();

        return (int) $db->getAffectedRows();
    }
}
