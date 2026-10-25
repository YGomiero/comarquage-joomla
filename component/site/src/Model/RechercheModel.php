<?php
/**
 * Comarquage — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Site\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Pagination\Pagination;

defined('_JEXEC') or die;

class RechercheModel extends BaseDatabaseModel
{
    private ?int $total = null;

    public function getItems(): array
    {
        $q = trim(Factory::getApplication()->getInput()->getString('q', ''));
        if ($q === '') {
            return [];
        }

        $cacheKey = $this->getCacheKey('items');
        $cached = $this->readCache($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $locked = $this->openCacheLock($cacheKey);

        if (!$locked) {
            $cached = $this->waitForCache($cacheKey);

            if (is_array($cached)) {
                return $cached;
            }
        }

        try {
            $db = $this->getDatabase();
            $items = $db->setQuery($this->buildQuery(false), $this->getStart(), $this->getLimit())->loadAssocList() ?: [];
            $this->writeCache($cacheKey, $items);

            return $items;
        } finally {
            $this->closeCacheLock($locked);
        }
    }

    public function getPagination(): Pagination
    {
        $pagination = new Pagination($this->getTotal(), $this->getStart(), $this->getLimit());
        $q = trim(Factory::getApplication()->getInput()->getString('q', ''));

        if ($q !== '') {
            $pagination->setAdditionalUrlParam('q', $q);
        }

        return $pagination;
    }

    public function getTotal(): int
    {
        if ($this->total !== null) {
            return $this->total;
        }

        $q = trim(Factory::getApplication()->getInput()->getString('q', ''));
        if ($q === '') {
            $this->total = 0;
            return $this->total;
        }

        $cacheKey = $this->getCacheKey('total');
        $cached = $this->readCache($cacheKey);

        if (is_numeric($cached)) {
            $this->total = (int) $cached;
            return $this->total;
        }

        $locked = $this->openCacheLock($cacheKey);

        if (!$locked) {
            $cached = $this->waitForCache($cacheKey);

            if (is_numeric($cached)) {
                $this->total = (int) $cached;
                return $this->total;
            }
        }

        try {
            $this->total = (int) $this->getDatabase()->setQuery($this->buildQuery(true))->loadResult();
            $this->writeCache($cacheKey, $this->total);

            return $this->total;
        } finally {
            $this->closeCacheLock($locked);
        }
    }

    private function buildQuery(bool $count)
    {
        $params = ComponentHelper::getParams('com_comarquage');
        $enabled = array_values(array_filter(['particuliers', 'associations', 'entreprises'], fn ($u) => (int) $params->get('enable_' . $u, 1)));
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select($count ? 'COUNT(DISTINCT a.id)' : 'DISTINCT a.id, a.external_id, a.universe AS rubrique, a.title, a.alias, LEFT(COALESCE(NULLIF(a.introtext, \'\'), a.body), 1200) AS introtext, LEFT(COALESCE(NULLIF(a.introtext, \'\'), a.body), 1200) AS body')
            ->from($db->quoteName('#__comarquage_items', 'a'))
            ->where($db->quoteName('a.state') . ' = 1');

        if (!$enabled) {
            return $query->where('1 = 0');
        }

        $q = trim(Factory::getApplication()->getInput()->getString('q', ''));
        $match = $this->buildFullTextExpression($q);
        $matchSql = 'MATCH(' . $db->quoteName('s.title') . ',' . $db->quoteName('s.content') . ') AGAINST (' . $db->quote($match) . ' IN BOOLEAN MODE)';
        $query->whereIn($db->quoteName('a.universe'), $enabled, \Joomla\Database\ParameterType::STRING)
            ->join('INNER', $db->quoteName('#__comarquage_search_index', 's') . ' ON s.item_id = a.id')
            ->where($matchSql);

        if (!$count) {
            $query->order($matchSql . ' DESC')
                ->order($db->quoteName('a.title') . ' ASC');
        }

        return $query;
    }

    private function buildFullTextExpression(string $query): string
    {
        $terms = preg_split('/\s+/u', trim($query)) ?: [];
        $tokens = [];

        foreach ($terms as $term) {
            $term = trim($term);
            $term = preg_replace('/[^\p{L}\p{N}_-]+/u', '', $term) ?? '';

            if (mb_strlen($term) < 3) {
                continue;
            }

            $tokens[] = '+' . $term . '*';
        }

        if (!$tokens) {
            $fallback = preg_replace('/[^\p{L}\p{N}_-]+/u', '', trim($query)) ?? '';

            return $fallback !== '' ? $fallback . '*' : '';
        }

        return implode(' ', $tokens);
    }

    private function getLimit(): int
    {
        $params = ComponentHelper::getParams('com_comarquage');
        return max(5, min(100, (int) $params->get('results_per_page', 20)));
    }

    private function getStart(): int
    {
        $input = Factory::getApplication()->getInput();
        $sefStart = $input->getInt('start', -1);
        $start = $sefStart >= 0 ? $sefStart : $input->getInt('limitstart', 0);

        return max(0, $start);
    }

    private function getCacheKey(string $type): string
    {
        $input = Factory::getApplication()->getInput();
        $parts = [
            'search',
            $type,
            trim($input->getString('q', '')),
        ];

        if ($type !== 'total') {
            $parts[] = (string) $this->getStart();
            $parts[] = (string) $this->getLimit();
        }

        return sha1(implode('|', $parts));
    }

    private function readCache(string $key): mixed
    {
        $file = $this->getCacheFile($key);

        if (!is_file($file)) {
            return null;
        }

        $params = ComponentHelper::getParams('com_comarquage');
        $ttl = max(1, (int) $params->get('cache_hours', 24)) * 3600;

        if ((time() - (int) filemtime($file)) > $ttl) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return $decoded['value'] ?? null;
    }

    private function writeCache(string $key, mixed $value): void
    {
        $directory = $this->getCacheDirectory();

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return;
        }

        @file_put_contents($this->getCacheFile($key), json_encode(['value' => $value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function getCacheFile(string $key): string
    {
        return $this->getCacheDirectory() . '/search_' . $key . '.json';
    }

    private function waitForCache(string $key): mixed
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            usleep(100000);
            $cached = $this->readCache($key);

            if ($cached !== null) {
                return $cached;
            }
        }

        return null;
    }

    private function openCacheLock(string $key): mixed
    {
        $directory = $this->getCacheDirectory();

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return false;
        }

        $handle = @fopen($directory . '/search_' . $key . '.lock', 'c');

        if (!$handle) {
            return false;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }

        return $handle;
    }

    private function closeCacheLock(mixed $handle): void
    {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function getCacheDirectory(): string
    {
        return JPATH_ROOT . '/tmp/com_comarquage/cache';
    }
}
