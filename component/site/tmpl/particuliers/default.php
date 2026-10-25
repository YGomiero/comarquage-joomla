<?php
/**
 * Distro — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Component\Comarquage\Site\Service\ContentRendererService;
use Joomla\Component\Comarquage\Site\Service\RouteHelper;
use Joomla\Component\Comarquage\Site\Service\LayoutThemeService;

$renderer = new ContentRendererService();
$input = Joomla\CMS\Factory::getApplication()->getInput();
$q = $input->getString('q', '');
$currentTheme = $input->getString('theme', '');
$currentSubtheme = $input->getString('subtheme', '');
$label = static fn (string $value): string => html_entity_decode($value, ENT_QUOTES, 'UTF-8');
$universeLabels = [
    'particuliers' => 'Particuliers',
    'associations' => 'Associations',
    'entreprises' => 'Entreprises',
];
$heroCopy = [
    'particuliers' => $label('Retrouvez les d&eacute;marches utiles du quotidien, organis&eacute;es par th&egrave;mes et pr&ecirc;tes &agrave; &ecirc;tre personnalis&eacute;es localement.'),
    'associations' => $label('Un acc&egrave;s clair aux formalit&eacute;s de cr&eacute;ation, de gestion et de financement des associations.'),
    'entreprises' => $label('Les informations pratiques pour cr&eacute;er, g&eacute;rer et d&eacute;velopper une activit&eacute; professionnelle.'),
];
$iconRules = [
    'Papiers - Citoyennete' => 'fa-id-card',
    'Famille' => 'fa-people-roof',
    'Social - Sante' => 'fa-heart-pulse',
    'Travail' => 'fa-briefcase',
    'Logement' => 'fa-house-chimney',
    'Transports' => 'fa-car-side',
    'Argent' => 'fa-coins',
    'Justice' => 'fa-scale-balanced',
    'Etranger' => 'fa-earth-europe',
    'Loisirs' => 'fa-umbrella-beach',
    'Creation' => 'fa-file-circle-plus',
    'Fonctionnement' => 'fa-gears',
    'Financement' => 'fa-hand-holding-dollar',
    'Benevoles' => 'fa-people-group',
    'Fiscalite' => 'fa-receipt',
    'Manifestations' => 'fa-calendar-days',
    'Gestion' => 'fa-screwdriver-wrench',
    'Ressources humaines' => 'fa-users-gear',
    'Vente' => 'fa-store',
    'International' => 'fa-globe',
];
$defaultIcons = ['fa-circle-info', 'fa-folder-open', 'fa-list-check', 'fa-compass', 'fa-landmark', 'fa-location-dot'];
$themeIcon = static function (string $title, int $index) use ($iconRules, $defaultIcons): string {
    $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title;

    foreach ($iconRules as $needle => $icon) {
        if (stripos($normalized, $needle) !== false) {
            return $icon;
        }
    }

    $fallback = $defaultIcons[$index % count($defaultIcons)];
    $activeTheme = (string) Joomla\CMS\Component\ComponentHelper::getParams('com_comarquage')->get('layout_theme', 'default');
    return LayoutThemeService::iconFor($title, $fallback, $activeTheme);
};
$renderThemeIcon = static fn (string $icon): string => LayoutThemeService::iconHtml($icon);
$fallbackThemes = [
    'particuliers' => [
        ['title' => $label('Papiers - Citoyennet&eacute;'), 'alias' => 'papiers-citoyennete'],
        ['title' => 'Famille', 'alias' => 'famille'],
        ['title' => $label('Social - Sant&eacute;'), 'alias' => 'social-sante'],
        ['title' => 'Travail', 'alias' => 'travail'],
        ['title' => 'Logement', 'alias' => 'logement'],
        ['title' => 'Transports', 'alias' => 'transports'],
        ['title' => 'Argent', 'alias' => 'argent'],
        ['title' => 'Justice', 'alias' => 'justice'],
        ['title' => $label('&Eacute;tranger'), 'alias' => 'etranger'],
        ['title' => 'Loisirs', 'alias' => 'loisirs'],
    ],
    'associations' => [
        ['title' => $label('Cr&eacute;ation'), 'alias' => 'creation'],
        ['title' => 'Fonctionnement', 'alias' => 'fonctionnement'],
        ['title' => 'Financement', 'alias' => 'financement'],
        ['title' => $label('B&eacute;n&eacute;voles'), 'alias' => 'benevoles'],
        ['title' => $label('Fiscalit&eacute;'), 'alias' => 'fiscalite'],
        ['title' => 'Manifestations', 'alias' => 'manifestations'],
    ],
    'entreprises' => [
        ['title' => $label('Cr&eacute;ation'), 'alias' => 'creation'],
        ['title' => 'Gestion', 'alias' => 'gestion'],
        ['title' => $label('Fiscalit&eacute;'), 'alias' => 'fiscalite'],
        ['title' => 'Ressources humaines', 'alias' => 'ressources-humaines'],
        ['title' => 'Vente', 'alias' => 'vente'],
        ['title' => 'International', 'alias' => 'international'],
    ],
];
$topicCards = $this->themes ?: ($fallbackThemes[$this->rubrique] ?? []);
$subthemeCards = property_exists($this, 'subthemes') ? (array) $this->subthemes : [];
$hiddenHomeThemeAliases = ['comment-faire-si', 'vos-questions', 'services-en-ligne-et-formulaires'];
$resultsTotal = $this->pagination ? (int) $this->pagination->total : count($this->items);
$fallbackFeatured = [
    'particuliers' => [
        ['icon' => 'fa-route', 'title' => $label('&Eacute;v&eacute;nements de vie'), 'links' => [$label('Je d&eacute;m&eacute;nage'), $label('Je pr&eacute;pare mes papiers'), 'Je cherche une aide']],
        ['icon' => 'fa-laptop', 'title' => 'Services en ligne', 'links' => [$label('Pr&eacute;-demande de carte d&rsquo;identit&eacute;'), $label('Changement d&rsquo;adresse'), $label('Demande d&rsquo;acte d&rsquo;&eacute;tat civil')]],
        ['icon' => 'fa-circle-question', 'title' => $label('Questions - R&eacute;ponses'), 'links' => ['Quels justificatifs fournir ?', 'Qui contacter ?', $label('Quels d&eacute;lais pr&eacute;voir ?')]],
    ],
    'associations' => [
        ['icon' => 'fa-rocket', 'title' => $label('Cr&eacute;er et g&eacute;rer'), 'links' => [$label('D&eacute;clarer une association'), 'Modifier les statuts', 'Publier une annonce']],
        ['icon' => 'fa-piggy-bank', 'title' => 'Financer', 'links' => 'Demander une subvention|Organiser une manifestation|Recevoir des dons'],
        ['icon' => 'fa-circle-question', 'title' => $label('Questions - R&eacute;ponses'), 'links' => [$label('Qui peut &ecirc;tre b&eacute;n&eacute;vole ?'), 'Quelles obligations ?', 'Quels registres tenir ?']],
    ],
    'entreprises' => [
        [
            'icon' => 'fa-seedling',
            'title' => $label('Cr&eacute;er son activit&eacute;'),
            'links' => [
                ['title' => 'Choisir un statut', 'url' => RouteHelper::getFicheRoute('creation-dentreprise-choisir-la-forme-juridique-de-votre-entreprise', 'entreprises')],
                ['title' => 'Immatriculer son entreprise', 'url' => RouteHelper::getFicheRoute('creation-dentreprise-formalites-dimmatriculation-dune-societe', 'entreprises')],
                ['title' => $label('D&eacute;clarer son activit&eacute;'), 'url' => RouteHelper::getFicheRoute('creation-dentreprise-formalites-dimmatriculation-dune-entreprise-individuelle', 'entreprises')],
            ],
        ],
        [
            'icon' => 'fa-chart-line',
            'title' => $label('G&eacute;rer'),
            'links' => [
                ['title' => 'Embaucher', 'url' => RouteHelper::getFicheRoute('procedure-et-formalites-dembauche-dun-salarie-du-secteur-prive', 'entreprises')],
                ['title' => 'Facturer', 'url' => RouteHelper::getFicheRoute('tout-savoir-sur-la-facturation', 'entreprises')],
                ['title' => 'Payer ses cotisations', 'url' => RouteHelper::getFicheRoute('declarer-et-payer-les-cotisations-et-contributions-sociales-des-salaries', 'entreprises')],
            ],
        ],
        [
            'icon' => 'fa-circle-question',
            'title' => $label('Questions - R&eacute;ponses'),
            'links' => [
                ['title' => $label('Quelles d&eacute;marches fiscales ?'), 'url' => RouteHelper::getViewRoute('entreprises', ['theme' => 'fiscalite'])],
                ['title' => 'Quelles aides ?', 'url' => RouteHelper::getViewRoute('entreprises', ['theme' => 'financement'])],
                ['title' => $label('Comment fermer une activit&eacute; ?'), 'url' => RouteHelper::getViewRoute('entreprises', ['theme' => 'difficultes-financieres'])],
            ],
        ],
    ],
];
$panelThemeAliases = [
    'particuliers' => [
        'Comment faire si...' => 'comment-faire-si',
        html_entity_decode('&Eacute;v&eacute;nements de vie', ENT_QUOTES, 'UTF-8') => 'comment-faire-si',
        'Services en ligne' => 'services-en-ligne-et-formulaires',
    ],
    'associations' => [
        html_entity_decode('Cr&eacute;er et g&eacute;rer', ENT_QUOTES, 'UTF-8') => 'creation',
        'Financer' => 'financement',
    ],
    'entreprises' => [
        html_entity_decode('Cr&eacute;er son activit&eacute;', ENT_QUOTES, 'UTF-8') => 'etapes-de-vie',
        html_entity_decode('G&eacute;rer', ENT_QUOTES, 'UTF-8') => 'fonctionnement-de-lentreprise',
    ],
];
?>
<?php $layoutTheme = Joomla\CMS\Component\ComponentHelper::getParams('com_comarquage')->get('layout_theme', 'default'); ?>
<div class="comarquage comarquage-theme-<?php echo $this->escape($layoutTheme); ?> comarquage-<?php echo $this->escape($this->rubrique); ?>">
    <section class="comarquage-hero">
        <div class="comarquage-hero-content">
            <a class="comarquage-kicker" href="https://www.service-public.gouv.fr/" rel="noopener noreferrer">
                <i class="fa-solid fa-landmark" aria-hidden="true"></i>
                <span>Service-Public.gouv.fr</span>
            </a>
            <h1><?php echo Text::_('COM_COMARQUAGE_' . strtoupper($this->rubrique)); ?></h1>
            <p><?php echo $this->escape($heroCopy[$this->rubrique] ?? Text::_('COM_COMARQUAGE_OPEN_DATA_LABEL')); ?></p>
        </div>
        <form class="comarquage-search-panel" action="<?php echo RouteHelper::getViewRoute($this->rubrique); ?>" method="get">
            <label for="comarquage-q">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <?php echo Text::_('COM_COMARQUAGE_SEARCH_LABEL'); ?>
            </label>
            <div class="comarquage-search-row">
                <input id="comarquage-q" name="q" type="search" placeholder="Passeport, subvention, embauche..." value="<?php echo $this->escape($q); ?>">
                <button type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            </div>
        </form>
    </section>

    <nav class="comarquage-switcher" aria-label="Univers">
        <?php foreach ($universeLabels as $view => $title) : ?>
            <a class="<?php echo $view === $this->rubrique ? 'is-active' : ''; ?>" href="<?php echo RouteHelper::getViewRoute($view); ?>">
                <?php echo $this->escape($title); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($this->item) : ?>
        <a class="comarquage-back" href="<?php echo $this->escape(RouteHelper::getReturnUrl($this->rubrique)); ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour &agrave; la liste</a>
        <article class="comarquage-fiche">
            <span class="comarquage-badge"><?php echo $this->escape($universeLabels[$this->rubrique] ?? $this->rubrique); ?></span>
            <h2><?php echo $this->escape($renderer->title($this->item['title'])); ?></h2>
            <div class="comarquage-fiche-tools" aria-label="Lecture de la fiche"><button type="button" data-comarquage-expand="all"><i class="fa-solid fa-plus" aria-hidden="true"></i> Tout d&eacute;plier</button><button type="button" data-comarquage-expand="none"><i class="fa-solid fa-minus" aria-hidden="true"></i> Tout replier</button></div>
            <?php echo $renderer->renderItem($this->item); ?>
        </article>
    <?php else : ?>
        <?php if ($q === '' && $currentTheme !== '') : ?>
            <?php
            $selectedTheme = null;
            foreach ($topicCards as $index => $theme) {
                if ($theme['alias'] === $currentTheme) {
                    $selectedTheme = $theme + ['index' => $index];
                    break;
                }
            }

            if (!$selectedTheme && $currentTheme === 'comment-faire-si') {
                $selectedTheme = [
                    'title' => $label('&Eacute;v&eacute;nements de vie'),
                    'alias' => 'comment-faire-si',
                    'index' => 0,
                ];
            }
            ?>
            <section class="comarquage-section comarquage-theme-selected">
                <a class="comarquage-back" href="<?php echo RouteHelper::getViewRoute($this->rubrique); ?>">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    Tous les th&egrave;mes
                </a>
                <?php if ($selectedTheme) : ?>
                    <div class="comarquage-topic is-active">
                        <span class="comarquage-topic-icon"><?php echo $renderThemeIcon($themeIcon((string) $selectedTheme['title'], (int) $selectedTheme['index'])); ?></span>
                        <strong><?php echo $this->escape($selectedTheme['title']); ?></strong>
                        <small><?php echo $subthemeCards && $currentSubtheme === '' ? 'Choisir une rubrique' : $resultsTotal . ' r&eacute;sultats'; ?></small>
                    </div>
                <?php endif; ?>
            </section>
        <?php elseif ($q === '' && $topicCards) : ?>
            <section class="comarquage-section">
                <div class="comarquage-section-head">
                    <span><?php echo Text::_('COM_COMARQUAGE_OPEN_DATA_LABEL'); ?></span>
                    <h2><?php echo Text::_('COM_COMARQUAGE_THEMES'); ?></h2>
                </div>
                <div class="comarquage-topic-grid" aria-label="<?php echo Text::_('COM_COMARQUAGE_THEMES'); ?>">
                    <?php foreach ($topicCards as $index => $theme) : ?>
                        <?php if (in_array((string) ($theme['alias'] ?? ''), $hiddenHomeThemeAliases, true)) { continue; } ?>
                        <?php $icon = $themeIcon((string) $theme['title'], (int) $index); ?>
                        <a class="comarquage-topic" href="<?php echo RouteHelper::getViewRoute($this->rubrique, ['theme' => $theme['alias']]); ?>">
                            <span class="comarquage-topic-icon"><?php echo $renderThemeIcon($icon); ?></span>
                            <strong><?php echo $this->escape($theme['title']); ?></strong>
                            <small><?php echo Text::_('COM_COMARQUAGE_TOPIC_BROWSE'); ?></small>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($q === '' && $currentTheme !== '' && $currentSubtheme === '' && $subthemeCards) : ?>
            <section class="comarquage-section">
                <div class="comarquage-section-head">
                    <span>Rubriques</span>
                    <h2><?php echo $selectedTheme ? $this->escape($selectedTheme['title']) : 'Rubriques'; ?></h2>
                </div>
                <div class="comarquage-topic-grid" aria-label="Rubriques">
                    <?php foreach ($subthemeCards as $index => $subtheme) : ?>
                        <?php
                        $subthemeReturnRoute = $currentTheme === 'comment-faire-si'
                            ? '/evenements-de-vie.html'
                            : RouteHelper::getViewRoute($this->rubrique, ['theme' => $currentTheme]);
                        $subthemeUrl = !empty($subtheme['item'])
                            ? RouteHelper::getFicheRoute($subtheme['item'], $this->rubrique, ['return' => RouteHelper::getReturnTokenForUrl($subthemeReturnRoute)])
                            : RouteHelper::getViewRoute($this->rubrique, ['theme' => $currentTheme, 'subtheme' => $subtheme['alias']]);
                        ?>
                        <a class="comarquage-topic" href="<?php echo $this->escape($subthemeUrl); ?>">
                            <span class="comarquage-topic-icon"><i class="fa-solid <?php echo $this->escape($subtheme['icon'] ?? $themeIcon((string) $subtheme['title'], (int) $index)); ?>" aria-hidden="true"></i></span>
                            <strong><?php echo $this->escape($subtheme['title']); ?></strong>
                            <?php if (!empty($subtheme['description'])) : ?><small><?php echo $this->escape($subtheme['description']); ?></small><?php endif; ?>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($this->items && ($q !== '' || $currentTheme !== '')) : ?>
            <section class="comarquage-section comarquage-results" aria-label="<?php echo Text::_('COM_COMARQUAGE_RESULTS'); ?>">
                <div class="comarquage-section-head">
                    <span><?php echo $resultsTotal; ?> r&eacute;sultats</span>
                    <h2><?php echo $q !== '' ? Text::_('COM_COMARQUAGE_SEARCH_RESULTS') : Text::_('COM_COMARQUAGE_POPULAR_RECORDS'); ?></h2>
                </div>
                <div class="comarquage-list">
                    <?php foreach ($this->items as $item) : ?>
                        <article class="comarquage-card">
                            <?php $excerpt = $renderer->excerptForItem($item); ?>
                            <h3><a href="<?php echo RouteHelper::getFicheRoute($item['alias'] ?: $item['id'], $this->rubrique, ['return' => RouteHelper::getReturnToken()]); ?>"><?php echo $this->escape($renderer->title($item['title'])); ?></a></h3>
                            <?php if ($excerpt !== '') : ?><p><?php echo $this->escape($excerpt); ?></p><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php if ($this->pagination && $this->pagination->pagesTotal > 1) : ?>
                    <nav class="comarquage-pagination" aria-label="Pagination">
                        <?php echo $this->pagination->getPagesLinks(); ?>
                    </nav>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="comarquage-featured" aria-label="Acc&egrave;s rapides">
            <?php foreach (($this->homePanels ?: ($fallbackFeatured[$this->rubrique] ?? [])) as $panel) : ?>
                <?php if (!is_array($panel)) { continue; } ?>
                <?php $rawLinks = $panel['links'] ?? []; ?>
                <?php $links = is_array($rawLinks) ? $rawLinks : explode('|', (string) $rawLinks); ?>
                <?php $panelTitle = html_entity_decode((string) $panel['title'], ENT_QUOTES, 'UTF-8'); ?>
                <?php $panelTarget = $panel['url'] ?? (!empty($panelThemeAliases[$this->rubrique][$panelTitle]) ? RouteHelper::getViewRoute($this->rubrique, ['theme' => $panelThemeAliases[$this->rubrique][$panelTitle]]) : ''); ?>
                <article>
                    <div class="comarquage-featured-icon"><i class="fa-solid <?php echo $this->escape($panel['icon']); ?>" aria-hidden="true"></i></div>
                    <h2>
                        <?php if ($panelTarget !== '') : ?>
                            <a href="<?php echo $this->escape($panelTarget); ?>"><?php echo $this->escape($panel['title']); ?></a>
                        <?php else : ?>
                            <?php echo $this->escape($panel['title']); ?>
                        <?php endif; ?>
                    </h2>
                    <?php if ($links) : ?>
                        <ul>
                            <?php foreach ($links as $link) : ?>
                            <?php $linkTitle = is_array($link) ? (string) ($link['title'] ?? '') : (string) $link; ?>
                            <?php $linkUrl = is_array($link) && !empty($link['url']) ? (string) $link['url'] : RouteHelper::getViewRoute($this->rubrique, ['q' => html_entity_decode($linkTitle, ENT_QUOTES, 'UTF-8')]); ?>
                                <li>
                                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                                    <a href="<?php echo $this->escape($linkUrl); ?>"><?php echo $this->escape($linkTitle); ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($panelTarget !== '') : ?>
                        <p><a class="comarquage-featured-more" href="<?php echo $this->escape($panelTarget); ?>">Voir la liste</a></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    <?php include JPATH_COMPONENT . '/tmpl/common/signature.php'; ?>
</div>

