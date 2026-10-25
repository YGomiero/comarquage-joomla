<?php
/**
 * Distro — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Site\Service;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

defined('_JEXEC') or die;

class ContentRendererService
{
    private const QUESTION_START = "(?:Qui|Que|Quoi|Quand|Comment|Combien|Pourquoi|Dans quels cas|Quel|Quelle|Quels|Quelles|Peut-on|Faut-il|L['’][\\p{L}]+)";

    public function title(string $title): string
    {
        $title = trim($this->cleanText($title));

        if (preg_match('/^\[(.+)\]$/u', $title, $matches)) {
            return trim($matches[1]);
        }

        return $title;
    }

    public function excerpt(string $content, int $length = 260): string
    {
        $content = trim($this->cleanText(strip_tags($content)));

        if (mb_strlen($content) <= $length) {
            return $content;
        }

        return rtrim(mb_substr($content, 0, $length - 1)) . '...';
    }

    public function excerptForItem(array|object $item, int $length = 260): string
    {
        $item = (array) $item;
        $content = (string) ($item['introtext'] ?? $item['metadesc'] ?? $item['body'] ?? '');

        return $this->excerpt($content, $length);
    }

    public function renderItem(array|object $item): string
    {
        $item = (array) $item;
        $structuredHtml = $this->structuredHtmlFromPayload((string) ($item['payload'] ?? ''));

        if ($structuredHtml !== '') {
            return $structuredHtml;
        }

        $body = trim((string) ($item['body'] ?? $item['introtext'] ?? ''));

        if ($body === '') {
            return '';
        }

        $title = (string) ($item['title'] ?? '');
        $introText = $this->cleanText((string) ($item['introtext'] ?? ''));
        $body = $this->stripTechnicalLead($body, $title);
        $body = $this->stripDuplicatedLeadTitle($body, $title);

        $blocks = $this->extractBlocks($body, $title);
        $blocks = $this->focusBlocksOnIntro($blocks, $introText);
        $blocks = $this->cleanBlocks($blocks);

        if (!$blocks && $introText === '') {
            return '';
        }

        $intro = $introText !== '' ? $introText : (array_shift($blocks) ?: '');

        while ($blocks && $this->similarText($blocks[0], $intro)) {
            array_shift($blocks);
        }

        $blocks = $this->cleanBlocks($blocks);
        $blocks = $this->splitLeadBlocks($blocks);
        $blocks = $this->removeIntroFragments($blocks, $intro);
        $blocks = $this->cleanBlocks($blocks);
        $blocks = $this->splitLeadBlocks($blocks);
        $blocks = $this->removeIntroFragments($blocks, $intro);
        $blocks = $this->cleanBlocks($blocks);
        $sections = $this->buildSections($blocks);

        $html = '<div class="comarquage-fiche-intro">' . $this->paragraphs([$intro]) . '</div>';

        if (!$sections) {
            return $html;
        }

        $html .= '<div class="comarquage-accordions">';

        foreach ($sections as $index => $section) {
            $sectionTitle = $this->title($section['title'] ?: ($index === 0 ? 'A retenir' : ('Informations ' . ($index + 1))));

            if (!$section['blocks']) {
                continue;
            }

            $html .= '<details class="comarquage-accordion">';
            $html .= '<summary><span>' . htmlspecialchars($sectionTitle, ENT_QUOTES, 'UTF-8') . '</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>';
            $html .= '<div class="comarquage-accordion-body">' . $this->paragraphs($section['blocks']) . '</div>';
            $html .= '</details>';
        }

        $html .= '</div>';

        return $html;
    }

    private function structuredHtmlFromPayload(string $payload): string
    {
        if ($payload === '') {
            return '';
        }

        $decoded = json_decode($payload, true);

        if (!is_array($decoded)) {
            return '';
        }

        $html = trim((string) ($decoded['structured_html'] ?? ''));

        if ($html === '') {
            return '';
        }

        return $this->sanitizeStructuredHtml($html);
    }

    private function sanitizeStructuredHtml(string $html): string
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="distro-content-root">' . $html . '</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $allowed = ['div','section','details','summary','button','span','i','p','strong','em','var','q','sup','sub','abbr','ul','ol','li','aside','h2','h3','h4','h5','a','small','table','caption','tbody','thead','tr','th','td','br'];
        $nodes = iterator_to_array($dom->getElementsByTagName('*'));
        foreach ($nodes as $node) {
            if (in_array($node->tagName, ['html', 'body'], true)) { continue; }
            if (!in_array($node->tagName, $allowed, true)) {
                $node->parentNode?->removeChild($node);
                continue;
            }
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = $attribute->value;
                $keep = in_array($name, ['class','id','title','role','type','open','colspan','rowspan','scope','target','rel'], true) || str_starts_with($name, 'aria-') || str_starts_with($name, 'data-comarquage-');
                if ($name === 'href') {
                    $value = preg_replace('/[\x00-\x20\x7f]+/', '', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    $keep = preg_match('~^(https?://|mailto:|tel:|#|index\.php(?:\?|$)|/(?!/))~i', $value) === 1;
                    if ($keep) { $node->setAttribute('href', $value); }
                }
                if (!$keep) { $node->removeAttribute($name); }
            }
        }
        $html = '';
        $root = $dom->getElementById('distro-content-root');
        if ($root) {
            foreach ($root->childNodes as $child) { $html .= $dom->saveHTML($child); }
        }

        return $this->rewriteInternalLinks($html);
    }

    private function rewriteInternalLinks(string $html): string
    {
        $references = [];

        if (!preg_match_all('/href=(["\'])([^"\']*\\bid=)(F[A-Za-z0-9_-]+)([^"\']*)\1/u', $html, $matches)) {
            return $html;
        }

        foreach ($matches[3] as $reference) {
            $references[$reference] = $reference;
        }

        $aliases = $this->aliasesForExternalIds(array_values($references));

        if (!$aliases) {
            return $html;
        }

        return preg_replace_callback(
            '/href=(["\'])([^"\']*\\bid=)(F[A-Za-z0-9_-]+)([^"\']*)\1/u',
            static function (array $match) use ($aliases): string {
                $alias = $aliases[$match[3]] ?? '';

                if ($alias === '') {
                    return $match[0];
                }

                return 'href=' . $match[1] . $match[2] . htmlspecialchars($alias, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $match[4] . $match[1];
            },
            $html
        ) ?? $html;
    }

    private function aliasesForExternalIds(array $externalIds): array
    {
        $externalIds = array_values(array_unique(array_filter($externalIds)));

        if (!$externalIds) {
            return [];
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $quoted = array_map([$db, 'quote'], $externalIds);
            $query = $db->getQuery(true)
                ->select($db->quoteName(['external_id', 'alias']))
                ->from($db->quoteName('#__comarquage_items'))
                ->where($db->quoteName('external_id') . ' IN (' . implode(',', $quoted) . ')')
                ->where($db->quoteName('alias') . ' <> ' . $db->quote(''));

            $rows = $db->setQuery($query)->loadAssocList('external_id') ?: [];
        } catch (\Throwable) {
            return [];
        }

        $aliases = [];

        foreach ($rows as $externalId => $row) {
            $aliases[$externalId] = (string) ($row['alias'] ?? '');
        }

        return $aliases;
    }

    private function extractBlocks(string $body, string $title): array
    {
        $text = preg_replace('~<(br|/p|/div|/li|/h[1-6]|/section|/article|/Titre|/SousTitre|/Paragraphe|/Chapitre|/BlocCas|/Liste|/Item)\b[^>]*>~iu', "\n", $body) ?? $body;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = $this->segmentConcatenatedText($text);
        $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{2,}/u', "\n", $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), static fn ($line) => $line !== ''));

        if (count($lines) <= 2) {
            $lines = $this->splitLongText($text);
        }

        $cleanTitle = $this->cleanText($title);
        $blocks = [];

        foreach ($lines as $line) {
            $line = $this->cleanText($line);

            if ($line === '' || ($cleanTitle !== '' && $line === $cleanTitle)) {
                continue;
            }

            $blocks[] = $line;
        }

        return $blocks;
    }

    private function segmentConcatenatedText(string $text): string
    {
        $patterns = [
            '/(?<!^)(?=(?:' . self::QUESTION_START . ')[^?]{8,180}\?)/u',
            '/(?<!^)(?=\b(?:Attention|A savoir|À savoir|A noter|À noter|Exemple|Cas général|Cas general)\b)/u',
            '/(?<!^)(?=\b(?:Appartement|Maison|Avant l\'achat|Choix de|Contrat préalable|Contrat prealable|Signature du|Assurances|Au plus tôt|Au plus tot|Dans les|Aide ou prêt|Aide ou pret|Crédit d\'impôt|Credit d\'impot|Renégociation|Renegociation)\b)/u',
            '/(?<!^)(?=\b(?:Lors de|Quand le|Comment fixer|Comment réviser|Comment reviser|Révision triennale|Revision triennale|Clause d\'échelle|Clause d\'echelle|Clause-recette|Saisir la|Pour saisir)\b)/u',
            '/(?<!^)(?=\b(?:Préparer la|Preparer la|La déclaration doit|La declaration doit|Copie du|Exemplaire des|Liste des|Lorsque la démarche|Lorsque la demarche|Faire la déclaration|Faire la declaration|Après la déclaration|Apres la declaration)\b)/u',
            '/(?<!^)(?=\b(?:Vous |Nous vous |Je |Si |Soit,|Code de|Tribunal judiciaire|Ministère chargé|Ministere charge|Assurance Banque|Après un accident|Apres un accident)\b)/u',
            '/(?<!^)(?=\b(?:Direction de l\'information|Service Public|Entreprendre Service Public|modified |Fiche pratique|Question-réponse|Question-reponse|Particuliers|Professionnels|Associations|Accueil particuliers|Accueil professionnels|Accueil associations)\b)/u',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, "\n", $text) ?? $text;
        }

        return $text;
    }

    private function splitLongText(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+(?=[\p{Lu}0-9][^.!?]{6,120}(?:[.!?]|$))/u', $text) ?: [$text];

        return array_values(array_filter(array_map('trim', $parts), static fn ($part) => $part !== ''));
    }

    private function cleanBlocks(array $blocks): array
    {
        $blocks = array_values(array_filter(array_map(fn ($block) => $this->cleanText((string) $block), $blocks), fn ($block) => $block !== ''));
        $blocks = array_values(array_filter($blocks, fn ($block) => !$this->isTechnicalBlock($block) && !$this->isNavigationBlock($block)));

        while ($blocks && !$this->looksLikeContentLead($blocks[0]) && !$this->isShortStructuralHeading($blocks[0]) && !$this->isQuestionHeading($blocks[0])) {
            array_shift($blocks);
        }

        return array_values($blocks);
    }

    private function focusBlocksOnIntro(array $blocks, string $introText): array
    {
        $introNorm = $this->normalize($introText);

        if ($introNorm === '') {
            return $blocks;
        }

        $needle = mb_substr($introNorm, 0, min(90, mb_strlen($introNorm)));

        foreach ($blocks as $index => $block) {
            $blockNorm = $this->normalize((string) $block);

            if ($blockNorm === $introNorm || str_contains($blockNorm, $needle)) {
                $focused = array_slice($blocks, $index);
                $focused[0] = $introText;
                $tail = $this->tailAfterIntro((string) $block, $introText);

                if ($tail !== '') {
                    array_splice($focused, 1, 0, [$tail]);
                }

                return array_values($focused);
            }
        }

        return $blocks;
    }

    private function removeIntroFragments(array $blocks, string $intro): array
    {
        return array_values(array_filter($blocks, fn ($block) => !$this->similarText((string) $block, $intro)));
    }

    private function tailAfterIntro(string $block, string $intro): string
    {
        $block = $this->cleanText($block);
        $intro = $this->cleanText($intro);

        if ($block === '' || $intro === '') {
            return '';
        }

        $position = mb_stripos($block, $intro);

        if ($position === false) {
            return '';
        }

        return trim(mb_substr($block, $position + mb_strlen($intro)));
    }

    private function splitLeadBlocks(array $blocks): array
    {
        $result = [];

        foreach ($blocks as $block) {
            $block = $this->cleanText((string) $block);

            $segmented = array_values(array_filter(array_map('trim', explode("\n", $this->segmentConcatenatedText($block))), static fn ($part) => $part !== ''));

            if (count($segmented) > 1) {
                foreach ($this->splitLeadBlocks($segmented) as $part) {
                    $result[] = $part;
                }

                continue;
            }

            if (preg_match('/^(Attention|A savoir|À savoir|A noter|À noter|Exemple)\s*(.+)$/iu', $block, $matches)) {
                $result[] = trim($matches[1]);
                $tail = trim($matches[2]);

                if ($tail !== '') {
                    $result[] = $tail;
                }

                continue;
            }

            if (preg_match('/^(.+?\.)\s*((?:' . self::QUESTION_START . ')[^?]{8,180}\?)\s*(.+)$/iu', $block, $matches)) {
                $result[] = trim($matches[1]);
                $result[] = trim($matches[2]);
                $tail = trim($matches[3]);

                if ($tail !== '') {
                    $result[] = $tail;
                }

                continue;
            }

            if (preg_match('/^((?:' . self::QUESTION_START . ')[^?]{8,180}\?)\s*(.+)$/iu', $block, $matches)) {
                $result[] = trim($matches[1]);
                $tail = trim($matches[2]);

                if ($tail !== '') {
                    $result[] = $tail;
                }

                continue;
            }

            $result[] = $block;
        }

        return array_values(array_filter($result, fn ($block) => $this->cleanText((string) $block) !== ''));
    }

    private function buildSections(array $blocks): array
    {
        $sections = [];
        $current = ['title' => '', 'blocks' => []];

        foreach ($blocks as $block) {
            if ($this->isHeading($block)) {
                if ($current['title'] !== '' || $current['blocks']) {
                    $sections[] = $current;
                }

                $current = ['title' => $block, 'blocks' => []];
                continue;
            }

            $current['blocks'][] = $block;
        }

        if ($current['title'] !== '' || $current['blocks']) {
            $sections[] = $current;
        }

        if (count($sections) <= 1 && count($blocks) > 3) {
            $sections = [];
            $labels = ['A retenir', 'Demarches et conditions', 'Services et references', 'Pour aller plus loin'];

            foreach (array_chunk($blocks, 4) as $index => $chunk) {
                $sections[] = [
                    'title' => $labels[$index] ?? ('Suite ' . ($index + 1)),
                    'blocks' => $chunk,
                ];
            }
        }

        return $sections;
    }

    private function isHeading(string $text): bool
    {
        $text = trim($text);

        if ($text === '') {
            return false;
        }

        if ($this->isQuestionHeading($text)) {
            return true;
        }

        if (mb_strlen($text) > 120 || preg_match('/[.;:]$/u', $text)) {
            return false;
        }

        if (preg_match('/^(Vous|Je|Nous|Si|Soit|de|du|des|et)\b/iu', $text)) {
            return false;
        }

        return $this->isShortStructuralHeading($text) || (bool) preg_match('/^(Attention|A savoir|À savoir|A noter|À noter|Exemple)$/iu', $text);
    }

    private function isQuestionHeading(string $text): bool
    {
        return mb_strlen($text) <= 180
            && (bool) preg_match('/^(?:' . self::QUESTION_START . ')[^?]{8,180}\?$/iu', trim($text));
    }

    private function isShortStructuralHeading(string $block): bool
    {
        $block = $this->cleanText($block);

        if ($block === '' || mb_strlen($block) > 70 || preg_match('/[.!?:;]/u', $block)) {
            return false;
        }

        if (preg_match('/^(Vous|Je|Nous|Si|Soit|de|du|des|et)\b/iu', $block)) {
            return false;
        }

        return (bool) preg_match('/^[\p{Lu}0-9]/u', $block);
    }

    private function looksLikeContentLead(string $block): bool
    {
        $block = $this->cleanText($block);

        return mb_strlen($block) >= 45 && (bool) preg_match('/[.!?]/u', $block);
    }

    private function isTechnicalBlock(string $block): bool
    {
        $block = $this->cleanText($block);
        $norm = $this->normalize($block);

        if ($block === '') {
            return true;
        }

        if (str_contains($norm, 'direction de l information')
            || str_contains($norm, 'premier ministre')
            || str_contains($norm, 'service-public.fr')
            || str_contains($norm, 'service-public.gouv.fr/a-propos/mentions-legales')
            || str_contains($norm, 'text/xml')
            || preg_match('/^modified\s+\d{4}-\d{2}-\d{2}/', $norm)
            || preg_match('/^https?:\/\//', $norm)
            || preg_match('/^fr(ispartof)?\s+/', $norm)
            || in_array($norm, ['france entiere', 'particuliers', 'professionnels', 'associations', 'fiche pratique', 'question-reponse', 'comment faire si...'], true)
            || preg_match('/^accueil\s+(particuliers|professionnels|associations)/', $norm)
            || str_starts_with($norm, 'particulierswww.service-public.gouv.fr')
            || str_starts_with($norm, 'professionnelswww.service-public.gouv.fr')
            || str_starts_with($norm, 'associationswww.service-public.gouv.fr')
            || str_starts_with($norm, 'assurance banque') && str_contains($norm, 'info service')
        ) {
            return true;
        }

        return false;
    }

    private function isNavigationBlock(string $block): bool
    {
        $norm = $this->normalize($block);
        $starts = [
            'argent - impots - consommation',
            'assurance automobile',
            'souscription et vie du contrat',
            'souscription du contrat',
            'assurance obligatoire',
            'assurances facultatives',
            'modification du contrat',
            'resiliation du contrat',
            'recours et litiges',
            'primetarifs',
            'tarifs cotisations',
            'bonus-malus',
            'jeunes conducteurs',
            'sinistreaccident',
            'accident et constat',
            'vol du vehicule',
            'indemnisation du sinistre',
            'degats materiels',
            'dommages corporels',
            'accident de la route',
            'formalites administratives d une association',
            'associations fondations et fonds de dotation',
            'creation d une association',
            'redaction des statuts',
            'declaration initiale',
            'identification et immatriculation',
            'agrement d une association',
        ];

        foreach ($starts as $start) {
            if (str_starts_with($norm, $start)) {
                return true;
            }
        }

        $navHits = 0;
        foreach (['modification du contrat', 'resiliation du contrat', 'recours et litiges', 'bonus-malus', 'sinistre', 'accident de la route'] as $needle) {
            if (str_contains($norm, $needle)) {
                $navHits++;
            }
        }

        return $navHits >= 2 && !preg_match('/[.!?]/u', $block);
    }

    private function stripTechnicalLead(string $body, string $title): string
    {
        $plain = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cleanTitle = $this->cleanText($title);

        if ($cleanTitle === '') {
            return $body;
        }

        $position = mb_strripos($plain, $cleanTitle);

        if ($position === false) {
            return $body;
        }

        return trim(mb_substr($plain, $position + mb_strlen($cleanTitle)));
    }

    private function similarText(string $left, string $right): bool
    {
        $left = mb_strtolower($this->cleanText($left));
        $right = mb_strtolower($this->cleanText($right));

        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right || str_starts_with($left, mb_substr($right, 0, min(80, mb_strlen($right))))) {
            return true;
        }

        return mb_strlen($left) > 15 && str_contains($right, $left);
    }

    private function paragraphs(array $blocks): string
    {
        $html = '';

        foreach ($blocks as $block) {
            $block = $this->cleanText((string) $block);

            if ($block === '') {
                continue;
            }

            $html .= '<p>' . htmlspecialchars($block, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        return $html;
    }

    private function stripDuplicatedLeadTitle(string $body, string $title): string
    {
        $plainTitle = preg_quote($this->cleanText($title), '/');

        if ($plainTitle === '') {
            return $body;
        }

        return preg_replace('/^\s*' . $plainTitle . '\s*/iu', '', $body, 1) ?? $body;
    }

    private function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function normalize(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = $ascii !== false ? $ascii : $text;
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9? .:\/-]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }
}
