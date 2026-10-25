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

/** @var \Joomla\Component\Comarquage\Administrator\View\Logs\HtmlView $this */
?>
<form action="<?php echo Route::_('index.php?option=com_comarquage&view=logs'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="alert alert-info">
        <?php echo Text::_('COM_COMARQUAGE_LOGS_HELP'); ?>
    </div>
    <div class="table-responsive">
        <table class="table">
            <caption class="visually-hidden">Comarquage logs</caption>
            <thead><tr><th>Date</th><th>Scope</th><th>Name</th><th>Value</th></tr></thead>
            <tbody>
                <?php foreach ($this->items as $item) : ?>
                    <tr>
                        <td><?php echo $this->escape($item->created); ?></td>
                        <td><?php echo $this->escape($item->scope); ?></td>
                        <td><?php echo $this->escape($item->name); ?></td>
                        <td><pre class="mb-0 text-wrap"><?php echo $this->escape($item->value); ?></pre></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($this->items)) : ?>
                    <tr><td colspan="4"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php echo $this->pagination->getListFooter(); ?>
    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
