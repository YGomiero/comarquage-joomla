<?php
/**
 * Bounded XML-only ZIP extraction into an isolated, site-specific temporary directory.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\Service;
defined('_JEXEC') or die;

final class SourceStorageService
{
    public static function workspace(): string
    {
        $path = sys_get_temp_dir() . '/comarquage_' . substr(hash('sha256', JPATH_ROOT), 0, 16);
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new \RuntimeException('Cannot create the import workspace.');
        }
        return $path;
    }

    public function prepare(string $path): array
    {
        if (is_dir($path)) {
            return $this->listXml($path);
        }
        if (!is_file($path)) {
            throw new \RuntimeException('Source file not found.');
        }
        $signature = file_get_contents($path, false, null, 0, 4);
        if ($signature !== "PK\x03\x04") {
            return [$path];
        }
        $target = self::workspace() . '/xml_' . hash_file('sha256', $path);
        if (!is_dir($target)) {
            mkdir($target, 0700, true);
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true || $zip->numFiles > 20000) {
            throw new \RuntimeException('Invalid or oversized archive.');
        }
        $total = 0;
        $files = [];
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = str_replace('\\', '/', $entry['name']);
                if (preg_match('~(^/|(^|/)\.\.(/|$)|:)~', $name)) {
                    throw new \RuntimeException('Unsafe archive entry.');
                }
                if (!str_ends_with(strtolower($name), '.xml')) {
                    continue;
                }
                $total += $entry['size'];
                if ($entry['size'] > 32 * 1024 * 1024 || $total > 512 * 1024 * 1024) {
                    throw new \RuntimeException('Archive extraction limit exceeded.');
                }
                // Flatten filenames into hashes: no paths or executable files are extracted.
                $file = $target . '/' . hash('sha256', $name) . '.xml';
                $content = $zip->getFromIndex($i);
                if ($content === false || file_put_contents($file, $content) === false) {
                    throw new \RuntimeException('Cannot extract XML file.');
                }
                $files[] = $file;
            }
        } finally {
            $zip->close();
        }
        sort($files, SORT_STRING);
        return $files;
    }

    public function listXml(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isLink() && $file->isFile() && strtolower($file->getExtension()) === 'xml') {
                $files[] = $file->getPathname();
            }
        }
        sort($files, SORT_STRING);
        return $files;
    }

    /** Delete only this site's generated files, never a configured local source. */
    public function cleanup(array $files, string $download): void
    {
        $root = str_replace('\\', '/', self::workspace()) . '/';
        $dirs = [];
        foreach (array_merge($files, [$download]) as $file) {
            $resolved = realpath($file);
            if ($resolved && str_starts_with(str_replace('\\', '/', $resolved), $root) && is_file($resolved)) {
                $dirs[dirname($resolved)] = true;
                unlink($resolved);
            }
        }
        foreach (array_keys($dirs) as $dir) {
            if (str_replace('\\', '/', $dir) !== rtrim($root, '/') && count(scandir($dir)) === 2) {
                rmdir($dir);
            }
        }
    }
}
