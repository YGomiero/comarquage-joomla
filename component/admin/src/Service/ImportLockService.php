<?php
/**
 * Atomic import lock, released by the OS if the process exits unexpectedly.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\Service;
defined('_JEXEC') or die;

final class ImportLockService
{
    private $handle = null;

    public function acquire(int $ttlSeconds = 3600): bool
    {
        $this->handle = fopen(SourceStorageService::workspace() . '/import.lock', 'c');
        return $this->handle !== false && flock($this->handle, LOCK_EX | LOCK_NB);
    }

    public function release(): void
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
        $this->handle = null;
    }
}
