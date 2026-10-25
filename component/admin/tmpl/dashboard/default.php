<?php
/**
 * Comarquage — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Joomla\Component\Comarquage\Administrator\View\Dashboard\HtmlView $this */
?>
<form action="<?php echo Route::_('index.php?option=com_comarquage'); ?>" method="post" enctype="multipart/form-data" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-7">
            <div class="card">
                <div class="card-body">
                    <h2><?php echo Text::_('COM_COMARQUAGE_STATUS'); ?></h2>
                    <dl class="row">
                        <dt class="col-sm-4"><?php echo Text::_('COM_COMARQUAGE_FIELD_CODE_INSEE_LABEL'); ?></dt>
                        <dd class="col-sm-8"><?php echo $this->escape($this->status['code'] ?: Text::_('JNONE')); ?></dd>
                        <dt class="col-sm-4"><?php echo Text::_('COM_COMARQUAGE_FIELD_TARGET_VERSION_LABEL'); ?></dt>
                        <dd class="col-sm-8"><?php echo $this->escape($this->status['targetVersion']); ?></dd>
                        <dt class="col-sm-4"><?php echo Text::_('COM_COMARQUAGE_LAST_IMPORT'); ?></dt>
                        <dd class="col-sm-8"><?php echo $this->escape($this->status['lastImport']['created'] ?? Text::_('JNONE')); ?></dd>
                    </dl>
                    <?php if (!$this->status['codeValid']) : ?>
                        <joomla-alert type="warning"><?php echo Text::_('COM_COMARQUAGE_CODE_INSEE_INVALID'); ?></joomla-alert>
                    <?php endif; ?>
                    <div class="mt-3">
                        <button class="btn btn-outline-primary" type="submit" onclick="this.form.task.value='useRecommendedSources'">
                            <?php echo Text::_('COM_COMARQUAGE_USE_RECOMMENDED_SOURCES'); ?>
                        </button>
                        <p class="form-text"><?php echo Text::_('COM_COMARQUAGE_RECOMMENDED_SOURCES_DESC'); ?></p>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-outline-danger" type="submit" onclick="if (!confirm('<?php echo $this->escape(Text::_('COM_COMARQUAGE_PURGE_CONTENT_CONFIRM')); ?>')) return false; this.form.task.value='purgeImportedContent'">
                            <span class="icon-trash" aria-hidden="true"></span>
                            <?php echo Text::_('COM_COMARQUAGE_PURGE_CONTENT'); ?>
                        </button>
                        <p class="form-text"><?php echo Text::_('COM_COMARQUAGE_PURGE_CONTENT_DESC'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-body">
                    <h2><?php echo Text::_('COM_COMARQUAGE_IMPORTED_ITEMS'); ?></h2>
                    <ul class="list-group">
                        <?php foreach ($this->status['counts'] as $universe => $count) : ?>
                            <li class="list-group-item d-flex justify-content-between">
                                <span><?php echo Text::_('COM_COMARQUAGE_' . strtoupper($universe)); ?></span>
                                <span class="badge bg-primary"><?php echo (int) $count; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-body">
                    <h2><?php echo Text::_('COM_COMARQUAGE_SCHEDULE_STATUS'); ?></h2>
                    <dl class="row">
                        <dt class="col-sm-4"><?php echo Text::_('COM_COMARQUAGE_TASK_PLUGIN'); ?></dt>
                        <dd class="col-sm-8">
                            <?php echo $this->status['scheduler']['pluginEnabled'] ? Text::_('JENABLED') : Text::_('JDISABLED'); ?>
                        </dd>
                        <dt class="col-sm-4"><?php echo Text::_('COM_COMARQUAGE_CLI_COMMAND'); ?></dt>
                        <dd class="col-sm-8"><code><?php echo $this->escape($this->status['scheduler']['cliCommand']); ?></code></dd>
                    </dl>
                    <button class="btn btn-outline-primary" type="submit" onclick="this.form.task.value='repairScheduledTasks'">
                        <span class="icon-calendar" aria-hidden="true"></span>
                        <?php echo Text::_('COM_COMARQUAGE_REPAIR_SCHEDULED_TASKS'); ?>
                    </button>
                    <p class="form-text"><?php echo Text::_('COM_COMARQUAGE_SCHEDULE_HELP'); ?></p>
                    <div class="table-responsive mt-3">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?php echo Text::_('JGLOBAL_TITLE'); ?></th>
                                    <th><?php echo Text::_('JSTATUS'); ?></th>
                                    <th><?php echo Text::_('COM_COMARQUAGE_LAST_RUN'); ?></th>
                                    <th><?php echo Text::_('COM_COMARQUAGE_NEXT_RUN'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->status['scheduler']['tasks'] as $task) : ?>
                                    <tr>
                                        <td><?php echo $this->escape($task['title']); ?></td>
                                        <td><?php echo (int) $task['state'] === 1 ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED'); ?></td>
                                        <td><?php echo $this->escape($task['last_execution'] ?: Text::_('JNONE')); ?></td>
                                        <td><?php echo $this->escape($task['next_execution'] ?: Text::_('JNONE')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($this->status['scheduler']['tasks'])) : ?>
                                    <tr><td colspan="4"><?php echo Text::_('COM_COMARQUAGE_NO_SCHEDULED_TASKS'); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-body">
                    <h2><?php echo Text::_('COM_COMARQUAGE_FEED_STATUS'); ?></h2>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?php echo Text::_('COM_COMARQUAGE_UNIVERSE'); ?></th>
                                    <th><?php echo Text::_('JSTATUS'); ?></th>
                                    <th><?php echo Text::_('COM_COMARQUAGE_LAST_IMPORT'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->status['feeds'] as $feed) : ?>
                                    <tr>
                                        <td><?php echo Text::_('COM_COMARQUAGE_' . strtoupper($feed['universe'])); ?></td>
                                        <td title="<?php echo $this->escape($feed['last_error'] ?? ''); ?>"><?php echo $this->escape($feed['last_status'] ?: Text::_('JNONE')); ?></td>
                                        <td><?php echo $this->escape($feed['last_imported'] ?: Text::_('JNONE')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="form-text"><?php echo Text::_('COM_COMARQUAGE_FEED_STATUS_HELP'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h2><?php echo Text::_('COM_COMARQUAGE_LAYOUT_THEMES'); ?></h2>
                    <p><?php echo Text::_('COM_COMARQUAGE_LAYOUT_THEMES_DESC'); ?></p>
                    <p><strong><?php echo Text::_('COM_COMARQUAGE_ACTIVE_THEME'); ?> :</strong> <?php echo $this->escape($this->status['activeTheme']); ?></p>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?php echo Text::_('JGLOBAL_TITLE'); ?></th>
                                    <th><?php echo Text::_('JGLOBAL_DESCRIPTION'); ?></th>
                                    <th><?php echo Text::_('JACTION'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->status['layoutThemes'] as $theme) : ?>
                                    <tr>
                                        <td><?php echo $this->escape($theme['title']); ?> <code><?php echo $this->escape($theme['id']); ?></code></td>
                                        <td><?php echo $this->escape($theme['description']); ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-secondary" href="<?php echo Route::_('index.php?option=com_comarquage&task=exportLayoutTheme&theme_id=' . urlencode($theme['id']) . '&' . Joomla\CMS\Session\Session::getFormToken() . '=1'); ?>">
                                                <?php echo Text::_('COM_COMARQUAGE_EXPORT'); ?>
                                            </a>
                                            <button class="btn btn-sm btn-info" type="button" data-bs-toggle="collapse" data-bs-target="#theme-edit-<?php echo $this->escape($theme['id']); ?>">
                                                <?php echo Text::_('COM_COMARQUAGE_EDIT'); ?>
                                            </button>
                                            <?php if (!$theme['builtin']) : ?>
                                                <button class="btn btn-sm btn-danger" type="submit" name="theme_id" value="<?php echo $this->escape($theme['id']); ?>" onclick="this.form.task.value='deleteLayoutTheme'">
                                                    <?php echo Text::_('COM_COMARQUAGE_DELETE'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php $details = $this->status['layoutThemeDetails'][$theme['id']] ?? []; ?>
                                    <tr class="collapse" id="theme-edit-<?php echo $this->escape($theme['id']); ?>">
                                        <td colspan="3">
                                            <h3><?php echo Text::_('COM_COMARQUAGE_THEME_PARAMETERS'); ?></h3>
                                            <label class="form-label"><?php echo Text::_('COM_COMARQUAGE_THEME_CSS'); ?></label>
                                            <textarea class="form-control" name="theme_css[<?php echo $this->escape($theme['id']); ?>]" rows="7"><?php echo $this->escape($details['css'] ?? ''); ?></textarea>
                                            <label class="form-label mt-2"><?php echo Text::_('COM_COMARQUAGE_THEME_PARAMS_JSON'); ?></label>
                                            <textarea class="form-control" name="theme_params[<?php echo $this->escape($theme['id']); ?>]" rows="5"><?php echo $this->escape(json_encode($details['params'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></textarea>
                                            <label class="form-label mt-2"><?php echo Text::_('COM_COMARQUAGE_THEME_ICONS_JSON'); ?></label>
                                            <textarea class="form-control" name="theme_icons[<?php echo $this->escape($theme['id']); ?>]" rows="5"><?php echo $this->escape(json_encode($details['icons'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></textarea>
                                            <button class="btn btn-success mt-2" type="submit" name="theme_id" value="<?php echo $this->escape($theme['id']); ?>" onclick="this.form.task.value='saveLayoutTheme'">
                                                <?php echo Text::_('COM_COMARQUAGE_SAVE_AS_CUSTOM_THEME'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <label for="theme_file" class="form-label"><?php echo Text::_('COM_COMARQUAGE_IMPORT_THEME'); ?></label>
                        <input type="file" id="theme_file" name="theme_file" accept="application/json,.json">
                        <button class="btn btn-primary" type="submit" onclick="this.form.task.value='importLayoutTheme'">
                            <?php echo Text::_('COM_COMARQUAGE_IMPORT'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
