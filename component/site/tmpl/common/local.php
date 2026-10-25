<?php
/**
 * Municipality context and relevant nearby services, without live visitor API calls.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;
$localParams = \Joomla\CMS\Component\ComponentHelper::getParams('com_comarquage');
$localCode = strtoupper(trim((string) $localParams->get('code_insee', '')));
if (!(int) $localParams->get('activer_personnalisation_locale', 0) || !(int) $localParams->get('show_local_blocks', 1)
    || !preg_match('/^(?:[0-9]{5}|2[AB][0-9]{3})$/D', $localCode)) { return; }
$localDb = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
$localRows = $localDb->setQuery('SELECT title,type,url,payload FROM #__comarquage_local_organisations WHERE state=1 AND code_insee=' . $localDb->quote($localCode) . ' ORDER BY title')->loadAssocList() ?: [];
$preferred = [
    'particuliers' => ['mairie','france_services','ccas','sip'],
    'associations' => ['mairie','sous_pref','prefecture','greffe_associations','epci','france_services'],
    'entreprises' => ['cci','cma','sie','urssaf','epci','mairie'],
][$this->rubrique ?? 'particuliers'] ?? ['mairie'];
$localRows = array_values(array_filter($localRows, static fn($row) => (bool) array_intersect(explode(',', $row['type']), $preferred)));
usort($localRows, static fn($a,$b) => (array_search(explode(',', $a['type'])[0], $preferred) ?: 0) <=> (array_search(explode(',', $b['type'])[0], $preferred) ?: 0));
if (!$localRows) { return; }
$localEscape = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$localDetails = json_decode($localRows[0]['payload'], true) ?: [];
?>
<aside class="comarquage-local" aria-labelledby="comarquage-local-title">
 <h2 id="comarquage-local-title"><?php echo $localEscape(\Joomla\CMS\Language\Text::sprintf('COM_COMARQUAGE_LOCAL_TITLE', $localParams->get('nom_commune', '') ?: $localCode)); ?></h2>
 <p><?php echo $localEscape(\Joomla\CMS\Language\Text::sprintf('COM_COMARQUAGE_LOCAL_HINT', $localCode)); ?></p>
 <ul><?php foreach (array_slice($localRows, 0, 6) as $localRow) : ?>
  <li><a href="<?php echo $localEscape($localRow['url']); ?>" rel="external noopener"><?php echo $localEscape($localRow['title']); ?></a></li>
 <?php endforeach; ?></ul>
 <small><?php echo $localEscape(\Joomla\CMS\Language\Text::_('COM_COMARQUAGE_LOCAL_SOURCE')); ?>
  <a href="https://api-lannuaire.service-public.gouv.fr/explore/dataset/api-lannuaire-administration/information/" rel="external noopener">Service-Public / DILA</a> · Licence Ouverte 2.0 ·
  <?php echo $localEscape(substr($localDetails['retrieved'] ?? '', 0, 10)); ?>
 </small>
</aside>
