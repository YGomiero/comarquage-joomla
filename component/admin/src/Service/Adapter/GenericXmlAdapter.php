<?php
/**
 * Comarquage — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\Service\Adapter;

use Joomla\CMS\Filter\OutputFilter;

defined('_JEXEC') or die;

class GenericXmlAdapter implements FeedAdapterInterface
{
    private string $currentUniverse = '';
    private array $referenceTitles = [];

    public function supports(string $version): bool
    {
        return true;
    }

    public function adapt(\DOMDocument $document, string $universe, callable $log): array
    {
        $this->currentUniverse = $universe;
        $xpath = new \DOMXPath($document);
        $nodes = $this->publicationNodes($xpath, $document);
        $items = [];
        $categories = [];
        $relations = [];

        foreach ($nodes as $node) {
            // Associations now belong to the Particuliers feed (official theme N31931).
            if ($universe === 'associations' && !$xpath->query('.//*[local-name()="FilDAriane"]/*[@ID="N31931"] | ./*[local-name()="Theme"][@ID="N31931"]', $node)->length && $node->attributes?->getNamedItem('ID')?->nodeValue !== 'N31931') {
                continue;
            }
            $title = $this->cleanTitleText($this->firstText($xpath, $node, ['Titre', 'title', 'Intitule']));

            if ($title === '') {
                $log('warning', ['message' => 'No title found', 'node' => $node->nodeName]);
                continue;
            }

            $externalId = $node->attributes?->getNamedItem('ID')?->nodeValue
                ?: $node->attributes?->getNamedItem('id')?->nodeValue
                ?: $node->attributes?->getNamedItem('xml:id')?->nodeValue
                ?: substr(sha1($universe . $title . $node->textContent), 0, 24);

            $categoryTitle = $this->cleanTitleText($this->firstText($xpath, $node, ['Theme/Titre', 'FilDAriane/Niveau[2]', 'Categorie', 'category', 'Univers']));
            if ($universe === 'associations') {
                $categoryTitle = $this->cleanTitleText($this->firstText($xpath, $node, ['SousThemePere', 'DossierPere', 'Theme/Titre'])) ?: $categoryTitle;
            }
            $categoryId = $categoryTitle !== '' ? substr(sha1($universe . ':' . $categoryTitle), 0, 24) : '';

            if ($categoryTitle !== '') {
                $categories[$categoryId] = [
                    'external_id' => $categoryId,
                    'parent_external_id' => '',
                    'title' => $categoryTitle,
                    'alias' => OutputFilter::stringURLSafe($categoryTitle),
                    'path' => OutputFilter::stringURLSafe($categoryTitle),
                    'level' => 1,
                    'payload' => ['source' => 'generic'],
                ];
                $relations[] = ['item_external_id' => $externalId, 'category_external_id' => $categoryId];
            }

            $intro = $this->cleanTitleText($this->firstText($xpath, $node, ['description', 'Introduction/Texte/Paragraphe', 'Introduction/Paragraphe']));
            $text = $this->plainText($node);
            $this->referenceTitles = $this->collectReferenceTitles($xpath, $node);
            $structuredHtml = $this->structuredHtml($xpath, $node, $log);

            $items[] = [
                'external_id' => preg_replace('/[^A-Za-z0-9_.:-]/', '', $externalId),
                'title' => $title,
                'alias' => OutputFilter::stringURLSafe($title),
                'introtext' => $intro !== '' ? $intro : mb_substr($text, 0, 500),
                'body' => $text,
                'metadesc' => $intro !== '' ? mb_substr($intro, 0, 160) : mb_substr($text, 0, 160),
                'payload' => [
                    'source_node' => $node->nodeName,
                    'source_modified' => $this->firstText($xpath, $node, ['date']),
                    'source_url' => $node instanceof \DOMElement ? (string) $node->getAttribute('spUrl') : '',
                    'structured_version' => 2,
                    'structured_html' => $structuredHtml,
                ],
            ];
        }

        return ['items' => $items, 'categories' => array_values($categories), 'relations' => $relations];
    }

    protected function firstText(\DOMXPath $xpath, \DOMNode $context, array $paths): string
    {
        foreach ($paths as $path) {
            $parts = array_map('trim', explode('/', $path));
            $query = '.';

            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }

                if (preg_match('/^([A-Za-z0-9_-]+)\[(\d+)\]$/', $part, $matches)) {
                    $query .= '/*[local-name()="' . $matches[1] . '"][' . (int) $matches[2] . ']';
                } else {
                    $query .= '/*[local-name()="' . $part . '"][1]';
                }
            }

            $node = $xpath->query($query, $context)->item(0);

            if ($node && trim($node->textContent) !== '') {
                return trim($node->textContent);
            }
        }

        return '';
    }

    private function publicationNodes(\DOMXPath $xpath, \DOMDocument $document): \DOMNodeList
    {
        $rootName = $document->documentElement?->localName ?? '';

        if (in_array($rootName, ['Fiche', 'Publication', 'ServiceComplementaire', 'Dossier', 'Ressource', 'item'], true)) {
            return $xpath->query('/*');
        }

        if (in_array($rootName, ['Arborescence', 'Menu', 'DatesModImp', 'CentresDeContact', 'Redirections'], true)) {
            return $xpath->query('/*[false()]');
        }
        return $xpath->query('/*/*[local-name()="Fiche" or local-name()="Publication" or local-name()="Dossier" or local-name()="Ressource" or local-name()="item"]');
    }

    private function structuredHtml(\DOMXPath $xpath, \DOMNode $publication, callable $log): string
    {
        $parts = [];
        $queries = [
            'Introduction' => './*[local-name()="Introduction"]',
            'Texte' => './*[local-name()="Texte"]',
            'ListeSituations' => './*[local-name()="ListeSituations"]',
            'Problematiques' => './*[local-name()="Problematiques"]',
            'ServiceEnLigne' => './*[local-name()="ServiceEnLigne"]',
            'RechercheGuidee' => './*[local-name()="RechercheGuidee"]',
            'VoirAussi' => './*[local-name()="VoirAussi"]',
            'QuiPeutMAider' => './*[local-name()="QuiPeutMAider"]',
            'OuSAdresser' => './*[local-name()="OuSAdresser"]',
            'Reference' => './*[local-name()="Reference"]',
            'PourEnSavoirPlus' => './*[local-name()="PourEnSavoirPlus"]',
            'Definition' => './*[local-name()="Definition"]',
            'Abreviation' => './*[local-name()="Abreviation" or local-name()="Abréviation"]',
        ];

        if (($publication->localName ?? '') === 'ServiceComplementaire') {
            // Complementary resources have their own descriptive blocks and action URL.
            foreach (['Descriptif', 'Description', 'Public', 'Recherchable', 'Conditions', 'Modalites', 'Langue'] as $block) {
                $queries[$block] = './*[local-name()="' . $block . '"]';
            }
            foreach ($xpath->query('./*[local-name()="LienWeb"]', $publication) as $link) {
                $url = $link->getAttribute('URL');
                if ($this->isAllowedUrl($url)) {
                    $parts[] = '<p><a href="' . $this->escapeAttribute($url) . '" rel="noopener noreferrer" target="_blank">Accéder au service en ligne</a></p>';
                }
            }
        }
        foreach ($queries as $query) {
            foreach ($xpath->query($query, $publication) ?: [] as $node) {
                $html = $this->renderBlock($node, 2);

                if ($html !== '') {
                    $parts[] = $html;
                }
            }
        }

        $questionHtml = $this->renderResourceGroup($xpath->query('./*[local-name()="QuestionReponse"]', $publication), 'Questions liées');

        if ($questionHtml !== '') {
            $parts[] = $questionHtml;
        }

        if (!$parts) {
            $log('warning', ['message' => 'No structured content found', 'node' => $publication->nodeName]);
        }

        return $parts ? '<div class="comarquage-structured">' . $this->mergeAdjacentLists(implode('', $parts)) . '</div>' : '';
    }

    private function renderBlock(\DOMNode $node, int $level = 2): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = trim((string) $node->textContent);

            return $text !== '' ? '<p>' . $this->escape($text) . '</p>' : '';
        }

        if (!$node instanceof \DOMElement) {
            return '';
        }

        $name = $node->localName ?: $node->nodeName;

        return match ($name) {
            'Introduction' => $this->wrap('div', $this->renderChildren($node, $level), 'comarquage-fiche-intro'),
            'Texte' => $this->renderChildren($node, $level),
            'ListeSituations' => $this->renderSituations($node, $level),
            'Situation' => $this->renderSituation($node, min(4, $level + 1)),
            'Chapitre' => $this->renderChapter($node, $level),
            'SousChapitre' => $this->renderSubSection($node, min(4, $level + 1)),
            'BlocCas' => $this->renderCaseGroup($node, min(4, $level + 1)),
            'Cas' => $this->renderCase($node, min(4, $level + 1)),
            'Titre' => $this->renderHeading($node, $level),
            'Paragraphe' => $this->renderParagraph($node),
            'Liste' => $this->renderList($node),
            'Item' => '<li>' . $this->renderChildren($node, $level) . '</li>',
            'Tableau' => $this->renderTable($node),
            'TitreRiche' => $this->renderHeading($node, min(4, $level + 1)),
            'TitreFlottant' => $this->renderFloatingTitle($node, min(4, $level + 1)),
            'Complement' => $this->renderTitledBlock($node, 'Complément', $level),
            'Definition', 'Abreviation', 'Abréviation' => $this->renderDefinition($node, $level),
            'Attention', 'ASavoir', 'ANoter', 'Avertissement', 'Exemple', 'Rappel' => $this->renderNotice($node, $name, $level),
            'VoirAussi' => $this->renderRelatedSection($node, 'Voir aussi'),
            'QuiPeutMAider' => $this->renderResource($node, 'Qui peut m’aider ?'),
            'OuSAdresser' => $this->renderResource($node, 'Où s’adresser ?'),
            'Reference' => $this->renderResource($node, 'Textes de référence'),
            'PourEnSavoirPlus' => $this->renderResource($node, 'Pour en savoir plus'),
            'QuestionReponse' => $this->renderResource($node, 'Question réponse'),
            'ServiceEnLigne', 'ServiceEnLigneAnnexe' => $this->renderResource($node, 'Service en ligne'),
            'RechercheGuidee' => $this->renderGuidedSearchReference($node),
            'Problematiques' => $this->renderProblematiques($node, $level),
            'Problematique', 'Branche' => $this->renderDecisionNode($node, $level),
            'SiSelectionne', 'SiNonSelectionne', 'affecteVrai', 'affecteFaux' => '',
            'FicheLiee', 'Dossier', 'CommentFaireSi', 'RessourceWeb', 'LienInterne' => $this->renderResource($node, $this->humanizeNodeName($name)),
            'Source' => $this->renderSource($node),
            'FragmentConditionne', 'Condition', 'Contenu', 'ContenuIllustre', 'Groupe' => $this->renderChildren($node, $level),
            default => $this->renderUnknownBlock($node, $level),
        };
    }

    private function renderChildren(\DOMNode $node, int $level = 2, bool $skipTitle = false): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            if ($skipTitle && $child instanceof \DOMElement && ($child->localName ?: $child->nodeName) === 'Titre') {
                continue;
            }

            $html .= $this->renderBlock($child, $level);
        }

        return $html;
    }

    private function renderInlineChildren(\DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $html .= $this->escape((string) $child->nodeValue);
                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = $child->localName ?: $child->nodeName;
            $normalizedRowName = $this->normalizeNodeName($name);

            if ($normalizedRowName === 'rangee') {
                $name = 'Rangee';
            } elseif ($normalizedRowName === 'titreriche') {
                $name = 'TitreRiche';
            } elseif ($normalizedRowName === 'titre') {
                $name = 'Titre';
            } elseif ($normalizedRowName === 'rang') {
                $name = 'Rang';
            }

            if ($this->normalizeNodeName($name) === 'rangee') {
                $name = 'RangÃ©e';
            }
            $content = $this->renderInlineChildren($child);

            $html .= match ($name) {
                'MiseEnEvidence' => $content !== '' ? '<strong>' . $content . '</strong>' : $this->spaceFromEmptyInline($child),
                'Expression' => $content !== '' ? '<em>' . $content . '</em>' : $this->spaceFromEmptyInline($child),
                'Valeur' => $content !== '' ? '<span class="comarquage-value">' . $content . '</span>' : '',
                'Variable' => $content !== '' ? '<var>' . $content . '</var>' : '',
                'Citation' => $content !== '' ? '<q>' . $content . '</q>' : '',
                'Exposant' => $content !== '' ? '<sup>' . $content . '</sup>' : '',
                'Indice' => $content !== '' ? '<sub>' . $content . '</sub>' : '',
                'Acronyme', 'Sigle', 'Abreviation', 'Abréviation' => $content !== '' ? '<abbr>' . $content . '</abbr>' : '',
                'LienExterne', 'LienWeb', 'LienExterneCommente' => $this->renderExternalLink($child, $content),
                'LienInterne', 'LienIntra' => $this->renderInternalReference($child, $content),
                default => $content,
            };
        }

        return trim(preg_replace('/\s+/u', ' ', $html) ?? $html);
    }

    private function renderParagraph(\DOMNode $node): string
    {
        $content = $this->renderInlineChildren($node);

        return $content !== '' ? '<p>' . $content . '</p>' : '';
    }

    private function renderHeading(\DOMNode $node, int $level): string
    {
        $text = $this->cleanTitleText($this->nodeTitle($node));

        if ($text === '') {
            return '';
        }

        $tag = 'h' . max(2, min(4, $level));

        return '<' . $tag . ' class="comarquage-section-title">' . $this->escape($text) . '</' . $tag . '>';
    }

    private function renderChapter(\DOMElement $node, int $level): string
    {
        $title = $this->directTitle($node) ?: 'Informations';
        $body = $this->renderChildren($node, $level, true);

        if ($body === '') {
            return '';
        }

        return '<details class="comarquage-accordion comarquage-structured-section"' . $this->conditionAttributes($node) . '>'
            . '<summary><span>' . $this->escape($title) . '</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>'
            . '<div class="comarquage-accordion-body">' . $body . '</div>'
            . '</details>';
    }

    private function renderSubSection(\DOMElement $node, int $level): string
    {
        $title = $this->directTitle($node);
        $body = $this->renderChildren($node, $level, true);

        if ($title !== '') {
            $body = '<h' . max(3, min(5, $level)) . ' class="comarquage-subsection-title">' . $this->escape($title) . '</h' . max(3, min(5, $level)) . '>' . $body;
        }

        return $body !== '' ? '<section class="comarquage-subsection"' . $this->conditionAttributes($node) . '>' . $body . '</section>' : '';
    }

    private function renderSituations(\DOMElement $node, int $level): string
    {
        $body = $this->renderChildren($node, $level);

        return $body !== '' ? '<div class="comarquage-situations">' . $body . '</div>' : '';
    }

    private function renderSituation(\DOMElement $node, int $level): string
    {
        $title = $this->directTitle($node);
        $body = $this->renderChildren($node, $level, true);

        if ($body === '') {
            return '';
        }

        if ($title === '') {
            return '<section class="comarquage-situation"' . $this->conditionAttributes($node) . '>' . $body . '</section>';
        }

        return '<details class="comarquage-situation"' . $this->conditionAttributes($node) . '>'
            . '<summary>' . $this->escape($title) . '</summary>'
            . '<div class="comarquage-situation-body">' . $body . '</div>'
            . '</details>';
    }

    private function renderCase(\DOMElement $node, int $level): string
    {
        $title = $this->directTitle($node);
        $body = $this->renderChildren($node, $level, true);

        if ($body === '') {
            return '';
        }

        if ($title === '') {
            return '<div class="comarquage-case"' . $this->conditionAttributes($node) . '>' . $body . '</div>';
        }

        return '<details class="comarquage-case"' . $this->conditionAttributes($node) . '><summary>' . $this->escape($title) . '</summary><div>' . $body . '</div></details>';
    }

    private function renderCaseGroup(\DOMElement $node, int $level): string
    {
        $title = $this->directTitle($node);
        $display = strtolower(trim($node->getAttribute('affichage')));
        $body = $this->renderChildren($node, $level, true);

        if ($body === '') {
            return '';
        }

        $classes = ['comarquage-case-group'];

        if ($display !== '') {
            $classes[] = 'comarquage-case-group-' . preg_replace('/[^a-z0-9_-]/', '', $display);
        }

        if ($title !== '') {
            $body = '<h' . max(3, min(5, $level)) . ' class="comarquage-case-group-title">' . $this->escape($title) . '</h' . max(3, min(5, $level)) . '>' . $body;
        }

        return '<section class="' . implode(' ', $classes) . '"' . $this->conditionAttributes($node) . '>' . $body . '</section>';
    }

    private function renderList(\DOMElement $node): string
    {
        $type = strtolower(trim($node->getAttribute('type')));
        $tag = in_array($type, ['numero', 'numéro', 'number', 'ordered'], true) ? 'ol' : 'ul';
        $typeClass = $type !== '' ? ' comarquage-list-type-' . preg_replace('/[^a-z0-9_-]/', '', str_replace(['é', 'è', 'ê'], 'e', $type)) : '';
        $items = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && ($child->localName ?: $child->nodeName) === 'Item') {
                $content = $this->renderChildren($child, 3);

                if ($content !== '') {
                    $items .= '<li>' . $content . '</li>';
                }
            }
        }

        return $items !== '' ? '<' . $tag . ' class="comarquage-list comarquage-list-' . $tag . $typeClass . '">' . $items . '</' . $tag . '>' : '';
    }

    private function renderTable(\DOMElement $node): string
    {
        $caption = '';
        $rows = '';

        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = $child->localName ?: $child->nodeName;

            if ($name === 'TitreRiche' || $name === 'Titre') {
                $caption = $this->nodeTitle($child);
                continue;
            }

            if ($name !== 'Rangée' && $name !== 'Rangee' && $name !== 'Rang') {
                continue;
            }

            $isHeader = strtolower($child->getAttribute('type')) === 'header';
            $cells = '';

            foreach ($child->childNodes as $cell) {
                if (!$cell instanceof \DOMElement || $this->normalizeNodeName($cell->localName ?: $cell->nodeName) !== 'cellule') {
                    continue;
                }

                $content = $this->renderChildren($cell, 3);
                $content = $content !== '' ? $content : $this->escape($this->nodeTitle($cell));

                if ($content === '') {
                    continue;
                }

                $tag = $isHeader ? 'th' : 'td';
                $scope = $isHeader ? ' scope="col"' : '';
                $cells .= '<' . $tag . $scope . '>' . $content . '</' . $tag . '>';
            }

            if ($cells !== '') {
                $rows .= '<tr>' . $cells . '</tr>';
            }
        }

        if ($rows === '') {
            return '';
        }

        return '<div class="comarquage-table-wrap"><table class="comarquage-table">'
            . ($caption !== '' ? '<caption>' . $this->escape($caption) . '</caption>' : '')
            . '<tbody>' . $rows . '</tbody></table></div>';
    }

    private function renderNotice(\DOMElement $node, string $name, int $level): string
    {
        $labels = [
            'Attention' => 'Attention',
            'ASavoir' => 'À savoir',
            'ANoter' => 'À noter',
            'Avertissement' => 'Important',
            'Exemple' => 'Exemple',
            'Rappel' => 'Rappel',
        ];
        $title = $this->directTitle($node) ?: ($labels[$name] ?? 'Information');
        $body = $this->renderChildren($node, $level, true);

        if ($body === '') {
            return '';
        }

        return '<aside class="comarquage-note comarquage-note-' . strtolower($name) . '">'
            . '<strong class="comarquage-note-title">' . $this->escape($title) . '</strong>'
            . '<div class="comarquage-note-body">' . $body . '</div>'
            . '</aside>';
    }

    private function renderProblematiques(\DOMElement $node, int $level): string
    {
        $items = '';

        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement || ($child->localName ?: $child->nodeName) !== 'Problematique') {
                continue;
            }

            $items .= $this->renderDecisionNode($child, $level, []);
        }

        return $items !== ''
            ? '<section class="comarquage-guide comarquage-decision-tree" data-comarquage-guide><h' . max(2, min(4, $level)) . ' class="comarquage-section-title">Votre situation</h' . max(2, min(4, $level)) . '><div class="comarquage-guide-level" data-comarquage-guide-level="0">' . $items . '</div></section>'
            : '';
    }

    private function renderDecisionNode(\DOMElement $node, int $level, array $parentVars = []): string
    {
        $title = $this->cleanTitleText($this->directTitle($node));
        $vars = array_values(array_unique(array_merge($parentVars, $this->selectionVars($node))));
        $children = '';
        $resources = '';

        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = $child->localName ?: $child->nodeName;

            if (in_array($name, ['Titre', 'SiSelectionne', 'SiNonSelectionne', 'affecteVrai', 'affecteFaux'], true)) {
                continue;
            }

            if ($name === 'Branche' || $name === 'Problematique') {
                $children .= $this->renderDecisionNode($child, min(5, $level + 1), $vars);
                continue;
            }

            $resources .= $this->renderBlockWithGuideVars($child, min(5, $level + 1), $vars);
        }

        if ($title === '' && $children === '' && $resources === '') {
            return '';
        }

        if ($title === '') {
            return '<div class="comarquage-guide-node">' . $children . $resources . '</div>';
        }

        $next = $children !== '' ? '<div class="comarquage-guide-children" hidden>' . $children . '</div>' : '';
        $result = $resources !== '' ? '<div class="comarquage-guide-result" hidden>' . $resources . '</div>' : '';

        return '<div class="comarquage-guide-node" data-comarquage-guide-node>'
            . '<button type="button" class="comarquage-guide-choice" data-comarquage-guide-choice data-comarquage-vars="' . $this->escapeAttribute(implode(',', $vars)) . '">'
            . '<span>' . $this->escape($title) . '</span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i>'
            . '</button>'
            . $next
            . $result
            . '</div>';
    }

    private function renderRelatedSection(\DOMElement $node, string $fallbackTitle): string
    {
        $items = '';

        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $item = $this->renderResourceItem($child, '');

            if ($item !== '') {
                $items .= '<li>' . $item . '</li>';
            }
        }

        return $items !== '' ? '<section class="comarquage-related"><h3>' . $this->escape($fallbackTitle) . '</h3><ul>' . $items . '</ul></section>' : '';
    }

    private function renderResourceGroup(?\DOMNodeList $nodes, string $title): string
    {
        if (!$nodes || $nodes->length === 0) {
            return '';
        }

        $items = '';

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $item = $this->renderResourceItem($node, '');

            if ($item !== '') {
                $items .= '<li>' . $item . '</li>';
            }
        }

        return $items !== ''
            ? '<details class="comarquage-related comarquage-related-collapsible"><summary>' . $this->escape($title) . '</summary><ul>' . $items . '</ul></details>'
            : '';
    }

    private function renderResource(\DOMElement $node, string $fallbackTitle): string
    {
        $name = $node->localName ?: $node->nodeName;

        if (in_array($name, ['ServiceEnLigne', 'ServiceEnLigneAnnexe', 'RessourceWeb'], true) || $this->selfUrl($node) !== '') {
            $content = $this->renderResourceItem($node, $fallbackTitle);

            return $content !== '' ? '<div class="comarquage-resource">' . $content . '</div>' : '';
        }

        $sectionTitle = $this->directTitle($node) ?: $fallbackTitle;
        $items = '';

        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement || $this->isResourceMetadataNode($child)) {
                continue;
            }

            $item = $this->renderResourceItem($child, '');

            if ($item !== '') {
                $items .= '<li>' . $item . '</li>';
            }
        }

        if ($items !== '') {
            return '<section class="comarquage-resource comarquage-resource-section"><h3>'
                . $this->escape($sectionTitle)
                . '</h3><ul>' . $items . '</ul></section>';
        }

        $content = $this->renderResourceItem($node, $fallbackTitle);

        return $content !== '' ? '<div class="comarquage-resource">' . $content . '</div>' : '';
    }

    private function renderResourceItem(\DOMElement $node, string $fallbackTitle): string
    {
        $url = $this->firstUrl($node);
        $internalReference = $this->publicationReference($node);
        $title = $this->resourceTitle($node);

        if ($title === '' && $internalReference !== '') {
            $title = $this->internalReferenceLabel($node, $internalReference);
        }

        if ($title === '') {
            $title = $fallbackTitle;
        }

        $complement = $this->directChildText($node, 'Complement') ?: $this->directChildText($node, 'Texte');
        $source = $this->directChildText($node, 'Source');

        if ($title === '' && $complement === '' && $source === '') {
            return '';
        }

        if ($url !== '' && $title !== '') {
            $content = '<a href="' . $this->escapeAttribute($url) . '" rel="noopener noreferrer" target="_blank">' . $this->escape($title) . '</a>';
        } elseif ($internalReference !== '' && $title !== '') {
            $content = '<a href="' . $this->escapeAttribute($this->internalReferenceUrl($internalReference)) . '">' . $this->escape($title) . '</a>';
        } else {
            $content = $this->escape($title);
        }

        if ($complement !== '') {
            $content .= '<p>' . $this->escape($complement) . '</p>';
        }

        if ($source !== '') {
            $content .= '<small>' . $this->escape($source) . '</small>';
        }

        return $content;
    }

    private function renderBlockWithGuideVars(\DOMElement $node, int $level, array $vars): string
    {
        $html = $this->renderBlock($node, $level);

        if ($html === '' || $vars === []) {
            return $html;
        }

        $query = 'cq_vars=' . rawurlencode(implode(',', array_values(array_unique($vars))));

        return preg_replace_callback(
            '/href=("|\')([^"\']+)\1/u',
            static function (array $match) use ($query): string {
                $href = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $separator = str_contains($href, '?') ? '&' : '?';

                return 'href=' . $match[1] . htmlspecialchars($href . $separator . $query, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $match[1];
            },
            $html
        ) ?? $html;
    }

    private function renderGuidedSearchReference(\DOMElement $node): string
    {
        $title = $this->cleanTitleText($this->directTitle($node) ?: $this->nodeTitle($node));
        $reference = trim($node->getAttribute('ID') ?: $node->getAttribute('id'));

        if ($title === '') {
            $title = 'Personnaliser la fiche';
        }

        if (!$this->isPublicationReference($reference)) {
            return '<div class="comarquage-guided"><strong>' . $this->escape($title) . '</strong></div>';
        }

        return '<div class="comarquage-guided">'
            . '<strong>' . $this->escape($title) . '</strong>'
            . '<a class="comarquage-guided-link" href="' . $this->escapeAttribute($this->internalReferenceUrl($reference)) . '">Répondre aux questions</a>'
            . '</div>';
    }

    private function selectionVars(\DOMElement $node): array
    {
        $vars = [];

        foreach ($node->childNodes as $selection) {
            if (!$selection instanceof \DOMElement || ($selection->localName ?: $selection->nodeName) !== 'SiSelectionne') {
                continue;
            }

            foreach ($selection->childNodes as $child) {
                if (!$child instanceof \DOMElement || !in_array(($child->localName ?: $child->nodeName), ['affecteVrai', 'AffecteVrai'], true)) {
                    continue;
                }

                $var = trim($child->getAttribute('var'));

                if ($var !== '') {
                    $vars[] = $var;
                }
            }
        }

        return array_values(array_unique($vars));
    }

    private function conditionAttributes(\DOMElement $node): string
    {
        $vars = [];

        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement || ($child->localName ?: $child->nodeName) !== 'Condition') {
                continue;
            }

            foreach ($child->getElementsByTagName('*') as $condition) {
                if (!$condition instanceof \DOMElement || !in_array(($condition->localName ?: $condition->nodeName), ['estVrai', 'EstVrai'], true)) {
                    continue;
                }

                $var = trim($condition->getAttribute('var'));

                if ($var !== '') {
                    $vars[] = $var;
                }
            }
        }

        return $vars ? ' data-comarquage-condition="' . $this->escapeAttribute(implode(',', array_values(array_unique($vars)))) . '"' : '';
    }

    private function internalReferenceLabel(\DOMElement $node, string $reference): string
    {
        $comment = $this->cleanTitleText($node->getAttribute('commentaireLien'));

        if ($comment !== '') {
            return $comment;
        }

        if ($reference !== '' && !empty($this->referenceTitles[$reference])) {
            return $this->referenceTitles[$reference];
        }

        $type = mb_strtolower($this->cleanTitleText($node->getAttribute('type')));

        if (str_contains($type, 'question')) {
            return 'Voir la question associée';
        }

        if (str_contains($type, 'service')) {
            return 'Ouvrir le service associé';
        }

        if (str_contains($type, 'définition') || str_contains($type, 'definition') || str_contains($type, 'sigle')) {
            return 'Voir la définition';
        }

        return 'Voir la fiche recommandée';
    }

    private function collectReferenceTitles(\DOMXPath $xpath, \DOMNode $node): array
    {
        $titles = [];

        foreach ($xpath->query('.//*[@ID or @id]', $node) ?: [] as $candidate) {
            if (!$candidate instanceof \DOMElement) {
                continue;
            }

            $id = trim($candidate->getAttribute('ID') ?: $candidate->getAttribute('id'));

            if ($id === '' || !preg_match('/^[A-Z][A-Za-z0-9_-]+$/', $id)) {
                continue;
            }

            $text = $this->cleanTitleText($this->directTitle($candidate) ?: $this->nodeTitle($candidate));

            if ($text === '' || mb_strlen($text) > 220) {
                continue;
            }

            $titles[$id] = $text;
        }

        return $titles;
    }

    private function isResourceMetadataNode(\DOMElement $node): bool
    {
        return in_array($node->localName ?: $node->nodeName, ['Titre', 'TitreRiche', 'Source', 'Complement'], true);
    }

    private function resourceTitle(\DOMElement $node): string
    {
        foreach (['Titre', 'Nom', 'Intitule', 'Libelle', 'Libellé', 'Texte'] as $name) {
            $text = $this->directChildText($node, $name);

            if ($text !== '') {
                return $this->cleanTitleText($text);
            }
        }

        $text = $this->cleanTitleText($node->getAttribute('commentaireLien') ?: $this->nodeTitle($node));

        if ($text === '' && $this->publicationReference($node) !== '') {
            return '';
        }

        return mb_strlen($text) > 180 ? mb_substr($text, 0, 180) . '...' : $text;
    }

    private function renderTitledBlock(\DOMElement $node, string $fallbackTitle, int $level): string
    {
        $title = $this->directTitle($node) ?: $fallbackTitle;
        $body = $this->renderChildren($node, $level, true);

        if ($body === '') {
            $body = $this->escape($this->nodeTitle($node));
        }

        return $body !== ''
            ? '<section class="comarquage-titled-block"><h' . max(3, min(5, $level)) . '>' . $this->escape($title) . '</h' . max(3, min(5, $level)) . '><div>' . $body . '</div></section>'
            : '';
    }

    private function renderFloatingTitle(\DOMElement $node, int $level): string
    {
        $body = $this->renderChildren($node, $level);

        return $body !== '' ? '<aside class="comarquage-floating-title">' . $body . '</aside>' : '';
    }

    private function renderDefinition(\DOMElement $node, int $level): string
    {
        $title = $this->directTitle($node) ?: 'Définition';
        $body = $this->renderChildren($node, $level, true);

        if ($body === '') {
            return '';
        }

        return '<aside class="comarquage-definition"><strong class="comarquage-definition-title">'
            . $this->escape($title)
            . '</strong><div class="comarquage-definition-body">' . $body . '</div></aside>';
    }

    private function renderSource(\DOMElement $node): string
    {
        $text = $this->nodeTitle($node);

        return $text !== '' ? '<small class="comarquage-source">' . $this->escape($text) . '</small>' : '';
    }

    private function renderUnknownBlock(\DOMElement $node, int $level): string
    {
        $hasElementChildren = false;

        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $hasElementChildren = true;
                break;
            }
        }

        if ($hasElementChildren) {
            return $this->renderChildren($node, $level);
        }

        $text = trim((string) $node->textContent);

        return $text !== '' ? '<p>' . $this->escape($text) . '</p>' : '';
    }

    private function renderExternalLink(\DOMElement $node, string $content): string
    {
        $url = $node->getAttribute('URL') ?: $node->getAttribute('href');

        if ($content === '' || !$this->isAllowedUrl($url)) {
            return $content;
        }

        return '<a href="' . $this->escapeAttribute($url) . '" rel="noopener noreferrer" target="_blank">' . $content . '</a>';
    }

    private function renderInternalReference(\DOMElement $node, string $content): string
    {
        if ($content === '') {
            return '';
        }

        $reference = $node->getAttribute('LienPublication') ?: $node->getAttribute('LienID');

        if ($this->isPublicationReference($reference)) {
            return '<a class="comarquage-inline-reference" href="' . $this->escapeAttribute($this->internalReferenceUrl($reference)) . '">' . $content . '</a>';
        }

        return '<span class="comarquage-inline-reference" data-reference="' . $this->escapeAttribute($reference) . '">' . $content . '</span>';
    }

    private function publicationReference(\DOMElement $node): string
    {
        foreach (['ID', 'id', 'LienPublication', 'LienID'] as $attribute) {
            $value = trim($node->getAttribute($attribute));

            if ($this->isPublicationReference($value)) {
                return $value;
            }
        }

        return '';
    }

    private function isPublicationReference(string $reference): bool
    {
        return preg_match('/^[FN][A-Za-z0-9_-]+$/', trim($reference)) === 1;
    }

    private function internalReferenceUrl(string $reference): string
    {
        if (str_starts_with($reference, 'R') || $this->currentUniverse === 'associations') {
            $base = $this->currentUniverse === 'entreprises' ? 'https://entreprendre.service-public.gouv.fr/vosdroits/' : 'https://www.service-public.gouv.fr/particuliers/vosdroits/';
            return $base . rawurlencode($reference);
        }
        $view = $this->currentUniverse !== '' ? $this->currentUniverse : 'particuliers';

        return 'index.php?option=com_comarquage&view=' . rawurlencode($view) . '&id=' . rawurlencode($reference);
    }

    private function directTitle(\DOMElement $node): string
    {
        return $this->directChildText($node, 'Titre');
    }

    private function normalizeNodeName(string $name): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;

        return strtolower(preg_replace('/[^a-z0-9_-]/i', '', $normalized) ?? $normalized);
    }

    private function directChildText(\DOMElement $node, string $name): string
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && ($child->localName ?: $child->nodeName) === $name) {
                return $this->cleanTitleText($this->nodeTitle($child));
            }
        }

        return '';
    }

    private function nodeTitle(\DOMNode $node): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $node->textContent) ?? (string) $node->textContent);
    }

    private function cleanTitleText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        if (preg_match('/^\[(.+)\]$/u', $text, $matches)) {
            $text = trim($matches[1]);
        }

        return $text;
    }

    private function firstUrl(\DOMElement $node): string
    {
        $url = $this->selfUrl($node);

        if ($url !== '') {
            return $url;
        }

        foreach ($node->getElementsByTagName('*') as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            foreach (['URL', 'url', 'href', 'HREF'] as $attribute) {
                if ($this->isAllowedUrl($child->getAttribute($attribute))) {
                    return $child->getAttribute($attribute);
                }
            }

            if (in_array($child->localName ?: $child->nodeName, ['URL', 'Url', 'url'], true) && $this->isAllowedUrl(trim($child->textContent))) {
                return trim($child->textContent);
            }
        }

        return '';
    }

    private function selfUrl(\DOMElement $node): string
    {
        foreach (['URL', 'url', 'href', 'HREF'] as $attribute) {
            if ($this->isAllowedUrl($node->getAttribute($attribute))) {
                return $node->getAttribute($attribute);
            }
        }

        return '';
    }

    private function isAllowedUrl(string $url): bool
    {
        return preg_match('~^https?://~i', $url) === 1;
    }

    private function plainText(\DOMNode $node): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $node->textContent) ?? (string) $node->textContent);
    }

    private function wrap(string $tag, string $content, string $class): string
    {
        return $content !== '' ? '<' . $tag . ' class="' . $class . '">' . $content . '</' . $tag . '>' : '';
    }

    private function humanizeNodeName(string $name): string
    {
        $label = preg_replace('/(?<!^)([A-ZÉÈÀÂÊÎÔÛÇ])/', ' $1', $name) ?? $name;

        return trim($label) !== '' ? trim($label) : 'Ressource';
    }

    private function mergeAdjacentLists(string $html): string
    {
        $html = preg_replace('~</ul>\s*<ul class="comarquage-list comarquage-list-ul">~', '', $html) ?? $html;
        $html = preg_replace('~</ol>\s*<ol class="comarquage-list comarquage-list-ol">~', '', $html) ?? $html;

        return $html;
    }

    private function spaceFromEmptyInline(\DOMNode $node): string
    {
        return (string) $node->textContent !== '' && trim((string) $node->textContent) === '' ? ' ' : '';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
