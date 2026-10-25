<?php
/**
 * Visible source attribution separates source date, record date and local import date.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
$db = Factory::getContainer()->get(DatabaseInterface::class);
$universe = $this->rubrique ?? Factory::getApplication()->getInput()->getCmd('view', 'particuliers');
$feed = $db->setQuery('SELECT * FROM #__comarquage_feeds WHERE universe=' . $db->quote($universe))->loadAssoc() ?: [];
$details = json_decode($feed['params'] ?? '', true) ?: [];
$source = $details['provenance'] ?? [];
$payload = json_decode($this->item['payload'] ?? '', true) ?: [];
$escape = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formatDate = static fn($s) => $s && strtotime($s) !== false ? gmdate('d/m/Y', strtotime($s)) : '';
?>
<footer class="comarquage-signature">
 <div class="comarquage-source"><p>
  <strong><?php echo $escape(Text::_('COM_COMARQUAGE_ATTRIBUTION')); ?> <a href="https://www.service-public.gouv.fr/P10004" rel="external noopener">Service-Public.gouv.fr / DILA</a></strong>
  <?php if (!empty($source['url'])) : ?>
  <span><?php echo $escape(Text::_('COM_COMARQUAGE_SOURCE_FILE')); ?> <a href="<?php echo $escape($source['url']); ?>" rel="external noopener"><?php echo $escape($source['filename']); ?></a> — <?php echo $escape($formatDate($source['last_modified'])); ?></span>
  <?php endif; ?>
  <?php if (!empty($payload['source_modified'])) : ?>
  <span><?php echo $escape(Text::_('COM_COMARQUAGE_RECORD_DATE')); ?> <?php echo $escape(str_replace('modified ', '', $payload['source_modified'])); ?></span>
  <?php endif; ?>
  <?php if (!empty($feed['last_imported'])) : ?>
  <span><?php echo $escape(Text::_('COM_COMARQUAGE_LOCAL_IMPORT')); ?> <?php echo $escape($formatDate($feed['last_imported'])); ?></span>
  <?php endif; ?>
  <span><a href="https://www.etalab.gouv.fr/licence-ouverte-open-licence/" rel="external noopener">Licence Ouverte 2.0</a> · Comarquage</span>
  <?php if (!empty($payload['source_url']) && preg_match('~^https://(?:www\.)?(?:entreprendre\.)?service-public\.gouv\.fr/~', $payload['source_url'])) : ?>
  <span><a href="<?php echo $escape($payload['source_url']); ?>" rel="external noopener"><?php echo $escape(Text::_('COM_COMARQUAGE_ORIGINAL_RECORD')); ?></a></span>
  <?php endif; ?>
 </p></div>
</footer>
