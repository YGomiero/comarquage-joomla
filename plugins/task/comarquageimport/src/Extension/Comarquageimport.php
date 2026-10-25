<?php
/**
 * Comarquage — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Plugin\Task\Comarquageimport\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Comarquage\Administrator\Service\CacheService;
use Joomla\Component\Comarquage\Administrator\Service\ImportService;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\SubscriberInterface;

defined('_JEXEC') or die;

final class Comarquageimport extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    protected const TASKS_MAP = [
        'comarquageimport.import' => [
            'langConstPrefix' => 'PLG_TASK_COMARQUAGEIMPORT_TASK_IMPORT',
            'form' => 'import',
            'method' => 'runImport',
        ],
        'comarquageimport.purge_cache' => [
            'langConstPrefix' => 'PLG_TASK_COMARQUAGEIMPORT_TASK_PURGE',
            'form' => 'import',
            'method' => 'purgeCache',
        ],
    ];

    protected $autoloadLanguage = true;

    public function __construct(array $config, private DatabaseInterface $db)
    {
        parent::__construct($config);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList' => 'advertiseRoutines',
            'onExecuteTask' => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    protected function runImport(ExecuteTaskEvent $event): int
    {
        try {
            $params = $event->getArgument('params');
            $universe = (string) ($params->universe ?? '');
            $summary = (new ImportService($this->db))->import($universe !== '' ? $universe : null, [
                'max_runtime' => (int) ($params->max_runtime ?? 240),
                'max_files' => (int) ($params->max_files ?? 250),
            ]);
            $this->logTask(sprintf(
                'Import des demarches: %d fiches, %d erreurs%s.',
                $summary['items'],
                $summary['errors'],
                !empty($summary['interrupted']) ? ', execution interrompue proprement par limite de securite' : ''
            ));

            return $summary['errors'] > 0 ? TaskStatus::KNOCKOUT : TaskStatus::OK;
        } catch (\Throwable $e) {
            $this->logTask($e->getMessage(), 'error');

            return TaskStatus::KNOCKOUT;
        }
    }

    protected function purgeCache(ExecuteTaskEvent $event): int
    {
        (new CacheService())->clear();
        $this->logTask('Cache des demarches purge.');

        return TaskStatus::OK;
    }
}
