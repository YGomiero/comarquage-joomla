<?php
/**
 * Optional local directory snapshot. INSEE identifies a municipality, not a postal zone.
 * These are services located in the commune, not a determination of jurisdiction.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\Service;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Http\HttpFactory;
use Joomla\Database\DatabaseInterface;

defined('_JEXEC') or die;

final class LocalContextService
{
    public const ENDPOINT = 'https://api-lannuaire.service-public.gouv.fr/api/explore/v2.1/catalog/datasets/api-lannuaire-administration/records';

    public function __construct(private DatabaseInterface $db) {}

    public function refresh(bool $force = false): int
    {
        $params = ComponentHelper::getParams('com_comarquage');
        $code = strtoupper(trim((string) $params->get('code_insee', '')));
        if (!(int) $params->get('activer_personnalisation_locale', 0)) { return 0; }
        if (!(new CommuneValidator())->isValidFormat($code)) { throw new \RuntimeException('Invalid INSEE municipality code.'); }
        $saved = $this->db->setQuery('SELECT payload FROM #__comarquage_local_organisations WHERE state=1 AND code_insee=' . $this->db->quote($code))->loadColumn();
        $stamp = json_decode($saved[0] ?? '', true)['retrieved'] ?? '';
        if (!$force && $stamp && strtotime($stamp) > time() - 86400) { return count($saved); }
        $records = [];
        for ($offset = 0; $offset < 1000; $offset += 100) {
            $url = self::ENDPOINT . '?' . http_build_query([
                'where' => 'code_insee_commune="' . $code . '"',
                'select' => 'id,nom,pivot,code_insee_commune,url_service_public,date_modification',
                'order_by' => 'id', 'limit' => 100, 'offset' => $offset,
            ], '', '&', PHP_QUERY_RFC3986);
            $response = HttpFactory::getHttp()->get($url, [], 15);
            if ((int) $response->code !== 200 || strlen((string) $response->body) > 4 * 1024 * 1024) {
                throw new \RuntimeException('The official local directory is temporarily unavailable. Previous contacts are preserved.');
            }
            $data = json_decode((string) $response->body, true, 64, JSON_THROW_ON_ERROR);
            if (!isset($data['results'], $data['total_count']) || $data['total_count'] > 1000) { throw new \RuntimeException('Incomplete local directory response.'); }
            foreach ($data['results'] as $record) {
                $normal = self::normalise($record, $code);
                if ($normal) { $records[] = $normal; }
            }
            if ($offset + 100 >= $data['total_count']) { break; }
            if (!$data['results']) { throw new \RuntimeException('Incomplete local directory page.'); }
        }
        if (!$records) { throw new \RuntimeException('No local services found for this INSEE code. Verify the code; previous contacts are preserved.'); }
        $this->db->transactionStart();
        try {
            $this->db->setQuery('DELETE FROM #__comarquage_local_organisations WHERE code_insee=' . $this->db->quote($code))->execute();
            foreach ($records as $record) {
                $object = (object) $record;
                $this->db->insertObject('#__comarquage_local_organisations', $object);
            }
            $this->db->transactionCommit();
        } catch (\Throwable $e) { $this->db->transactionRollback(); throw $e; }
        (new CacheService())->clear();
        return count($records);
    }

    /** Reject foreign-commune records and external links before persisting a snapshot. */
    public static function normalise(array $record, string $code): ?array
    {
        $url = (string) ($record['url_service_public'] ?? '');
        if (($record['code_insee_commune'] ?? '') !== $code || empty($record['nom'])
            || !preg_match('~^https://lannuaire\.service-public\.gouv\.fr/[a-zA-Z0-9/_-]+$~D', $url)) { return null; }
        $pivot = is_array($record['pivot'] ?? null) ? $record['pivot'] : (json_decode($record['pivot'] ?? '[]', true) ?: []);
        $types = array_values(array_unique(array_filter(array_column($pivot, 'type_service_local'))));
        return ['code_insee' => $code, 'title' => mb_substr($record['nom'], 0, 255),
            'type' => mb_substr(implode(',', $types), 0, 64), 'url' => $url, 'state' => 1,
            'payload' => json_encode(['id' => $record['id'] ?? '', 'types' => $types,
                'modified' => $record['date_modification'] ?? '', 'retrieved' => gmdate('Y-m-d H:i:s'),
                'source' => self::ENDPOINT], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
    }
}
