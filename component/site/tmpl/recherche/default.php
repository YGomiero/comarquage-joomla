<?php
/**
 * Comarquage — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Component\Comarquage\Site\Service\ContentRendererService;
use Joomla\Component\Comarquage\Site\Service\RouteHelper;

$renderer = new ContentRendererService();
$q = Factory::getApplication()->getInput()->getString('q', '');
$resultsTotal = $this->pagination ? (int) $this->pagination->total : count($this->items);
$universeLabels = [
    'particuliers' => Text::_('COM_COMARQUAGE_PARTICULIERS'),
    'associations' => Text::_('COM_COMARQUAGE_ASSOCIATIONS'),
    'entreprises' => Text::_('COM_COMARQUAGE_ENTREPRISES'),
];
?>
<?php $layoutTheme = Joomla\CMS\Component\ComponentHelper::getParams('com_comarquage')->get('layout_theme', 'default'); ?>
<div class="comarquage comarquage-theme-<?php echo $this->escape($layoutTheme); ?> comarquage-recherche">
    <section class="comarquage-hero comarquage-hero-compact">
        <div class="comarquage-hero-content">
            <span class="comarquage-kicker">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <span><?php echo Text::_('COM_COMARQUAGE_SEARCH'); ?></span>
            </span>
            <h1><?php echo Text::_('COM_COMARQUAGE_SEARCH'); ?></h1>
        </div>
        <form class="comarquage-search-panel" action="<?php echo RouteHelper::getViewRoute('recherche'); ?>" method="get">
            <label for="comarquage-q">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <?php echo Text::_('COM_COMARQUAGE_SEARCH_LABEL'); ?>
            </label>
            <div class="comarquage-search-row">
                <input id="comarquage-q" name="q" type="search" value="<?php echo $this->escape($q); ?>" placeholder="Passeport, association, embauche...">
                <button type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            </div>
        </form>
    </section>

    <section class="comarquage-section comarquage-results" aria-label="<?php echo Text::_('COM_COMARQUAGE_RESULTS'); ?>">
        <div class="comarquage-section-head">
            <span><?php echo $q !== '' ? $resultsTotal . ' ' . Text::_('COM_COMARQUAGE_RESULTS') : Text::_('COM_COMARQUAGE_OPEN_DATA_LABEL'); ?></span>
            <h2><?php echo $q !== '' ? Text::_('COM_COMARQUAGE_SEARCH_RESULTS') : Text::_('COM_COMARQUAGE_SEARCH_LABEL'); ?></h2>
        </div>

        <div class="comarquage-list">
            <?php foreach ($this->items as $item) : ?>
                <?php $content = $renderer->excerptForItem($item); ?>
                <article class="comarquage-card">
                    <p class="comarquage-meta"><?php echo $this->escape($universeLabels[$item['rubrique']] ?? $item['rubrique']); ?></p>
                    <h2><a href="<?php echo RouteHelper::getFicheRoute($item['alias'] ?: $item['id'], $item['rubrique'], ['return' => RouteHelper::getReturnToken()]); ?>"><?php echo $this->escape($renderer->title($item['title'])); ?></a></h2>
                    <?php if ($content !== '') : ?><p><?php echo $this->escape($content); ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>

            <?php if ($q !== '' && !$this->items) : ?>
                <p><?php echo Text::_('COM_COMARQUAGE_NO_RESULTS'); ?></p>
            <?php endif; ?>
        </div>
        <?php if ($this->pagination && $this->pagination->pagesTotal > 1) : ?>
            <nav class="comarquage-pagination" aria-label="Pagination">
                <?php echo $this->pagination->getPagesLinks(); ?>
            </nav>
        <?php endif; ?>
    </section>
    <?php include JPATH_COMPONENT . '/tmpl/common/signature.php'; ?>
</div>
