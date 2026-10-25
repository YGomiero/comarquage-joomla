<?php
/**
 * Comarquage — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Administrator\Service;

use Joomla\CMS\Http\HttpFactory;

defined('_JEXEC') or die;

class FeedFetcherService
{
    public function fetch(string $source, string $mode): string
    {
        $source = trim($source);

        if ($source === '') {
            throw new \InvalidArgumentException('Flux non configure.');
        }

        if ($mode === 'local') {
            if (!is_file($source)) {
                throw new \RuntimeException('Fichier XML introuvable.');
            }

            $content = file_get_contents($source);
        } else {
            if (!filter_var($source, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $source)) {
                throw new \InvalidArgumentException('URL de flux invalide.');
            }

            $response = HttpFactory::getHttp()->get($source, [], 20);

            if ($response->code < 200 || $response->code >= 300) {
                throw new \RuntimeException('Flux distant indisponible.');
            }

            $content = $response->body;
        }

        if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Flux XML vide.');
        }

        return $content;
    }
}