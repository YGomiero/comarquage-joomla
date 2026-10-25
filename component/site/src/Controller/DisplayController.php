<?php
/**
 * Distro — Service-Public Open Data for Joomla.
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Component\Comarquage\Site\Controller;

use Joomla\CMS\MVC\Controller\BaseController;

defined('_JEXEC') or die;

class DisplayController extends BaseController
{
    public function display($cachable = false, $urlparams = [])
    {
        $view = $this->input->getCmd('view', 'particuliers');
        if (!in_array($view, ['particuliers', 'associations', 'entreprises', 'recherche'], true)) {
            throw new \RuntimeException('Page not found', 404);
        }
        $this->input->set('view', $view);

        return parent::display(false, [
            'Itemid' => 'INT',
            'id' => 'STRING',
            'theme' => 'STRING',
            'q' => 'STRING',
            'start' => 'INT',
            'limitstart' => 'INT',
        ]);
    }
}
