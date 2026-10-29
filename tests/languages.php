<?php
/** Validate each shipped INI independently, using both Joomla and JED parsing modes. */
$root = $argv[1] ?? dirname(__DIR__);
$groups = [
    ['component/admin', 'com_comarquage', 'COM_COMARQUAGE_'],
    ['component/site', 'com_comarquage', 'COM_COMARQUAGE_'],
    ['plugins/task/comarquageimport', 'plg_task_comarquageimport', 'PLG_TASK_COMARQUAGEIMPORT_'],
    ['plugins/finder/comarquage', 'plg_finder_comarquage', 'PLG_FINDER_COMARQUAGE_'],
    ['', 'pkg_comarquage', 'PKG_COMARQUAGE_'],
];
$count = 0;
$failures = [];
foreach ($groups as [$directory, $extension, $prefix]) {
    $base = $root . ($directory === '' ? '' : '/' . $directory);
    $translations = [];
    foreach (['en-GB', 'fr-FR'] as $tag) {
        foreach (glob($base . '/language/' . $tag . '/*.ini') as $file) {
            $count++;
            $content = file_get_contents($file);
            $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));
            $keys = [];
            if (str_starts_with($content, "\xEF\xBB\xBF") || !mb_check_encoding($content, 'UTF-8')) {
                $failures[] = "$relative: expected UTF-8 without BOM";
            }
            foreach (preg_split('/\r?\n/', $content) as $lineNumber => $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, ';')) { continue; }
                if (!preg_match('/^([A-Z][A-Z0-9_]*)\s*=\s*"((?:[^"\\\\]|\\\\.)*)"\s*(?:;[^"\r\n]*)?$/', $line, $match)) {
                    $failures[] = $relative . ':' . ($lineNumber + 1) . ': invalid INI assignment';
                    continue;
                }
                if (isset($keys[$match[1]])) {
                    $failures[] = "$relative: duplicate key " . $match[1];
                }
                $keys[$match[1]] = true;
            }
            foreach ([INI_SCANNER_NORMAL, INI_SCANNER_RAW] as $scanner) {
                set_error_handler(static function ($level, $message) { throw new RuntimeException($message); });
                try {
                    $parsed = parse_ini_string($content, false, $scanner);
                    if (!is_array($parsed) || count($parsed) !== count($keys)) {
                        $failures[] = "$relative: parser/key count mismatch";
                    }
                } catch (Throwable $error) {
                    $failures[] = "$relative: PHP INI parser rejected the file";
                    $parsed = [];
                } finally { restore_error_handler(); }
            }
            $translations[$tag][basename($file)] = $parsed;
        }
    }
    // Do not merge all extensions/clients: a key in the administrator must not
    // hide a missing frontend or plugin translation.
    if ($directory === '') { continue; }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (!in_array($file->getExtension(), ['php', 'xml'], true)) { continue; }
        $content = file_get_contents($file->getPathname());
        if ($file->getExtension() === 'php') {
            preg_match_all('/\bText::(?:_|sprintf|printf|alt|plural|script)\s*\(\s*[\'"](' . $prefix . '[A-Z0-9_]+)[\'"]\s*[,)]/', $content, $matches);
        } else {
            preg_match_all('/\b(' . $prefix . '[A-Z0-9_]+)\b/', $content, $matches);
        }
        foreach (['en-GB', 'fr-FR'] as $tag) {
            $defined = $translations[$tag][$extension . '.ini'] ?? [];
            if ($file->getExtension() === 'xml') {
                $defined += $translations[$tag][$extension . '.sys.ini'] ?? [];
            }
            foreach (array_unique($matches[1]) as $key) {
                if (!array_key_exists($key, $defined)) {
                    $failures[] = "$directory ($tag): missing $key used by " . $file->getFilename();
                }
            }
        }
    }
}
if ($count !== 18) { $failures[] = "Expected 18 language files, found $count"; }
if ($failures) {
    fwrite(STDERR, implode("\n", array_unique($failures)) . "\n");
    exit(1);
}
echo "$count language files passed strict syntax, PHP INI parsing and per-client FR/EN key checks.\n";
