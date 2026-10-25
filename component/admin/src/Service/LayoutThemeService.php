<?php
/**
 * Comarquage — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\Service;

defined('_JEXEC') or die;

class LayoutThemeService
{
    private string $dir;

    public function __construct()
    {
        $this->dir = JPATH_ROOT . '/media/com_comarquage/themes';
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
        }
        $this->ensureBuiltinThemes();
    }

    public function list(): array
    {
        $themes = [];
        foreach (glob($this->dir . '/*.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true) ?: [];
            $id = basename($file, '.json');
            $themes[$id] = [
                'id' => $id,
                'title' => (string) ($data['title'] ?? $id),
                'description' => (string) ($data['description'] ?? ''),
                'builtin' => !empty($data['builtin']),
            ];
        }
        ksort($themes);

        return $themes;
    }

    public function import(string $source): string
    {
        $data = json_decode((string) file_get_contents($source), true);
        if (!is_array($data) || empty($data['id']) || !isset($data['css'])) {
            throw new \RuntimeException('Fichier de thème invalide.');
        }

        $id = $this->sanitizeId((string) $data['id']);
        $data['id'] = $id;
        $data['builtin'] = false;
        $this->write($id, $data);

        return $id;
    }

    public function export(string $id): string
    {
        $id = $this->sanitizeId($id);
        $file = $this->dir . '/' . $id . '.json';
        if (!is_file($file)) {
            throw new \RuntimeException('Thème introuvable.');
        }

        return $file;
    }

    public function get(string $id): array
    {
        $id = $this->sanitizeId($id);
        $file = $this->dir . '/' . $id . '.json';
        if (!is_file($file)) {
            throw new \RuntimeException('Thème introuvable.');
        }

        return json_decode((string) file_get_contents($file), true) ?: [];
    }

    public function saveEditable(string $id, string $css, string $paramsJson, string $iconsJson): void
    {
        $id = $this->sanitizeId($id);
        $data = $this->get($id);
        if (!empty($data['builtin'])) {
            $data['builtin'] = false;
            $id .= '-custom';
            $data['id'] = $id;
            $data['title'] = ($data['title'] ?? $id) . ' personnalisé';
        }

        $params = json_decode($paramsJson, true);
        if (!is_array($params)) {
            throw new \RuntimeException('Paramètres JSON invalides.');
        }

        $icons = json_decode($iconsJson, true);
        if (!is_array($icons)) {
            throw new \RuntimeException('Icônes JSON invalides.');
        }

        $data['params'] = $params;
        $data['icons'] = $icons;
        $data['css'] = $css;
        $data['builtin'] = false;
        $this->write($id, $data, true);
    }

    public function delete(string $id): void    {
        $id = $this->sanitizeId($id);
        $json = $this->dir . '/' . $id . '.json';
        $css = $this->dir . '/' . $id . '.css';
        $data = is_file($json) ? json_decode((string) file_get_contents($json), true) : [];

        if (!empty($data['builtin'])) {
            throw new \RuntimeException('Un thème intégré ne peut pas être supprimé.');
        }

        if (is_file($json)) {
            unlink($json);
        }
        if (is_file($css)) {
            unlink($css);
        }
    }

    private function ensureBuiltinThemes(): void
    {
        $this->write('default', [
            'id' => 'default',
            'title' => 'Standard',
            'description' => 'Mise en page moderne par défaut.',
            'builtin' => true,
            'params' => ['radius' => '8px', 'density' => 'comfortable', 'accent' => '#006f8d'],
            'icons' => [],
            'css' => '',
        ], false);
        $this->write('compact', [
            'id' => 'compact',
            'title' => 'Compact',
            'description' => 'Espacements réduits pour les pages denses.',
            'builtin' => true,
            'params' => ['radius' => '4px', 'density' => 'compact', 'accent' => '#006f8d'],
            'icons' => ['Papiers' => 'fa-id-card', 'Famille' => 'fa-people-roof'],
            'css' => ".comarquage-theme-compact.comarquage{gap:1rem}.comarquage-theme-compact .comarquage-hero{padding:1.25rem}.comarquage-theme-compact .comarquage-topic{min-height:9rem}.comarquage-theme-compact .comarquage-card,.comarquage-theme-compact .comarquage-fiche{padding:1rem}\n",
        ], false);
        $this->write('signature', [
            'id' => 'signature',
            'title' => 'Signature',
            'description' => 'Grille éditoriale plus actuelle, accents colorés et icônes rondes.',
            'builtin' => true,
            'params' => ['radius' => '0px', 'density' => 'editorial', 'accent' => '#e94f37'],
            'icons' => [
                'Papiers' => 'fa-address-card',
                'Famille' => 'fa-children',
                'Social' => 'fa-hand-holding-heart',
                'Travail' => 'fa-user-tie',
                'Logement' => 'fa-building-user',
                'Transports' => 'fa-bus',
                'Argent' => 'fa-wallet',
                'Justice' => 'fa-gavel',
                'Etranger' => 'fa-earth-europe',
                'Loisirs' => 'fa-masks-theater',
                'Comment faire si' => 'fa-route',
                'Fiscalite' => 'fa-file-invoice-dollar',
                'Ressources humaines' => 'fa-people-arrows',
            ],
            'css' => ".comarquage-theme-signature.comarquage{--cq-blue:#145c73;--cq-blue-dark:#12242c;--cq-cyan:#e94f37;--cq-soft:#f5f1ea;--cq-line:#d8d0c3;--cq-shadow:0 20px 60px rgba(18,36,44,.14)}.comarquage-theme-signature .comarquage-hero{background:linear-gradient(120deg,#12242c 0%,#145c73 52%,#f5f1ea 52%,#fffaf2 100%);border-bottom:6px solid #e94f37}.comarquage-theme-signature .comarquage-topic-grid{grid-template-columns:repeat(4,minmax(0,1fr))}.comarquage-theme-signature .comarquage-topic{border:0;box-shadow:0 12px 30px rgba(18,36,44,.08);min-height:10.5rem}.comarquage-theme-signature .comarquage-topic-icon{background:#12242c;color:#fff;border-radius:999px}.comarquage-theme-signature .comarquage-featured{grid-template-columns:1.2fr 1fr 1fr}.comarquage-theme-signature .comarquage-featured article{border:0;border-top:5px solid var(--cq-cyan)}\n",
        ], false);
        $this->write('mairie', [
            'id' => 'mairie',
            'title' => 'Mairie',
            'description' => 'Palette plus institutionnelle et sobre.',
            'builtin' => true,
            'params' => ['radius' => '2px', 'density' => 'spacious', 'accent' => '#2f6f73'],
            'icons' => ['Papiers' => 'fa-id-card', 'Famille' => 'fa-people-roof', 'Justice' => 'fa-scale-balanced'],
            'css' => ".comarquage-theme-mairie.comarquage{--cq-blue:#2f6f73;--cq-blue-dark:#193f42;--cq-cyan:#8abf3f;--cq-soft:#f3f7f2}.comarquage-theme-mairie .comarquage-hero{background:linear-gradient(135deg,#193f42 0%,#2f6f73 62%,#f3f7f2 62%,#fff 100%)}\n",
        ], false);
    }

    private function write(string $id, array $data, bool $overwrite = true): void
    {
        $id = $this->sanitizeId($id);
        $json = $this->dir . '/' . $id . '.json';
        $css = $this->dir . '/' . $id . '.css';
        if (!$overwrite && is_file($json)) {
            return;
        }
        file_put_contents($json, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        file_put_contents($css, (string) ($data['css'] ?? ''));
    }

    private function sanitizeId(string $id): string
    {
        $id = strtolower(trim($id));
        $id = preg_replace('/[^a-z0-9_-]+/', '-', $id) ?: '';
        if ($id === '') {
            throw new \RuntimeException('Identifiant de thème invalide.');
        }

        return $id;
    }
}