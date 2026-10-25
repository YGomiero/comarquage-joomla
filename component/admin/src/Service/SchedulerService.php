<?php
/**
 * Distro — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\Service;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

defined('_JEXEC') or die;

class SchedulerService
{
    public function __construct(private DatabaseInterface $db)
    {
    }

    public function getStatus(): array
    {
        $plugin = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName(['extension_id', 'enabled']))
                ->from($this->db->quoteName('#__extensions'))
                ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
                ->where($this->db->quoteName('folder') . ' = ' . $this->db->quote('task'))
                ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('comarquageimport'))
        )->loadAssoc() ?: [];

        $tasks = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName([
                    'id',
                    'title',
                    'type',
                    'state',
                    'last_exit_code',
                    'last_execution',
                    'next_execution',
                    'times_executed',
                    'times_failed',
                    'locked',
                ]))
                ->from($this->db->quoteName('#__scheduler_tasks'))
                ->where($this->db->quoteName('type') . ' LIKE ' . $this->db->quote('comarquageimport.%'))
                ->order($this->db->quoteName('title') . ' ASC')
        )->loadAssocList() ?: [];

        return [
            'pluginInstalled' => !empty($plugin),
            'pluginEnabled' => !empty($plugin) && (int) $plugin['enabled'] === 1,
            'tasks' => $tasks,
            'cliCommand' => 'php ' . JPATH_ROOT . '/cli/joomla.php scheduler:run',
        ];
    }

    public function repairDefaults(int $userId = 0): void
    {
        $this->enablePlugin();

        $now = Factory::getDate()->toSql();
        $definitions = [
            ['particuliers', 'Demarches administratives - Import particuliers', '02:10'],
            ['associations', 'Demarches administratives - Import associations', '03:10'],
            ['entreprises', 'Demarches administratives - Import entreprises', '04:10'],
        ];

        foreach ($definitions as [$universe, $title, $time]) {
            $this->upsertImportTask($universe, $title, $time, $now, $userId);
        }

        $this->upsertPurgeTask($now, $userId);
    }

    private function enablePlugin(): void
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__extensions'))
            ->set($this->db->quoteName('enabled') . ' = 1')
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
            ->where($this->db->quoteName('folder') . ' = ' . $this->db->quote('task'))
            ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('comarquageimport'));

        $this->db->setQuery($query)->execute();
    }

    private function upsertImportTask(string $universe, string $title, string $time, string $now, int $userId): void
    {
        $existingId = $this->findTaskId('comarquageimport.import', $universe);
        $executionRules = json_encode([
            'rule-type' => 'interval-days',
            'interval-days' => '1',
            'exec-day' => '01',
            'exec-time' => $time,
        ], JSON_UNESCAPED_SLASHES);
        $cronRules = json_encode(['type' => 'interval', 'exp' => 'P1D'], JSON_UNESCAPED_SLASHES);
        $params = json_encode([
            'individual_log' => true,
            'log_file' => '',
            'notifications' => [
                'success_mail' => '0',
                'failure_mail' => '1',
                'fatal_failure_mail' => '1',
                'orphan_mail' => '1',
            ],
            'universe' => $universe,
            'max_runtime' => 900,
            'max_files' => 0,
        ], JSON_UNESCAPED_SLASHES);
        $next = gmdate('Y-m-d H:i:s', strtotime('tomorrow ' . $time . ' Europe/Paris'));

        if ($existingId) {
            $query = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__scheduler_tasks'))
                ->set($this->db->quoteName('title') . ' = :title')
                ->set($this->db->quoteName('state') . ' = 1')
                ->set($this->db->quoteName('execution_rules') . ' = :executionRules')
                ->set($this->db->quoteName('cron_rules') . ' = :cronRules')
                ->set($this->db->quoteName('params') . ' = :params')
                ->set($this->db->quoteName('next_execution') . ' = COALESCE(' . $this->db->quoteName('next_execution') . ', :nextExecution)')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $existingId)
                ->bind(':title', $title)
                ->bind(':executionRules', $executionRules)
                ->bind(':cronRules', $cronRules)
                ->bind(':params', $params)
                ->bind(':nextExecution', $next);

            $this->db->setQuery($query)->execute();
            return;
        }

        $this->insertTask($title, 'comarquageimport.import', $executionRules, $cronRules, $params, $next, $now, $userId, 'Import Open Data Service-Public limite a 900 secondes par execution.');
    }

    private function upsertPurgeTask(string $now, int $userId): void
    {
        if ($this->findTaskId('comarquageimport.purge_cache', '')) {
            return;
        }

        $executionRules = json_encode([
            'rule-type' => 'interval-days',
            'interval-days' => '1',
            'exec-day' => '01',
            'exec-time' => '05:10',
        ], JSON_UNESCAPED_SLASHES);
        $cronRules = json_encode(['type' => 'interval', 'exp' => 'P1D'], JSON_UNESCAPED_SLASHES);
        $params = json_encode([
            'individual_log' => true,
            'log_file' => '',
            'notifications' => [
                'success_mail' => '0',
                'failure_mail' => '0',
                'fatal_failure_mail' => '1',
                'orphan_mail' => '1',
            ],
        ], JSON_UNESCAPED_SLASHES);
        $next = gmdate('Y-m-d H:i:s', strtotime('tomorrow 05:10 Europe/Paris'));

        $this->insertTask($title = 'Demarches administratives - Purge du cache', 'comarquageimport.purge_cache', $executionRules, $cronRules, $params, $next, $now, $userId, 'Nettoyage quotidien du cache du composant.');
    }

    private function findTaskId(string $type, string $universe): int
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__scheduler_tasks'))
            ->where($this->db->quoteName('type') . ' = :type')
            ->bind(':type', $type);

        if ($universe !== '') {
            $universeKeyPattern = '%"universe"%';
            $universeValuePattern = '%' . $universe . '%';
            $query->where($this->db->quoteName('params') . ' LIKE :universeKey')
                ->where($this->db->quoteName('params') . ' LIKE :universeValue')
                ->bind(':universeKey', $universeKeyPattern)
                ->bind(':universeValue', $universeValuePattern);
        }

        return (int) $this->db->setQuery($query, 0, 1)->loadResult();
    }

    private function insertTask(string $title, string $type, string $executionRules, string $cronRules, string $params, string $next, string $now, int $userId, string $note): void
    {
        $columns = [
            'title',
            'type',
            'execution_rules',
            'cron_rules',
            'state',
            'next_execution',
            'params',
            'note',
            'created',
            'created_by',
        ];

        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__scheduler_tasks'))
            ->columns($this->db->quoteName($columns))
            ->values(':title, :type, :executionRules, :cronRules, 1, :nextExecution, :params, :note, :created, :createdBy')
            ->bind(':title', $title)
            ->bind(':type', $type)
            ->bind(':executionRules', $executionRules)
            ->bind(':cronRules', $cronRules)
            ->bind(':nextExecution', $next)
            ->bind(':params', $params)
            ->bind(':note', $note)
            ->bind(':created', $now)
            ->bind(':createdBy', $userId);

        $this->db->setQuery($query)->execute();
    }
}
