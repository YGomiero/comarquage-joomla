<?php
/**
 * Resumable Open Data import. Batch and cursor commit in the same transaction.
 * Missing records are withdrawn only after a complete, valid snapshot.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\Service;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Http\HttpFactory;
use Joomla\Database\DatabaseInterface;

defined('_JEXEC') or die;

final class ImportService
{
    public function __construct(private DatabaseInterface $db) {}

    public function import(?string $requestedUniverse = null, array $options = []): array
    {
        $lock = new ImportLockService();
        if (!$lock->acquire()) {
            throw new \RuntimeException('An import is already running.');
        }
        $summary = ['items' => 0, 'categories' => 0, 'files' => 0, 'errors' => 0, 'interrupted' => 0, 'message' => ''];
        $params = ComponentHelper::getParams('com_comarquage');
        $logger = new ImportLogger($this->db);
        $repo = new RepositoryService($this->db);
        $storage = new SourceStorageService();
        $parser = new XmlParserService();
        $deadline = microtime(true) + max(5, min(1800, (int) ($options['max_runtime'] ?? $params->get('import_max_runtime', 240))));
        $limit = max(0, (int) ($options['max_files'] ?? $params->get('import_max_files', 10000)));
        try {
            foreach (['particuliers', 'associations', 'entreprises'] as $universe) {
                if (($requestedUniverse && $requestedUniverse !== $universe) || !(int) $params->get('enable_' . $universe, 1)) {
                    continue;
                }
                if (microtime(true) >= $deadline || ($limit && $summary['files'] >= $limit)) {
                    $summary['interrupted'] = 1;
                    break;
                }
                $source = trim((string) $params->get('feed_' . $universe, ''));
                $mode = (string) $params->get('fetch_mode', 'remote');
                $version = (string) $params->get('target_version', '3.5');
                $transaction = false;
                try {
                    if ($source === '') {
                        throw new \RuntimeException('No Open Data source configured.');
                    }
                    $state = $this->feed($universe);
                    $progress = json_decode((string) ($state['params'] ?? ''), true) ?: [];
                    $fingerprint = hash('sha256', 'parser-1.0.0-v2|' . $source . '|' . $mode . '|' . $version);
                    if (($progress['fingerprint'] ?? '') !== $fingerprint || empty($progress['files']) || !is_file($progress['files'][0])) {
                        if (!empty($progress['files'])) {
                            $storage->cleanup($progress['files'], $progress['download']['path'] ?? '');
                        }
                        $download = $this->download($source, $mode, $state, (int) $params->get('http_timeout', 60));
                        if ($download['unchanged']) {
                            $this->mark($universe, ['last_checked' => gmdate('Y-m-d H:i:s'), 'last_status' => 'unchanged', 'last_error' => '']);
                            continue;
                        }
                        $files = $storage->prepare($download['path']);
                        if (!$files) {
                            throw new \RuntimeException('The source contains no XML files.');
                        }
                        $progress = ['fingerprint' => $fingerprint, 'files' => $files, 'cursor' => 0, 'items' => [], 'categories' => [], 'download' => $download];
                    }
                    $this->db->transactionStart();
                    $transaction = true;
                    while ($progress['cursor'] < count($progress['files'])) {
                        if (microtime(true) >= $deadline || ($limit && $summary['files'] >= $limit)) {
                            $summary['interrupted'] = 1;
                            break;
                        }
                        $file = $progress['files'][$progress['cursor']];
                        $result = $parser->parseFile($file, $universe, $version, static function ($level, $data): void {
                            if ($level === 'error') {
                                throw new \RuntimeException('XML import failed: ' . ($data['message'] ?? 'Invalid document'));
                            }
                        });
                        $categories = [];
                        foreach ($result['categories'] as $category) {
                            $id = $repo->upsertCategory($universe, $category);
                            $categories[$category['external_id']] = $id;
                            $progress['categories'][$id] = $id;
                            $summary['categories']++;
                        }
                        $items = [];
                        foreach ($result['items'] as $item) {
                            $id = $repo->upsertItem($universe, $version, $item);
                            $items[$item['external_id']] = $id;
                            $progress['items'][$id] = $id;
                            $this->db->setQuery('DELETE FROM #__comarquage_item_category_map WHERE item_id = ' . (int) $id)->execute();
                            $summary['items']++;
                        }
                        foreach ($result['relations'] as $relation) {
                            $repo->mapItemCategory($items[$relation['item_external_id']] ?? 0, $categories[$relation['category_external_id']] ?? 0);
                        }
                        $progress['cursor']++;
                        $summary['files']++;
                    }
                    $complete = $progress['cursor'] === count($progress['files']);
                    $values = ['source_url' => $source, 'source_type' => $mode, 'target_version' => $version, 'last_checked' => gmdate('Y-m-d H:i:s'), 'last_status' => $complete ? 'ok' : 'partial', 'last_error' => ''];
                    if ($complete) {
                        if (!$progress['items']) {
                            throw new \RuntimeException('No publications found: existing content has been preserved.');
                        }
                        $this->withdrawMissing($universe, $progress['items'], $progress['categories']);
                        $d = $progress['download'];
                        $provenance = $d;
                        unset($provenance['path']);
                        $values += ['source_etag' => $d['etag'], 'source_last_modified' => $d['last_modified'], 'source_hash' => $d['hash'], 'last_downloaded' => $d['downloaded'], 'last_imported' => gmdate('Y-m-d H:i:s'), 'params' => json_encode(['provenance' => $provenance])];
                    } else {
                        $values['params'] = json_encode($progress, JSON_UNESCAPED_SLASHES);
                    }
                    $this->mark($universe, $values);
                    $this->db->transactionCommit();
                    $transaction = false;
                    if ($complete) {
                        $storage->cleanup($progress['files'], $progress['download']['path']);
                    }
                } catch (\Throwable $e) {
                    if ($transaction) { $this->db->transactionRollback(); }
                    $summary['errors']++;
                    $summary['message'] = $e->getMessage();
                    $this->mark($universe, ['last_status' => 'error', 'last_error' => $e->getMessage(), 'last_checked' => gmdate('Y-m-d H:i:s')]);
                    $logger->log('import.' . $universe, 'error', ['message' => $e->getMessage()]);
                }
            }
            try { (new LocalContextService($this->db))->refresh(); }
            catch (\Throwable $e) { $logger->log('local_context', 'warning', ['message' => $e->getMessage()]); }
            $logger->log('import', 'summary', $summary);
            (new CacheService())->clear();
            return $summary;
        } finally { $lock->release(); }
    }

    /** Current DILA addresses; the legacy associations archive is frozen. */
    public static function sourceUrl(string $source): string
    {
        $source = str_replace('lecomarquage.service-public.fr', 'lecomarquage.service-public.gouv.fr', $source);
        if (str_contains($source, 'data.gouv.fr/') && str_contains($source, '/datasets/')) {
            if (preg_match('~(particuliers|associations)~i', $source)) {
                return 'https://lecomarquage.service-public.gouv.fr/vdd/3.5/part/zip/vosdroits-latest.zip';
            }
            if (preg_match('~(professionnels|entreprendre)~i', $source)) {
                return 'https://lecomarquage.service-public.gouv.fr/vdd/3.5/pro/zip/vosdroits-latest.zip';
            }
        }
        return str_replace('/vdd/3.5/asso/', '/vdd/3.5/part/', $source);
    }

    private function download(string $source, string $mode, array $state, int $timeout): array
    {
        $url = $mode === 'local' ? $source : self::sourceUrl($source);
        $provenance = json_decode((string) ($state['params'] ?? ''), true)['provenance'] ?? [];
        $sameSource = ($provenance['parser'] ?? '') === '1.0.0-v2' && $source === ($state['source_url'] ?? '') && $mode === ($state['source_type'] ?? '') && ($provenance['url'] ?? '') === ($mode === 'remote' ? $url : '') && in_array($state['last_status'] ?? '', ['ok', 'unchanged'], true);
        $result = ['parser' => '1.0.0-v2', 'path' => '', 'url' => $mode === 'remote' ? $url : '', 'filename' => basename(parse_url($url, PHP_URL_PATH) ?: $url), 'etag' => '', 'last_modified' => '', 'hash' => '', 'downloaded' => gmdate('Y-m-d H:i:s'), 'unchanged' => false];
        if ($mode === 'local') {
            if (!is_file($source) && !is_dir($source)) { throw new \RuntimeException('Local source not found.'); }
            $result['path'] = $source;
            $result['hash'] = is_file($source) ? sha1_file($source) : '';
            $result['last_modified'] = gmdate('D, d M Y H:i:s', filemtime($source)) . ' GMT';
        } else {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !in_array($host, ['lecomarquage.service-public.gouv.fr', 'echanges.dila.gouv.fr', 'www.data.gouv.fr', 'data.gouv.fr'], true) || parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_PORT)) {
                throw new \RuntimeException('Only official HTTPS DILA/data.gouv.fr sources are supported.');
            }
            $headers = [];
            if ($sameSource && !empty($state['source_etag'])) { $headers['If-None-Match'] = $state['source_etag']; }
            $response = HttpFactory::getHttp()->get($url, $headers, max(5, min(120, $timeout)));
            if ((int) $response->code === 304 && $sameSource) {
                $result['unchanged'] = true;
                return $result;
            }
            if ((int) $response->code !== 200 || strlen((string) $response->body) > 128 * 1024 * 1024) {
                throw new \RuntimeException('Open Data download failed or exceeded 128 MiB (HTTP ' . (int) $response->code . ').');
            }
            foreach ($response->headers as $name => $value) {
                $value = is_array($value) ? end($value) : $value;
                if (strtolower((string) $name) === 'etag') { $result['etag'] = (string) $value; }
                if (strtolower((string) $name) === 'last-modified') { $result['last_modified'] = (string) $value; }
            }
            $result['hash'] = sha1((string) $response->body);
            $result['path'] = SourceStorageService::workspace() . '/source_' . bin2hex(random_bytes(8)) . '.zip';
            if (file_put_contents($result['path'], $response->body) === false) { throw new \RuntimeException('Cannot save the downloaded archive.'); }
        }
        $result['unchanged'] = $sameSource && $result['hash'] !== '' && hash_equals((string) ($state['source_hash'] ?? ''), $result['hash']);
        if ($result['unchanged'] && $mode !== 'local') { unlink($result['path']); }
        return $result;
    }

    private function feed(string $universe): array
    {
        return $this->db->setQuery('SELECT * FROM #__comarquage_feeds WHERE universe = ' . $this->db->quote($universe))->loadAssoc() ?: [];
    }

    private function mark(string $universe, array $values): void
    {
        $object = (object) (['universe' => $universe] + $values);
        $this->db->updateObject('#__comarquage_feeds', $object, 'universe');
    }

    private function withdrawMissing(string $universe, array $items, array $categories): void
    {
        foreach (['items' => $items, 'categories' => $categories] as $table => $ids) {
            $condition = $ids ? ' AND id NOT IN (' . implode(',', array_map('intval', $ids)) . ')' : '';
            $this->db->setQuery('UPDATE #__comarquage_' . $table . ' SET state = 0 WHERE universe = ' . $this->db->quote($universe) . $condition)->execute();
        }
        $this->db->setQuery('DELETE s FROM #__comarquage_search_index AS s INNER JOIN #__comarquage_items AS i ON i.id=s.item_id WHERE i.state=0')->execute();
        $this->db->setQuery("UPDATE #__finder_links AS f INNER JOIN #__comarquage_items AS i ON f.url=CONCAT('index.php?option=com_comarquage&view=fiche&id=',i.id) SET f.state=0 WHERE i.state=0")->execute();
    }
}
