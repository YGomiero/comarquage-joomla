<?php
/**
 * Distro — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\Service;

use Joomla\CMS\Date\Date;
use Joomla\Database\DatabaseInterface;

defined('_JEXEC') or die;

class RepositoryService
{
    public function __construct(private DatabaseInterface $db)
    {
    }

    public function upsertCategory(string $universe, array $category): int
    {
        $now = (new Date())->toSql();
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__comarquage_categories'))
            ->where($this->db->quoteName('universe') . ' = :universe')
            ->where($this->db->quoteName('external_id') . ' = :external_id')
            ->bind(':universe', $universe)
            ->bind(':external_id', $category['external_id']);
        $id = (int) $this->db->setQuery($query)->loadResult();

        $data = [
            'universe' => $universe,
            'external_id' => $category['external_id'],
            'parent_external_id' => $category['parent_external_id'] ?? '',
            'title' => $category['title'],
            'alias' => $category['alias'] ?: $category['external_id'],
            'path' => $category['path'] ?? '',
            'level' => (int) ($category['level'] ?? 0),
            'state' => 1,
            'payload' => json_encode($category['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'modified' => $now,
        ];

        $object = (object) $data;
        if ($id > 0) {
            $object->id = $id;
            $this->db->updateObject('#__comarquage_categories', $object, 'id');
            return $id;
        }

        $this->db->insertObject('#__comarquage_categories', $object, 'id');
        return (int) $object->id;
    }

    public function upsertItem(string $universe, string $version, array $item): int
    {
        $now = (new Date())->toSql();
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['id', 'source_hash', 'state']))
            ->from($this->db->quoteName('#__comarquage_items'))
            ->where($this->db->quoteName('universe') . ' = :universe')
            ->where($this->db->quoteName('external_id') . ' = :external_id')
            ->bind(':universe', $universe)
            ->bind(':external_id', $item['external_id']);

        $body = (string) ($item['body'] ?? '');
        $payloadJson = json_encode($item['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sourceHash = sha1($body . "\n" . ($payloadJson ?: ''));
        $row = $this->db->setQuery($query)->loadAssoc() ?: [];
        $id = (int) ($row['id'] ?? 0);

        if ($id > 0 && (int) $row['state'] === 1 && hash_equals((string) ($row['source_hash'] ?? ''), $sourceHash)) {
            return $id;
        }

        $data = [
            'universe' => $universe,
            'external_id' => $item['external_id'],
            'title' => $item['title'],
            'alias' => $item['alias'] ?: $item['external_id'],
            'introtext' => $item['introtext'] ?? '',
            'body' => $body,
            'state' => 1,
            'access' => 1,
            'language' => '*',
            'metakey' => '',
            'metadesc' => $item['metadesc'] ?? '',
            'metadata' => '{}',
            'canonical_url' => '',
            'source_version' => $version,
            'source_hash' => $sourceHash,
            'payload' => $payloadJson ?: '{}',
            'modified' => $now,
        ];

        $object = (object) $data;
        if ($id > 0) {
            $object->id = $id;
            $this->db->updateObject('#__comarquage_items', $object, 'id');
        } else {
            $object->created = $now;
            $this->db->insertObject('#__comarquage_items', $object, 'id');
            $id = (int) $object->id;
        }

        $this->indexItem($id, $universe, $item['title'], $body, $now);
        return $id;
    }

    public function mapItemCategory(int $itemId, int $categoryId): void
    {
        if ($itemId < 1 || $categoryId < 1) {
            return;
        }

        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__comarquage_item_category_map'))
            ->columns([$this->db->quoteName('item_id'), $this->db->quoteName('category_id')])
            ->values((int) $itemId . ', ' . (int) $categoryId);

        try {
            $this->db->setQuery($query)->execute();
        } catch (\RuntimeException) {
        }
    }

    private function indexItem(int $itemId, string $universe, string $title, string $content, string $now): void
    {
        $content = mb_substr($content, 0, 12000);
        $object = (object) [
            'item_id' => $itemId,
            'universe' => $universe,
            'title' => $title,
            'content' => $content,
            'updated' => $now,
        ];

        $exists = (int) $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->db->quoteName('#__comarquage_search_index'))
                ->where($this->db->quoteName('item_id') . ' = ' . (int) $itemId)
        )->loadResult();

        if ($exists) {
            $this->db->updateObject('#__comarquage_search_index', $object, 'item_id');
        } else {
            $this->db->insertObject('#__comarquage_search_index', $object);
        }
    }
}
