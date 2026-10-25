<?php
/**
 * Distro — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\Service;

use Joomla\Component\Comarquage\Administrator\Service\Adapter\Coperia35Adapter;
use Joomla\Component\Comarquage\Administrator\Service\Adapter\GenericXmlAdapter;

defined('_JEXEC') or die;

class XmlParserService
{
    public function parseFile(string $file, string $universe, string $version, callable $log): array
    {
        $xml = file_get_contents($file);

        if (!is_string($xml) || trim($xml) === '') {
            $log('error', ['file' => $file, 'message' => 'Empty XML file']);
            return ['items' => [], 'categories' => [], 'relations' => []];
        }

        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new \RuntimeException('XML document types and entities are not supported.');
        }
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach ($errors as $error) {
            $log('warning', ['file' => $file, 'line' => $error->line, 'message' => trim($error->message)]);
        }

        if (!$loaded || $dom->documentElement === null) {
            $log('error', ['file' => $file, 'message' => 'Invalid XML']);
            return ['items' => [], 'categories' => [], 'relations' => []];
        }

        foreach ([new Coperia35Adapter(), new GenericXmlAdapter()] as $adapter) {
            if ($adapter->supports($version)) {
                return $adapter->adapt($dom, $universe, fn ($level, $data) => $log($level, ['file' => $file] + $data));
            }
        }

        return ['items' => [], 'categories' => [], 'relations' => []];
    }
}