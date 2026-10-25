<?php
/**
 * Distro — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Plugin\Finder\Comarquage\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Component\Comarquage\Site\Service\RouteHelper;
use Joomla\Component\Finder\Administrator\Indexer\Adapter;
use Joomla\Component\Finder\Administrator\Indexer\Helper;
use Joomla\Component\Finder\Administrator\Indexer\Indexer;
use Joomla\Component\Finder\Administrator\Indexer\Result;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\QueryInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

defined('_JEXEC') or die;

final class Comarquage extends Adapter implements SubscriberInterface
{
    use DatabaseAwareTrait;

    protected $context = 'Comarquage';
    protected $extension = 'com_comarquage';
    protected $layout = 'fiche';
    protected $type_title = 'Service-Public';
    protected $table = '#__comarquage_items';
    protected $state_field = 'state';
    protected $autoloadLanguage = true;

    protected function setup()
    {
        return true;
    }

    protected function index(Result $item)
    {
        if (ComponentHelper::isEnabled($this->extension) === false) {
            return;
        }

        $item->setLanguage();
        $item->context = 'com_comarquage.fiche';
        $item->params = new Registry($item->params);
        $item->metadata = new Registry($item->metadata);
        $item->summary = Helper::prepareContent($item->summary, $item->params, $item);
        $item->body = Helper::prepareContent($item->body, $item->params, $item);
        $item->url = $this->getUrl($item->id, $this->extension, $this->layout);
        $item->route = RouteHelper::getFicheRoute((int) $item->id, (string) $item->universe, [], (string) $item->language);
        $item->addInstruction(Indexer::META_CONTEXT, 'metakey');
        $item->addInstruction(Indexer::META_CONTEXT, 'metadesc');
        $item->addTaxonomy('Type', 'Service-Public');
        $item->addTaxonomy('Universe', (string) $item->universe);
        $this->indexer->index($item);
    }

    protected function getListQuery($query = null)
    {
        $db = $this->getDatabase();

        $query = $query instanceof QueryInterface ? $query : $db->getQuery(true)
            ->select('a.id, a.title, a.alias, a.introtext AS summary, a.body')
            ->select('a.state, a.created AS start_date, a.modified, a.metakey, a.metadesc, a.metadata')
            ->select('a.language, a.access, a.universe, a.payload AS params')
            ->from($db->quoteName('#__comarquage_items', 'a'))
            ->where('a.state = 1');

        return $query;
    }
}
