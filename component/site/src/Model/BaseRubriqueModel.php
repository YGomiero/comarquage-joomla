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

abstract class BaseRubriqueModel extends BaseDatabaseModel
{
    protected string $rubrique = '';
    private ?int $total = null;

    public function getRubrique(): string
    {
        return $this->rubrique;
    }

    public function getItems(): array
    {
        $params = ComponentHelper::getParams('com_comarquage');
        if (!(int) $params->get('enable_' . $this->rubrique, 1)) {
            return [];
        }

        $input = Factory::getApplication()->getInput();
        $queryText = trim($input->getString('q', ''));
        $theme = trim($input->getString('theme', ''));
        $subtheme = trim($input->getString('subtheme', ''));

        if ($queryText === '' && $theme === '') {
            return [];
        }

        if ($queryText === '' && $theme !== '' && $subtheme === '' && $this->getCurrentThemeSubthemes()) {
            return [];
        }

        $cacheKey = $this->getListCacheKey('items');
        $cached = $this->readListCache($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $locked = $this->openListCacheLock($cacheKey);

        if (!$locked) {
            $cached = $this->waitForListCache($cacheKey);

            if (is_array($cached)) {
                return $cached;
            }
        }

        try {
            $db = $this->getDatabase();
            $query = $this->buildItemsQuery(false);
            $limit = $this->getLimit();
            $start = $this->getStart();

            $items = $db->setQuery($query, $start, $limit)->loadAssocList() ?: [];
            $this->writeListCache($cacheKey, $items);

            return $items;
        } finally {
            $this->closeListCacheLock($locked);
        }
    }

    public function getPagination(): Pagination
    {
        $pagination = new Pagination($this->getTotal(), $this->getStart(), $this->getLimit());
        $input = Factory::getApplication()->getInput();
        $theme = trim($input->getString('theme', ''));
        $queryText = trim($input->getString('q', ''));
        $subtheme = trim($input->getString('subtheme', ''));

        if ($theme !== '') {
            $pagination->setAdditionalUrlParam('theme', $theme);
        }

        if ($subtheme !== '') {
            $pagination->setAdditionalUrlParam('subtheme', $subtheme);
        }

        if ($queryText !== '') {
            $pagination->setAdditionalUrlParam('q', $queryText);
        }

        return $pagination;
    }

    public function getTotal(): int
    {
        if ($this->total !== null) {
            return $this->total;
        }

        $input = Factory::getApplication()->getInput();
        $queryText = trim($input->getString('q', ''));
        $theme = trim($input->getString('theme', ''));
        $subtheme = trim($input->getString('subtheme', ''));

        if ($queryText === '' && $theme === '') {
            $this->total = 0;
            return $this->total;
        }

        if ($queryText === '' && $theme !== '' && $subtheme === '' && $this->getCurrentThemeSubthemes()) {
            $this->total = 0;
            return $this->total;
        }

        $cacheKey = $this->getListCacheKey('total');
        $cached = $this->readListCache($cacheKey);

        if (is_numeric($cached)) {
            $this->total = (int) $cached;
            return $this->total;
        }

        $locked = $this->openListCacheLock($cacheKey);

        if (!$locked) {
            $cached = $this->waitForListCache($cacheKey);

            if (is_numeric($cached)) {
                $this->total = (int) $cached;
                return $this->total;
            }
        }

        try {
            $db = $this->getDatabase();
            $this->total = (int) $db->setQuery($this->buildItemsQuery(true))->loadResult();
            $this->writeListCache($cacheKey, $this->total);

            return $this->total;
        } finally {
            $this->closeListCacheLock($locked);
        }
    }

    public function getItem(): ?array
    {
        $id = Factory::getApplication()->getInput()->getString('id', '');
        if ($id === '') {
            return null;
        }

        $db = $this->getDatabase();
        $universe = $this->rubrique;
        $alias = $id;

        if (ctype_digit($id)) {
            $numericId = (int) $id;
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__comarquage_items'))
                ->where($db->quoteName('id') . ' = :numericId')
                ->where($db->quoteName('universe') . ' = :universe')
                ->where($db->quoteName('state') . ' = 1')
                ->bind(':numericId', $numericId)
                ->bind(':universe', $universe);

            return $db->setQuery($query, 0, 1)->loadAssoc() ?: null;
        }

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__comarquage_items'))
            ->where($db->quoteName('universe') . ' = :universe')
            ->where($db->quoteName('alias') . ' = :alias')
            ->where($db->quoteName('state') . ' = 1')
            ->bind(':universe', $universe)
            ->bind(':alias', $alias);

        $item = $db->setQuery($query, 0, 1)->loadAssoc();

        if ($item) {
            return $item;
        }

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__comarquage_items'))
            ->where($db->quoteName('universe') . ' = :universe')
            ->where($db->quoteName('external_id') . ' = :externalId')
            ->where($db->quoteName('state') . ' = 1')
            ->bind(':universe', $universe)
            ->bind(':externalId', $alias);

        return $db->setQuery($query, 0, 1)->loadAssoc() ?: null;
    }

    public function getThemes(): array
    {
        if ($this->rubrique === 'associations') {
            return $this->getAssociationThemes();
        }

        $db = $this->getDatabase();
        $universe = $this->rubrique;
        $query = $db->getQuery(true)
            ->select('DISTINCT c.title, c.alias')
            ->from($db->quoteName('#__comarquage_categories', 'c'))
            ->where($db->quoteName('c.universe') . ' = :universe')
            ->where($db->quoteName('c.state') . ' = 1')
            // Shared online resources may also reference themes from another audience.
            // Home cards follow the actual publications in this universe.
            ->where("EXISTS (SELECT 1 FROM #__comarquage_item_category_map AS m INNER JOIN #__comarquage_items AS i ON i.id=m.item_id WHERE m.category_id=c.id AND i.state=1 AND JSON_UNQUOTE(JSON_EXTRACT(i.payload, '$.source_node'))='Publication')")
            ->bind(':universe', $universe)
            ->order($db->quoteName('c.title') . ' ASC');

        if ($this->rubrique === 'particuliers') {
            $query->where($db->quoteName('c.alias') . ' NOT LIKE ' . $db->quote('rg-%'))
                ->where($db->quoteName('c.title') . ' NOT LIKE ' . $db->quote('RG %'))
                ->where($db->quoteName('c.alias') . ' NOT IN (' . implode(',', array_map([$db, 'quote'], [
                    'comment-faire-si',
                    'services-en-ligne-et-formulaires',
                    'vos-questions',
                ])) . ')');
        }

        return $db->setQuery($query)->loadAssocList() ?: [];
    }

    public function getHomePanels(): array
    {
        $cached = $this->readHomePanelsCache();

        if ($cached !== null) {
            return $cached;
        }

        if ($this->rubrique === 'entreprises') {
            $panels = [
                [
                    'icon' => 'fa-seedling',
                    'title' => html_entity_decode('Cr&eacute;er son activit&eacute;', ENT_QUOTES, 'UTF-8'),
                    'url' => \Joomla\Component\Comarquage\Site\Service\RouteHelper::getViewRoute($this->rubrique, ['theme' => 'etapes-de-vie']),
                    'links' => $this->getPanelItemsByTheme('etapes-de-vie', 3),
                ],
                [
                    'icon' => 'fa-chart-line',
                    'title' => html_entity_decode('G&eacute;rer', ENT_QUOTES, 'UTF-8'),
                    'url' => \Joomla\Component\Comarquage\Site\Service\RouteHelper::getViewRoute($this->rubrique, ['theme' => 'fonctionnement-de-lentreprise']),
                    'links' => $this->getPanelItemsByTheme('fonctionnement-de-lentreprise', 3),
                ],
                [
                    'icon' => 'fa-circle-question',
                    'title' => html_entity_decode('Questions - R&eacute;ponses', ENT_QUOTES, 'UTF-8'),
                    'url' => \Joomla\Component\Comarquage\Site\Service\RouteHelper::getViewRoute($this->rubrique, ['theme' => 'vos-questions']),
                    'links' => $this->getQuestionItems(3),
                ],
            ];
        } elseif ($this->rubrique === 'associations') {
            $panels = [
                [
                    'icon' => 'fa-file-circle-plus',
                    'title' => html_entity_decode('Cr&eacute;er une association', ENT_QUOTES, 'UTF-8'),
                    'url' => \Joomla\Component\Comarquage\Site\Service\RouteHelper::getViewRoute($this->rubrique, ['theme' => 'creation']),
                    'links' => $this->getAssociationItemsByTheme('creation', 3),
                ],
                [
                    'icon' => 'fa-gears',
                    'title' => html_entity_decode('G&eacute;rer l&rsquo;association', ENT_QUOTES, 'UTF-8'),
                    'url' => \Joomla\Component\Comarquage\Site\Service\RouteHelper::getViewRoute($this->rubrique, ['theme' => 'fonctionnement']),
                    'links' => $this->getAssociationItemsByTheme('fonctionnement', 3),
                ],
                [
                    'icon' => 'fa-hand-holding-dollar',
                    'title' => html_entity_decode('Financement et b&eacute;n&eacute;volat', ENT_QUOTES, 'UTF-8'),
                    'url' => \Joomla\Component\Comarquage\Site\Service\RouteHelper::getViewRoute($this->rubrique, ['theme' => 'financement']),
                    'links' => $this->getAssociationItemsByTheme('financement', 3),
                ],
            ];
        } else {
            $panels = [
                [
                    'icon' => 'fa-route',
                    'title' => 'Événements de vie',
                    'url' => \Joomla\Component\Comarquage\Site\Service\RouteHelper::getViewRoute($this->rubrique, ['theme' => 'comment-faire-si']),
                    'links' => $this->getPanelItemsByTheme('comment-faire-si', 3),
                ],
                [
                    'icon' => 'fa-laptop',
                    'title' => 'Services en ligne',
                    'url' => \Joomla\Component\Comarquage\Site\Service\RouteHelper::getViewRoute($this->rubrique, ['theme' => 'services-en-ligne-et-formulaires']),
                    'links' => $this->getPanelItemsByAliases([
                        'acte-de-naissance-demande-de-copie-integrale-ou-dextrait',
                        'carte-didentite-passeport-comment-remplir-le-formulaire-ou-la-pre-demande',
                        'comment-suivre-letat-davancement-dune-demande-de-carte-didentite',
                    ], 3),
                ],
                [
                    'icon' => 'fa-circle-question',
                    'title' => html_entity_decode('Questions - R&eacute;ponses', ENT_QUOTES, 'UTF-8'),
                    'url' => \Joomla\Component\Comarquage\Site\Service\RouteHelper::getViewRoute($this->rubrique, ['theme' => 'vos-questions']),
                    'links' => $this->getQuestionItems(3),
                ],
            ];
        }

        $this->writeHomePanelsCache($panels);

        return $panels;
    }

    public function getCurrentThemeSubthemes(): array
    {
        $input = Factory::getApplication()->getInput();
        $theme = trim($input->getString('theme', ''));

        if ($this->rubrique !== 'particuliers') {
            return [];
        }

        $themes = [
            'argent-impots-consommation' => [
                ['title' => 'Assurance', 'alias' => 'assurance', 'icon' => 'fa-shield-halved', 'description' => 'Habitation, auto, assurance vie et sinistres.'],
                ['title' => 'Banque', 'alias' => 'banque', 'icon' => 'fa-building-columns', 'description' => 'Comptes, paiements, fichiers bancaires et incidents.'],
                ['title' => 'Consommation', 'alias' => 'consommation', 'icon' => 'fa-cart-shopping', 'description' => 'Achats, garanties, prestations et protection du consommateur.'],
                ['title' => 'Crédit et surendettement', 'alias' => 'credit-surendettement', 'icon' => 'fa-credit-card', 'description' => 'Crédit à la consommation, immobilier et surendettement.'],
                ['title' => 'Épargne', 'alias' => 'epargne', 'icon' => 'fa-piggy-bank', 'description' => 'Livrets, assurance vie, épargne salariale.'],
                ['title' => 'Impôts, taxes et douane', 'alias' => 'impots-taxes-douane', 'icon' => 'fa-receipt', 'description' => 'Impôt sur le revenu, impôts locaux, succession et douane.'],
            ],
            'justice' => [
                ['title' => 'Organisation de la justice', 'alias' => 'organisation-justice', 'icon' => 'fa-scale-balanced', 'description' => 'Accès au droit, acteurs judiciaires et juridictions.'],
                ['title' => 'Procédures judiciaires', 'alias' => 'procedures-judiciaires', 'icon' => 'fa-file-signature', 'description' => 'Affaires civiles, pénales et contestation des décisions.'],
                ['title' => 'Infractions', 'alias' => 'infractions', 'icon' => 'fa-triangle-exclamation', 'description' => 'Violences, discrimination, harcèlement, vol et arnaques.'],
                ['title' => 'Sanctions pénales', 'alias' => 'sanctions-penales', 'icon' => 'fa-gavel', 'description' => 'Condamnations, peines, prison et casier judiciaire.'],
                ['title' => 'Réparation du préjudice', 'alias' => 'reparation-prejudice', 'icon' => 'fa-hand-holding-heart', 'description' => 'Indemnisation, saisies et recouvrements.'],
                ['title' => 'Justice des mineurs', 'alias' => 'justice-mineurs', 'icon' => 'fa-child-reaching', 'description' => 'Mineur victime et justice pénale des mineurs.'],
            ],
            'logement' => [
                ['title' => 'Achat immobilier', 'alias' => 'achat-immobilier', 'icon' => 'fa-house-circle-check', 'description' => 'Terrain, achat et vente d’un logement.'],
                ['title' => 'Aides financières', 'alias' => 'aides-financieres', 'icon' => 'fa-hand-holding-dollar', 'description' => 'APL, dépôt de garantie, factures et rénovation.'],
                ['title' => 'Bail d’habitation', 'alias' => 'bail-habitation', 'icon' => 'fa-file-contract', 'description' => 'Location, bail, loyer, obligations et fin de bail.'],
                ['title' => 'Expropriation - Préemption', 'alias' => 'expropriation-preemption', 'icon' => 'fa-landmark', 'description' => 'Expropriation et droit de préemption urbain.'],
                ['title' => 'Protection et sécurité de l’habitat', 'alias' => 'securite-habitat', 'icon' => 'fa-shield-heart', 'description' => 'Risques sanitaires, sécurité et diagnostics.'],
                ['title' => 'Urbanisme', 'alias' => 'urbanisme', 'icon' => 'fa-city', 'description' => 'Autorisations d’urbanisme et travaux.'],
                ['title' => 'Copropriété', 'alias' => 'copropriete', 'icon' => 'fa-building', 'description' => 'Organisation, documents, charges et assemblée générale.'],
                ['title' => 'Vie pratique dans un logement', 'alias' => 'vie-pratique-logement', 'icon' => 'fa-house-user', 'description' => 'Maison, voisinage, nuisances et déchets.'],
            ],
            'transports-mobilite' => [
                ['title' => 'Carte grise', 'alias' => 'carte-grise', 'icon' => 'fa-id-card', 'description' => 'Certificat d’immatriculation et démarches véhicule.'],
                ['title' => 'Permis de conduire', 'alias' => 'permis-conduire', 'icon' => 'fa-address-card', 'description' => 'Passer, récupérer ou renouveler son permis.'],
                ['title' => 'Infractions routières', 'alias' => 'infractions-routieres', 'icon' => 'fa-road-circle-exclamation', 'description' => 'Amendes, points, retrait et contestation.'],
                ['title' => 'Contrôle technique', 'alias' => 'controle-technique', 'icon' => 'fa-screwdriver-wrench', 'description' => 'Contrôle obligatoire et contre-visite.'],
                ['title' => 'Mesures antipollution', 'alias' => 'mesures-antipollution', 'icon' => 'fa-leaf', 'description' => 'Crit’Air, restrictions et zones à faibles émissions.'],
                ['title' => 'Conduire en France avec un permis étranger', 'alias' => 'permis-etranger-france', 'icon' => 'fa-earth-europe', 'description' => 'Validité et échange d’un permis étranger.'],
                ['title' => 'Conduire à l’étranger', 'alias' => 'conduire-etranger', 'icon' => 'fa-route', 'description' => 'Permis international et conduite hors de France.'],
                ['title' => 'Voyage en avion', 'alias' => 'voyage-avion', 'icon' => 'fa-plane', 'description' => 'Droits des passagers et formalités aériennes.'],
                ['title' => 'Cartes de transport', 'alias' => 'cartes-transport', 'icon' => 'fa-ticket', 'description' => 'Cartes, abonnements et aides au transport.'],
            ],
            'loisirs-sports-culture' => [
                ['title' => 'Chasse', 'alias' => 'chasse', 'icon' => 'fa-bullseye', 'description' => 'Permis, validation, armes et règles de chasse.'],
                ['title' => 'Pêche', 'alias' => 'peche', 'icon' => 'fa-water', 'description' => 'Carte de pêche, réglementation et pratiques.'],
                ['title' => 'Armes', 'alias' => 'armes', 'icon' => 'fa-shield-halved', 'description' => 'Acquisition, détention, déclaration et autorisation.'],
                ['title' => 'Animal de compagnie', 'alias' => 'animal-compagnie', 'icon' => 'fa-paw', 'description' => 'Identification, garde, transport et obligations.'],
                ['title' => 'Sport', 'alias' => 'sport', 'icon' => 'fa-person-running', 'description' => 'Licence, certificat médical, associations et pratiques sportives.'],
                ['title' => 'Hébergement touristique', 'alias' => 'hebergement-touristique', 'icon' => 'fa-bed', 'description' => 'Meublé, chambre d’hôtes, camping et classement.'],
                ['title' => 'Aides aux vacances', 'alias' => 'aides-vacances', 'icon' => 'fa-umbrella-beach', 'description' => 'Aides, chèques-vacances et départ en vacances.'],
            ],
            'papiers-citoyennete-elections' => [
                ['title' => 'Actes d’état civil', 'alias' => 'actes-etat-civil', 'icon' => 'fa-file-signature', 'description' => 'Naissance, mariage, décès, copies et extraits.'],
                ['title' => 'Livret de famille', 'alias' => 'livret-famille', 'icon' => 'fa-book-open', 'description' => 'Création, mise à jour, perte et duplicata.'],
                ['title' => 'Changement d’état civil', 'alias' => 'changement-etat-civil', 'icon' => 'fa-pen-to-square', 'description' => 'Nom, prénom, sexe et rectification d’acte.'],
                ['title' => 'Carte d’identité', 'alias' => 'carte-identite', 'icon' => 'fa-id-card', 'description' => 'Demande, renouvellement, perte et vol.'],
                ['title' => 'Passeport', 'alias' => 'passeport', 'icon' => 'fa-passport', 'description' => 'Demande, renouvellement, urgence et voyage.'],
                ['title' => 'Nom et prénom', 'alias' => 'nom-prenom', 'icon' => 'fa-signature', 'description' => 'Nom d’usage, changement de nom et prénom.'],
                ['title' => 'Certificats et copies de documents', 'alias' => 'certificats-copies-documents', 'icon' => 'fa-copy', 'description' => 'Certification, légalisation, conservation et traduction.'],
                ['title' => 'Élections', 'alias' => 'elections', 'icon' => 'fa-check-to-slot', 'description' => 'Inscription, procuration, vote et listes électorales.'],
                ['title' => 'Recensement citoyen, JDC et service national', 'alias' => 'recensement-jdc-service-national', 'icon' => 'fa-flag', 'description' => 'Recensement, JDC, service national et volontariats.'],
                ['title' => 'Relations avec l’administration', 'alias' => 'relations-administration', 'icon' => 'fa-landmark', 'description' => 'Obligations, recours, défenseur des droits et démarches.'],
                ['title' => 'Protection des données et de l’image', 'alias' => 'donnees-image', 'icon' => 'fa-user-shield', 'description' => 'Données personnelles, fichiers, image et vie privée.'],
            ],
            'social-sante' => [
                ['title' => 'Aides sociales', 'alias' => 'aides-sociales', 'icon' => 'fa-hand-holding-heart', 'description' => 'RSA, prime d’activité et autres aides sociales.'],
                ['title' => 'Chômage', 'alias' => 'chomage', 'icon' => 'fa-briefcase', 'description' => 'France Travail, ARE, formation et reprise d’activité.'],
                ['title' => 'Sécurité sociale', 'alias' => 'securite-sociale', 'icon' => 'fa-shield-heart', 'description' => 'Affiliation, remboursements, mutuelle et litiges.'],
                ['title' => 'Droits des patients', 'alias' => 'droits-patients', 'icon' => 'fa-user-doctor', 'description' => 'Dossier médical, préjudice médical et dons.'],
                ['title' => 'Santé', 'alias' => 'sante', 'icon' => 'fa-heart-pulse', 'description' => 'Prévention, vaccinations, addictions, grossesse et soins.'],
                ['title' => 'Invalidité', 'alias' => 'invalidite', 'icon' => 'fa-wheelchair', 'description' => 'Invalidité du salarié et du secteur public.'],
                ['title' => 'Handicap', 'alias' => 'handicap', 'icon' => 'fa-universal-access', 'description' => 'AAH, AEEH, PCH, hébergement et droits.'],
                ['title' => 'Personnes âgées', 'alias' => 'personnes-agees', 'icon' => 'fa-person-cane', 'description' => 'Allocations, aides et hébergement des personnes âgées.'],
                ['title' => 'Hébergement social', 'alias' => 'hebergement-social', 'icon' => 'fa-house-medical', 'description' => 'Hébergement, urgence sociale et accompagnement.'],
            ],
            'famille-scolarite' => [
                ['title' => 'Couple', 'alias' => 'couple', 'icon' => 'fa-people-arrows', 'description' => 'Union libre, Pacs, mariage, divorce et séparation.'],
                ['title' => 'Enfant', 'alias' => 'enfant', 'icon' => 'fa-child', 'description' => 'Adoption, naissance, autorité parentale et allocations familiales.'],
                ['title' => 'Scolarité', 'alias' => 'scolarite', 'icon' => 'fa-school', 'description' => 'Obligation scolaire, école, collège, lycée et aides.'],
                ['title' => 'Études supérieures', 'alias' => 'etudes-superieures', 'icon' => 'fa-graduation-cap', 'description' => 'Inscription, bourses, études en France et à l’étranger.'],
                ['title' => 'Protection des personnes', 'alias' => 'protection-personnes', 'icon' => 'fa-user-shield', 'description' => 'Tutelle, curatelle, disparition et enlèvement.'],
                ['title' => 'Décès', 'alias' => 'deces', 'icon' => 'fa-ribbon', 'description' => 'Déclaration, obsèques, sépulture, rentes et pensions.'],
                ['title' => 'Succession', 'alias' => 'succession', 'icon' => 'fa-scroll', 'description' => 'Héritage, donation, testament et règlement d’une succession.'],
            ],
            'travail-formation' => [
                ['title' => 'Recrutement', 'alias' => 'recrutement', 'icon' => 'fa-user-plus', 'description' => 'Emploi privé, fonction publique, concours et embauche.'],
                ['title' => 'Rémunération', 'alias' => 'remuneration', 'icon' => 'fa-sack-dollar', 'description' => 'Salaire, primes, traitement et indemnités.'],
                ['title' => 'Contrats et carrière', 'alias' => 'contrats-carriere', 'icon' => 'fa-file-contract', 'description' => 'Contrats, insertion, alternance et carrière publique.'],
                ['title' => 'Congés', 'alias' => 'conges', 'icon' => 'fa-calendar-check', 'description' => 'Congés du secteur privé et de la fonction publique.'],
                ['title' => 'Formation', 'alias' => 'formation', 'icon' => 'fa-chalkboard-user', 'description' => 'Formation professionnelle, agents publics, handicap et stages.'],
                ['title' => 'Santé, sécurité et conditions de travail', 'alias' => 'sante-securite-travail', 'icon' => 'fa-helmet-safety', 'description' => 'Conditions de travail, handicap, arrêt maladie et sécurité.'],
                ['title' => 'Relations individuelles et collectives', 'alias' => 'relations-travail', 'icon' => 'fa-people-group', 'description' => 'Représentation du personnel, conflits et dialogue social.'],
                ['title' => 'Temps de travail', 'alias' => 'temps-travail', 'icon' => 'fa-clock', 'description' => 'Durée, horaires, heures supplémentaires et aménagement.'],
                ['title' => 'Quitter son emploi', 'alias' => 'quitter-emploi', 'icon' => 'fa-person-walking-arrow-right', 'description' => 'Rupture, licenciement, démission et départ de la fonction publique.'],
                ['title' => 'Particulier employeur', 'alias' => 'particulier-employeur', 'icon' => 'fa-house-user', 'description' => 'Aide à domicile, assistante maternelle et emploi familial.'],
                ['title' => 'Retraite', 'alias' => 'retraite', 'icon' => 'fa-person-cane', 'description' => 'Retraite du secteur privé et des agents publics.'],
            ],
            'comment-faire-si' => [
                ['title' => 'Je déménage en France', 'alias' => 'je-demenage-en-france', 'item' => 'je-demenage-en-france', 'icon' => 'fa-truck-moving', 'description' => 'Adresse, logement, papiers, école et services.'],
                ['title' => 'Je pars de chez mes parents', 'alias' => 'je-pars-de-chez-mes-parents', 'item' => 'je-pars-de-chez-mes-parents', 'icon' => 'fa-door-open', 'description' => 'Logement, budget, aides et premières démarches.'],
                ['title' => 'Je deviens parent', 'alias' => 'je-deviens-parent', 'item' => 'je-deviens-parent', 'icon' => 'fa-baby', 'description' => 'Grossesse, naissance, filiation, aides et congés.'],
                ['title' => 'Un proche est décédé', 'alias' => 'un-proche-est-decede', 'item' => 'un-proche-est-decede', 'icon' => 'fa-ribbon', 'description' => 'Déclaration, obsèques, succession et aides.'],
                ['title' => 'Je suis en situation de handicap', 'alias' => 'je-suis-en-situation-de-handicap', 'item' => 'je-suis-en-situation-de-handicap', 'icon' => 'fa-universal-access', 'description' => 'Droits, aides, travail, logement et mobilité.'],
                ['title' => 'Mon enfant est en situation de handicap', 'alias' => 'mon-enfant-est-en-situation-de-handicap', 'item' => 'mon-enfant-est-en-situation-de-handicap', 'icon' => 'fa-child-reaching', 'description' => 'Scolarité, aides, soins et accompagnement.'],
                ['title' => 'Je souhaite devenir alternant', 'alias' => 'je-souhaite-devenir-alternant', 'item' => 'je-souhaite-devenir-alternant', 'icon' => 'fa-graduation-cap', 'description' => 'Contrat, formation, rémunération et droits.'],
                ['title' => 'Je souhaite travailler dans l’administration', 'alias' => 'je-souhaite-travailler-dans-ladministration', 'item' => 'je-souhaite-travailler-dans-ladministration', 'icon' => 'fa-landmark', 'description' => 'Concours, contrats et accès à la fonction publique.'],
                ['title' => 'Je prépare ma retraite', 'alias' => 'je-prepare-ma-retraite', 'item' => 'je-prepare-ma-retraite', 'icon' => 'fa-person-cane', 'description' => 'Droits, âge, trimestres et demande de retraite.'],
                ['title' => 'J’achète un logement', 'alias' => 'jachete-un-logement', 'item' => 'jachete-un-logement', 'icon' => 'fa-house-circle-check', 'description' => 'Financement, achat, notaire et installation.'],
                ['title' => 'Je veux obtenir un crédit immobilier', 'alias' => 'je-veux-obtenir-un-credit-immobilier', 'item' => 'je-veux-obtenir-un-credit-immobilier', 'icon' => 'fa-file-invoice-dollar', 'description' => 'Prêt, assurance, garanties et démarches.'],
                ['title' => 'Je me sépare', 'alias' => 'je-me-separe', 'item' => 'je-me-separe', 'icon' => 'fa-people-arrows', 'description' => 'Séparation, enfants, logement et ressources.'],
                ['title' => 'J’ai besoin de faire garder mes enfants', 'alias' => 'jai-besoin-de-faire-garder-mes-enfants', 'item' => 'jai-besoin-de-faire-garder-mes-enfants', 'icon' => 'fa-children', 'description' => 'Modes de garde, aides, contrat et emploi.'],
                ['title' => 'Je pars vivre à l’étranger', 'alias' => 'je-pars-vivre-a-letranger', 'item' => 'je-pars-vivre-a-letranger', 'icon' => 'fa-plane-departure', 'description' => 'Papiers, santé, fiscalité et protection sociale.'],
                ['title' => 'Je vis à l’étranger', 'alias' => 'je-vis-a-letranger', 'item' => 'je-vis-a-letranger', 'icon' => 'fa-earth-europe', 'description' => 'Démarches françaises depuis l’étranger.'],
                ['title' => 'Je rentre en France après avoir vécu à l’étranger', 'alias' => 'je-rentre-en-france-apres-avoir-vecu-a-letranger', 'item' => 'je-rentre-en-france-apres-avoir-vecu-a-letranger', 'icon' => 'fa-plane-arrival', 'description' => 'Retour, droits sociaux, logement et emploi.'],
                ['title' => 'Je recherche un emploi', 'alias' => 'je-recherche-un-emploi', 'item' => 'je-recherche-un-emploi', 'icon' => 'fa-magnifying-glass', 'description' => 'Inscription, aides, chômage et formation.'],
                ['title' => 'J’organise ma succession', 'alias' => 'jorganise-ma-succession', 'item' => 'jorganise-ma-succession', 'icon' => 'fa-scroll', 'description' => 'Donation, testament, héritiers et patrimoine.'],
                ['title' => 'Je suis victime ou proche de victime d’acte terroriste', 'alias' => 'que-faire-si-je-suis-une-victime-ou-un-proche-de-victime-dacte-terroriste', 'item' => 'que-faire-si-je-suis-une-victime-ou-un-proche-de-victime-dacte-terroriste', 'icon' => 'fa-hand-holding-heart', 'description' => 'Aide, indemnisation, droits et accompagnement.'],
                ['title' => 'Je crée une association', 'alias' => 'je-cree-une-association', 'item' => 'je-cree-une-association', 'icon' => 'fa-file-circle-plus', 'description' => 'Statuts, déclaration, publication et immatriculation.'],
                ['title' => 'Mon association organise un événement', 'alias' => 'lassociation-organise-un-evenement-fete-manifestation-vide-grenier', 'item' => 'lassociation-organise-un-evenement-fete-manifestation-vide-grenier', 'icon' => 'fa-calendar-days', 'description' => 'Fête, manifestation, vide-grenier et obligations.'],
            ],
        ];

        return $themes[$theme] ?? [];
    }

    private function buildItemsQuery(bool $count)
    {
        $db = $this->getDatabase();
        $input = Factory::getApplication()->getInput();
        $queryText = trim($input->getString('q', ''));
        $theme = trim($input->getString('theme', ''));
        $subtheme = trim($input->getString('subtheme', ''));
        $universe = $this->rubrique;

        $query = $db->getQuery(true)
            ->select($count ? 'COUNT(DISTINCT COALESCE(NULLIF(a.alias, \'\'), CONCAT(\'id:\', a.id)))' : 'a.id, a.external_id, a.universe, a.title, a.alias, LEFT(COALESCE(NULLIF(a.introtext, \'\'), a.body), 1200) AS introtext, LEFT(COALESCE(NULLIF(a.introtext, \'\'), a.body), 1200) AS body, a.metadesc, a.canonical_url')
            ->from($db->quoteName('#__comarquage_items', 'a'))
            ->where($db->quoteName('a.universe') . ' = :universe')
            ->where($db->quoteName('a.state') . ' = 1')
            ->bind(':universe', $universe);

        if ($queryText !== '') {
            $match = $this->buildFullTextExpression($queryText);
            $matchSql = 'MATCH(' . $db->quoteName('s.title') . ',' . $db->quoteName('s.content') . ') AGAINST (' . $db->quote($match) . ' IN BOOLEAN MODE)';
            $query->join('INNER', $db->quoteName('#__comarquage_search_index', 's') . ' ON s.item_id = a.id')
                ->where($db->quoteName('s.universe') . ' = ' . $db->quote($universe))
                ->where($matchSql);

            if ($this->rubrique === 'associations') {
                $this->applyAssociationBaseScope($query);
            }
        }

        if ($this->rubrique === 'associations' && $theme !== '' && $this->applyAssociationThemeFilter($query, $theme)) {
            $this->applyAssociationBaseScope($query);
        } elseif ($theme === 'services-en-ligne-et-formulaires') {
            $query->where('(' . $db->quoteName('a.body') . ' LIKE ' . $db->quote('%service en ligne%') . ' OR ' . $db->quoteName('a.body') . ' LIKE ' . $db->quote('%tele%') . ' OR ' . $db->quoteName('a.body') . ' LIKE ' . $db->quote('%formulaire%') . ')')
                ->where($db->quoteName('a.alias') . ' <> ' . $db->quote('services-en-ligne-et-formulaires'));
        } elseif ($theme === 'vos-questions') {
            $question = '%?%';
            $query->where($db->quoteName('a.title') . ' LIKE :themeQuestion')
                ->where($db->quoteName('a.alias') . ' <> ' . $db->quote('questions-reponses'))
                ->bind(':themeQuestion', $question);
        } elseif ($theme !== '') {
            $themeAlias = $theme;
            $query->join('INNER', $db->quoteName('#__comarquage_item_category_map', 'm') . ' ON m.item_id = a.id')
                ->join('INNER', $db->quoteName('#__comarquage_categories', 'c') . ' ON c.id = m.category_id')
                ->where($db->quoteName('c.alias') . ' = :theme')
                ->where($db->quoteName('a.alias') . ' <> :themeAlias')
                ->bind(':themeAlias', $themeAlias)
                ->bind(':theme', $themeAlias);

            if ($this->rubrique === 'particuliers' && $subtheme !== '') {
                $this->applyParticuliersSubthemeFilter($query, $themeAlias, $subtheme);
            }
        }

        $this->applyBestAliasFilter($query);

        if (!$count) {
            $query->order($db->quoteName('a.title') . ' ASC');
        }

        return $query;
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

    private function getPanelItemsByTheme(string $themeAlias, int $limit): array
    {
        $db = $this->getDatabase();
        $universe = $this->rubrique;
        $query = $db->getQuery(true)
            ->select('a.id, a.title, a.alias')
            ->from($db->quoteName('#__comarquage_items', 'a'))
            ->join('INNER', $db->quoteName('#__comarquage_item_category_map', 'm') . ' ON m.item_id = a.id')
            ->join('INNER', $db->quoteName('#__comarquage_categories', 'c') . ' ON c.id = m.category_id')
            ->where($db->quoteName('a.universe') . ' = :universe')
            ->where($db->quoteName('a.state') . ' = 1')
            ->where($db->quoteName('c.alias') . ' = :theme')
            ->where($db->quoteName('a.alias') . ' <> :themeAlias')
            ->bind(':universe', $universe)
            ->bind(':themeAlias', $themeAlias)
            ->bind(':theme', $themeAlias)
            ->order($db->quoteName('a.title') . ' ASC');

        return $this->panelRows($db->setQuery($query, 0, $limit)->loadAssocList() ?: []);
    }

    private function getPanelItemsByAliases(array $aliases, int $limit): array
    {
        if (!$aliases) {
            return [];
        }

        $db = $this->getDatabase();
        $quotedAliases = array_map([$db, 'quote'], $aliases);
        $orderCases = [];

        foreach ($aliases as $index => $alias) {
            $orderCases[] = 'WHEN ' . $db->quoteName('a.alias') . ' = ' . $db->quote($alias) . ' THEN ' . (int) $index;
        }

        $query = $db->getQuery(true)
            ->select('a.id, a.title, a.alias')
            ->from($db->quoteName('#__comarquage_items', 'a'))
            ->where($db->quoteName('a.universe') . ' = ' . $db->quote($this->rubrique))
            ->where($db->quoteName('a.state') . ' = 1')
            ->where($db->quoteName('a.alias') . ' IN (' . implode(',', $quotedAliases) . ')')
            ->order('CASE ' . implode(' ', $orderCases) . ' ELSE 999 END ASC');

        return $this->panelRows($db->setQuery($query, 0, $limit)->loadAssocList() ?: []);
    }

    private function applyParticuliersSubthemeFilter($query, string $theme, string $subtheme): bool
    {
        $subthemes = [
            'argent-impots-consommation' => [
                'assurance' => ['assurance habitation', 'assurance auto', 'assurance vie', 'assureur', 'sinistre', 'franchise'],
                'banque' => ['compte bancaire', 'opération bancaire', 'operation bancaire', 'virement', 'prélèvement', 'prelevement', 'mandat', 'fichier bancaire', 'carte bancaire', 'chèque', 'cheque', 'espèces', 'especes', 'interdit bancaire'],
                'consommation' => ['consommateur', 'consommation', 'achat', 'vente', 'garantie', 'prestation de service', 'démarchage', 'demarchage', 'litige', 'rétractation', 'retractation'],
                'credit-surendettement' => ['crédit', 'credit', 'surendettement', 'prêt', 'pret', 'emprunt', 'taux', 'hypothèque', 'hypotheque'],
                'epargne' => ['épargne', 'epargne', 'livret', 'assurance vie', 'plan d\'épargne', 'plan d\'epargne', 'intéressement', 'interessement', 'participation'],
                'impots-taxes-douane' => ['impôt', 'impot', 'taxe', 'douane', 'revenu', 'déclaration', 'declaration', 'crédit d\'impôt', 'credit d\'impot', 'succession', 'donation', 'prélèvements sociaux', 'prelevements sociaux', 'csg', 'crds'],
            ],
            'justice' => [
                'organisation-justice' => ['justice', 'tribunal', 'juridiction', 'avocat', 'notaire', 'commissaire de justice', 'aide juridictionnelle', 'accès au droit', 'acces au droit'],
                'procedures-judiciaires' => ['procès', 'proces', 'plainte', 'recours', 'appel', 'jugement', 'décision de justice', 'decision de justice', 'audience', 'médiation', 'mediation', 'conciliation'],
                'infractions' => ['violence', 'harcèlement', 'harcelement', 'discrimination', 'vol', 'escroquerie', 'arnaque', 'injure', 'diffamation', 'vandalisme', 'stupéfiant', 'stupefiant'],
                'sanctions-penales' => ['condamnation', 'peine', 'prison', 'détention', 'detention', 'bracelet électronique', 'bracelet electronique', 'casier judiciaire', 'amende pénale', 'amende penale'],
                'reparation-prejudice' => ['indemnisation', 'préjudice', 'prejudice', 'dommage', 'victime', 'saisie', 'recouvrement', 'fonds de garantie'],
                'justice-mineurs' => ['mineur', 'enfant', 'juge des enfants', 'autorité parentale', 'autorite parentale'],
            ],
            'logement' => [
                'achat-immobilier' => ['achat', 'vente', 'terrain', 'promesse de vente', 'acte de vente', 'notaire', 'prêt immobilier', 'pret immobilier'],
                'aides-financieres' => ['apl', 'aide au logement', 'allocation logement', 'dépôt de garantie', 'depot de garantie', 'rénovation', 'renovation', 'facture', 'chèque énergie', 'cheque energie'],
                'bail-habitation' => ['bail', 'location', 'locataire', 'propriétaire', 'proprietaire', 'loyer', 'charges locatives', 'préavis', 'preavis', 'état des lieux', 'etat des lieux'],
                'expropriation-preemption' => ['expropriation', 'préemption', 'preemption', 'déclaration d\'intention d\'aliéner', 'declaration d\'intention d\'aliener'],
                'securite-habitat' => ['diagnostic', 'insalubre', 'sécurité', 'securite', 'amiante', 'plomb', 'radon', 'incendie', 'risque'],
                'urbanisme' => ['urbanisme', 'permis de construire', 'déclaration préalable', 'declaration prealable', 'travaux', 'certificat d\'urbanisme'],
                'copropriete' => ['copropriété', 'copropriete', 'syndic', 'assemblée générale', 'assemblee generale', 'charges de copropriété', 'charges de copropriete'],
                'vie-pratique-logement' => ['voisinage', 'nuisance', 'déchet', 'dechet', 'eau', 'électricité', 'electricite', 'gaz', 'internet', 'domiciliation'],
            ],
            'transports-mobilite' => [
                'carte-grise' => ['carte grise', 'certificat d\'immatriculation', 'immatriculation', 'siv', 'véhicule d\'occasion', 'vehicule d\'occasion'],
                'permis-conduire' => ['permis de conduire', 'perte de points', 'points du permis', 'examen du permis', 'conduite accompagnée', 'conduite accompagnee'],
                'infractions-routieres' => ['infraction routière', 'infraction routiere', 'amende', 'radar', 'stationnement', 'retrait de points', 'suspension du permis', 'alcool au volant'],
                'controle-technique' => ['contrôle technique', 'controle technique', 'contre-visite', 'défaillance', 'defaillance'],
                'mesures-antipollution' => ['crit\'air', 'pollution', 'zone à faibles émissions', 'zone a faibles emissions', 'zfe', 'vignette'],
                'permis-etranger-france' => ['permis étranger', 'permis etranger', 'échange du permis', 'echange du permis', 'conduire en france'],
                'conduire-etranger' => ['permis international', 'conduire à l\'étranger', 'conduire a l\'etranger', 'étranger', 'etranger'],
                'voyage-avion' => ['avion', 'vol', 'bagage', 'compagnie aérienne', 'compagnie aerienne', 'retard', 'annulation'],
                'cartes-transport' => ['carte de transport', 'abonnement transport', 'transport scolaire', 'navigo', 'mobilité inclusion', 'mobilite inclusion'],
            ],
            'loisirs-sports-culture' => [
                'chasse' => ['chasse', 'chasseur', 'permis de chasser', 'gibier'],
                'peche' => ['pêche', 'peche', 'pêcheur', 'pecheur', 'carte de pêche', 'carte de peche'],
                'armes' => ['arme', 'armes', 'fusil', 'munition', 'détention d\'arme', 'detention d\'arme', 'port d\'arme'],
                'animal-compagnie' => ['animal', 'chien', 'chat', 'animal de compagnie', 'identification', 'tatouage', 'puce électronique', 'puce electronique'],
                'sport' => ['sport', 'sportif', 'licence sportive', 'certificat médical', 'certificat medical', 'dopage'],
                'hebergement-touristique' => ['hébergement touristique', 'hebergement touristique', 'meublé de tourisme', 'meuble de tourisme', 'chambre d\'hôtes', 'chambre d\'hotes', 'camping', 'classement'],
                'aides-vacances' => ['vacances', 'chèque-vacances', 'cheque-vacances', 'aide aux vacances', 'colonies de vacances', 'colo'],
            ],
            'papiers-citoyennete-elections' => [
                'actes-etat-civil' => ['acte de naissance', 'acte de mariage', 'acte de décès', 'acte de deces', 'état civil', 'etat civil', 'copie intégrale', 'copie integrale', 'extrait'],
                'livret-famille' => ['livret de famille'],
                'changement-etat-civil' => ['changement d\'état civil', 'changement d\'etat civil', 'rectification', 'changement de nom', 'changement de prénom', 'changement de prenom', 'changement de sexe'],
                'carte-identite' => ['carte d\'identité', 'carte d\'identite', 'cni', 'identité', 'identite'],
                'passeport' => ['passeport'],
                'nom-prenom' => ['nom de famille', 'nom d\'usage', 'prénom', 'prenom', 'changement de nom', 'changement de prénom', 'changement de prenom'],
                'certificats-copies-documents' => ['certificat', 'copie', 'légalisation', 'legalisation', 'conservation de documents', 'traduction', 'certification'],
                'elections' => ['élection', 'election', 'vote', 'électeur', 'electeur', 'liste électorale', 'liste electorale', 'procuration', 'bureau de vote'],
                'recensement-jdc-service-national' => ['recensement citoyen', 'jdc', 'journée défense', 'journee defense', 'service national', 'volontariat', 'service civique'],
                'relations-administration' => ['administration', 'recours administratif', 'défenseur des droits', 'defenseur des droits', 'silence de l\'administration', 'démarche administrative', 'demarche administrative'],
                'donnees-image' => ['données personnelles', 'donnees personnelles', 'image', 'vie privée', 'vie privee', 'cnil', 'fichier', 'police judiciaire'],
            ],
            'social-sante' => [
                'aides-sociales' => ['rsa', 'revenu de solidarité active', 'revenu de solidarite active', 'prime d\'activité', 'prime d\'activite', 'aide sociale', 'allocation', 'minimum vieillesse'],
                'chomage' => ['chômage', 'chomage', 'france travail', 'pôle emploi', 'pole emploi', 'allocation chômage', 'allocation chomage', 'are', 'formation', 'reprise d\'activité', 'reprise d\'activite', 'chômage partiel', 'chomage partiel'],
                'securite-sociale' => ['sécurité sociale', 'securite sociale', 'assurance maladie', 'remboursement', 'carte vitale', 'mutuelle', 'complémentaire santé', 'complementaire sante', 'affiliation'],
                'droits-patients' => ['patient', 'dossier médical', 'dossier medical', 'préjudice médical', 'prejudice medical', 'don du sang', 'don d\'organe', 'soins'],
                'sante' => ['santé', 'sante', 'vaccination', 'vaccin', 'addiction', 'covid', 'contraception', 'ivg', 'grossesse', 'hospitalisation', 'soins à domicile', 'soins a domicile'],
                'invalidite' => ['invalidité', 'invalidite', 'pension d\'invalidité', 'pension d\'invalidite', 'incapacité', 'incapacite'],
                'handicap' => ['handicap', 'aah', 'aeeh', 'pch', 'mdph', 'allocation aux adultes handicapés', 'allocation aux adultes handicapes'],
                'personnes-agees' => ['personne âgée', 'personne agee', 'personnes âgées', 'personnes agees', 'apa', 'ehpad', 'maison de retraite', 'allocation personnalisée d\'autonomie', 'allocation personnalisee d\'autonomie'],
                'hebergement-social' => ['hébergement social', 'hebergement social', 'urgence sociale', 'hébergement d\'urgence', 'hebergement d\'urgence', 'sans domicile', 'sdf'],
            ],
            'famille-scolarite' => [
                'couple' => ['union libre', 'pacs', 'mariage', 'divorce', 'séparation de corps', 'separation de corps', 'conjoint', 'époux', 'epoux'],
                'enfant' => ['adoption', 'naissance', 'filiation', 'autorité parentale', 'autorite parentale', 'allocation familiale', 'bafa', 'bafd', 'séparation des parents', 'separation des parents', 'placement d\'un enfant'],
                'scolarite' => ['obligation scolaire', 'école', 'ecole', 'collège', 'college', 'lycée', 'lycee', 'scolarité', 'scolarite', 'bourse de collège', 'bourse de college', 'bourse de lycée', 'bourse de lycee', 'diplôme', 'diplome'],
                'etudes-superieures' => ['études supérieures', 'etudes superieures', 'étudiant', 'etudiant', 'bourse étudiante', 'bourse etudiante', 'parcoursup', 'inscription universitaire', 'erasmus'],
                'protection-personnes' => ['tutelle', 'curatelle', 'protection juridique', 'habilitation familiale', 'disparition', 'enlèvement', 'enlevement'],
                'deces' => ['décès', 'deces', 'obsèques', 'obseques', 'sépulture', 'sepulture', 'rente', 'capital décès', 'capital deces', 'pension de réversion', 'pension de reversion', 'orphelin'],
                'succession' => ['héritage', 'heritage', 'héritier', 'heritier', 'succession', 'donation', 'testament', 'legs'],
            ],
            'travail-formation' => [
                'recrutement' => ['recrutement', 'embauche', 'offre d\'emploi', 'candidature', 'concours', 'contractuel', 'fonctionnaire', 'pacte'],
                'remuneration' => ['rémunération', 'remuneration', 'salaire', 'smic', 'prime', 'traitement', 'indemnité', 'indemnite', 'fiche de paie', 'bulletin de paie'],
                'contrats-carriere' => ['contrat de travail', 'cdd', 'cdi', 'intérim', 'interim', 'apprentissage', 'alternance', 'insertion', 'carrière', 'carriere', 'mobilité', 'mobilite'],
                'conges' => ['congé', 'conge', 'vacances', 'absence', 'autorisation d\'absence', 'jour férié', 'jour ferie', 'rtt'],
                'formation' => ['formation', 'cpf', 'vae', 'stage', 'apprentissage', 'bilan de compétences', 'bilan de competences'],
                'sante-securite-travail' => ['santé au travail', 'sante au travail', 'sécurité au travail', 'securite au travail', 'conditions de travail', 'accident du travail', 'maladie professionnelle', 'arrêt de travail', 'arret de travail', 'handicap'],
                'relations-travail' => ['représentant du personnel', 'representant du personnel', 'cse', 'syndicat', 'grève', 'greve', 'conflit du travail', 'prud\'hommes', 'discipline'],
                'temps-travail' => ['temps de travail', 'durée du travail', 'duree du travail', 'horaire', 'heures supplémentaires', 'heures supplementaires', 'travail de nuit', 'temps partiel', 'astreinte'],
                'quitter-emploi' => ['rupture du contrat', 'licenciement', 'démission', 'demission', 'rupture conventionnelle', 'préavis', 'preavis', 'abandon de poste', 'retraite progressive'],
                'particulier-employeur' => ['particulier employeur', 'aide à domicile', 'aide a domicile', 'assistant maternel', 'assistante maternelle', 'cesu', 'au pair', 'emploi à domicile', 'emploi a domicile'],
                'retraite' => ['retraite', 'pension de retraite', 'âge légal', 'age legal', 'trimestre', 'cnav', 'retraite complémentaire', 'retraite complementaire'],
            ],
        ];

        if (empty($subthemes[$theme][$subtheme])) {
            return false;
        }

        $this->applyKeywordFilter($query, $subthemes[$theme][$subtheme], ['title']);

        $labels = [
            'assurance' => 'Assurance',
            'banque' => 'Banque',
            'consommation' => 'Consommation',
            'epargne' => 'Épargne',
            'organisation-justice' => 'Organisation de la justice',
            'infractions' => 'Infractions',
            'copropriete' => 'Copropriété',
            'urbanisme' => 'Urbanisme',
            'chasse' => 'Chasse',
            'peche' => 'Pêche',
            'armes' => 'Armes',
            'animal-compagnie' => 'Animal de compagnie',
            'sport' => 'Sport',
            'hebergement-touristique' => 'Hébergement touristique',
            'aides-vacances' => 'Aides aux vacances',
            'livret-famille' => 'Livret de famille',
            'passeport' => 'Passeport',
            'carte-identite' => 'Carte d\'identité',
            'elections' => 'Élections',
            'aides-sociales' => 'Aides sociales',
            'chomage' => 'Chômage',
            'securite-sociale' => 'Sécurité sociale',
            'droits-patients' => 'Droits des patients',
            'sante' => 'Santé',
            'invalidite' => 'Invalidité',
            'handicap' => 'Handicap',
            'personnes-agees' => 'Personnes âgées',
            'hebergement-social' => 'Hébergement social',
            'couple' => 'Couple',
            'enfant' => 'Enfant',
            'scolarite' => 'Scolarité',
            'etudes-superieures' => 'Études supérieures',
            'protection-personnes' => 'Protection des personnes',
            'deces' => 'Décès',
            'succession' => 'Succession',
            'recrutement' => 'Recrutement',
            'remuneration' => 'Rémunération',
            'contrats-carriere' => 'Contrats et carrière',
            'conges' => 'Congés',
            'formation' => 'Formation',
            'sante-securite-travail' => 'Santé, sécurité et conditions de travail',
            'relations-travail' => 'Relations individuelles et collectives',
            'temps-travail' => 'Temps de travail',
            'quitter-emploi' => 'Quitter son emploi',
            'particulier-employeur' => 'Particulier employeur',
            'retraite' => 'Retraite',
        ];

        if (!empty($labels[$subtheme])) {
            $db = $this->getDatabase();
            $query->where($db->quoteName('a.title') . ' <> ' . $db->quote($labels[$subtheme]));
        }

        return true;
    }

    private function getAssociationThemes(): array
    {
        return [
            ['title' => 'Création', 'alias' => 'creation'],
            ['title' => 'Fonctionnement', 'alias' => 'fonctionnement'],
            ['title' => 'Financement', 'alias' => 'financement'],
            ['title' => 'Bénévoles', 'alias' => 'benevoles'],
            ['title' => 'Fiscalité', 'alias' => 'fiscalite'],
            ['title' => 'Manifestations', 'alias' => 'manifestations'],
        ];
    }

    private function getAssociationItemsByTheme(string $themeAlias, int $limit): array
    {
        $featured = [
            'creation' => [
                'je-cree-une-association',
                'declaration-initiale-dune-association',
                'redaction-des-statuts-dune-association',
                'identification-et-immatriculation-dune-association',
                'comment-savoir-si-un-nom-dassociation-est-deja-utilise',
            ],
            'fonctionnement' => [
                'modification-des-statuts-dune-association',
                'instances-dirigeantes-dune-association',
                'dissolution-dune-association',
                'assurance-des-associations',
                'peut-on-mettre-temporairement-une-association-en-sommeil',
            ],
            'financement' => [
                'subventions-versees-aux-associations',
                'association-evolution-des-ressources',
                'dons-donations-et-legs-au-benefice-dune-association',
                'cotisations-a-une-association',
                'frais-engages-par-les-benevoles-dune-association-quelle-fiscalite',
            ],
            'benevoles' => [
                'agrement-de-service-civique-et-de-volontariat-associatif',
                'engagement-de-service-civique',
                'conge-de-representation-pour-un-representant-dune-association',
                'titre-restaurant-du-benevole',
                'un-benevole-ou-un-salarie-peut-il-utiliser-son-vehicule-personnel-pour-les-besoins-dune-association',
            ],
            'fiscalite' => [
                'impot-sur-le-revenu-dons-aux-associations-et-organismes-dinteret-general',
                'frais-engages-par-les-benevoles-dune-association-quelle-fiscalite',
                'dans-quels-cas-une-association-doit-recourir-a-un-commissaire-aux-comptes',
            ],
            'manifestations' => [
                'vide-greniers-ou-brocante-organises-par-une-association',
            ],
        ];

        if (!empty($featured[$themeAlias])) {
            $rows = $this->getAssociationItemsByAliases($featured[$themeAlias], $limit);

            if (count($rows) >= $limit) {
                return $rows;
            }
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('a.id, a.title, a.alias')
            ->from($db->quoteName('#__comarquage_items', 'a'))
            ->where($db->quoteName('a.universe') . ' = ' . $db->quote('associations'))
            ->where($db->quoteName('a.state') . ' = 1')
            ->order($db->quoteName('a.title') . ' ASC');

        $this->applyAssociationBaseScope($query);
        $this->applyAssociationThemeFilter($query, $themeAlias);
        $this->applyBestAliasFilter($query);

        return $this->panelRows($db->setQuery($query, 0, $limit)->loadAssocList() ?: []);
    }

    private function getAssociationItemsByAliases(array $aliases, int $limit): array
    {
        $db = $this->getDatabase();
        $quotedAliases = array_map([$db, 'quote'], $aliases);
        $orderCases = [];

        foreach ($aliases as $index => $alias) {
            $orderCases[] = 'WHEN ' . $db->quoteName('a.alias') . ' = ' . $db->quote($alias) . ' THEN ' . (int) $index;
        }

        $query = $db->getQuery(true)
            ->select('a.id, a.title, a.alias')
            ->from($db->quoteName('#__comarquage_items', 'a'))
            ->where($db->quoteName('a.universe') . ' = ' . $db->quote('associations'))
            ->where($db->quoteName('a.state') . ' = 1')
            ->where($db->quoteName('a.alias') . ' IN (' . implode(',', $quotedAliases) . ')')
            ->order('CASE ' . implode(' ', $orderCases) . ' ELSE 999 END ASC');

        $this->applyBestAliasFilter($query);

        return $this->panelRows($db->setQuery($query, 0, $limit)->loadAssocList() ?: []);
    }

    private function applyAssociationBaseScope($query): void
    {
        $this->applyKeywordFilter($query, [
            'association',
            'associatif',
            'associative',
            'bénévole',
            'benevole',
            'subvention',
            'cotisation',
            'statuts',
            'assemblée générale',
            'assemblee generale',
            'dirigeant',
            'service civique',
        ], ['title']);
    }

    private function applyAssociationThemeFilter($query, string $theme): bool
    {
        $themes = [
            'creation' => ['déclaration initiale', 'declaration initiale', 'créer', 'creer', 'constituer', 'statuts', 'identification', 'immatriculation', 'nom d\'association', 'siège', 'siege'],
            'fonctionnement' => ['fonctionnement', 'statuts', 'assemblée', 'assemblee', 'dirigeant', 'membre', 'responsable', 'sommeil', 'dissolution', 'assurance', 'agrément', 'agrement'],
            'financement' => ['subvention', 'don', 'donation', 'legs', 'cotisation', 'ressources', 'financement', 'commissaire aux comptes'],
            'benevoles' => ['bénévole', 'benevole', 'volontariat', 'service civique', 'congé de représentation', 'conge de representation', 'titre-restaurant', 'frais engagés', 'frais engages'],
            'fiscalite' => ['fiscalité', 'fiscalite', 'impôt', 'impot', 'taxe', 'réduction d\'impôt', 'reduction d\'impot', 'reçu fiscal', 'recu fiscal', 'commissaire aux comptes'],
            'manifestations' => ['manifestation', 'événement', 'evenement', 'vide-greniers', 'brocante', 'spectacle', 'guso', 'débit de boissons', 'debit de boissons'],
        ];

        if (empty($themes[$theme])) {
            return false;
        }

        $this->applyKeywordFilter($query, $themes[$theme], ['title']);

        return true;
    }

    private function applyKeywordFilter($query, array $terms, array $columns = ['title', 'introtext', 'body']): void
    {
        $db = $this->getDatabase();
        $conditions = [];

        foreach ($terms as $term) {
            $like = $db->quote('%' . $term . '%');

            foreach ($columns as $column) {
                if (!in_array($column, ['title', 'introtext', 'body'], true)) {
                    continue;
                }

                $conditions[] = $db->quoteName('a.' . $column) . ' LIKE ' . $like;
            }
        }

        if ($conditions) {
            $query->where('(' . implode(' OR ', $conditions) . ')');
        }
    }

    private function applyBestAliasFilter($query): void
    {
        $db = $this->getDatabase();
        $currentScore = "CHAR_LENGTH(COALESCE(NULLIF(a.body, ''), NULLIF(a.introtext, ''), a.title))";
        $duplicateScore = "CHAR_LENGTH(COALESCE(NULLIF(dup.body, ''), NULLIF(dup.introtext, ''), dup.title))";
        $query->where(
            "(a.alias = '' OR NOT EXISTS (" .
            "SELECT 1 FROM " . $db->quoteName('#__comarquage_items', 'dup') . " " .
            "WHERE dup.universe = a.universe " .
            "AND dup.state = 1 " .
            "AND dup.alias = a.alias " .
            "AND dup.id <> a.id " .
            "AND (" . $duplicateScore . " > " . $currentScore . " OR (" . $duplicateScore . " = " . $currentScore . " AND dup.id < a.id))" .
            "))"
        );
    }

    private function getPanelItemsByText(array $needles, int $limit): array
    {
        $db = $this->getDatabase();
        $universe = $this->rubrique;
        $query = $db->getQuery(true)
            ->select('a.id, a.title, a.alias')
            ->from($db->quoteName('#__comarquage_items', 'a'))
            ->where($db->quoteName('a.universe') . ' = :universe')
            ->where($db->quoteName('a.state') . ' = 1')
            ->bind(':universe', $universe)
            ->order($db->quoteName('a.title') . ' ASC');

        $conditions = [];
        foreach ($needles as $index => $needle) {
            $key = ':needle' . $index;
            $like = '%' . $needle . '%';
            $conditions[] = $db->quoteName('a.body') . ' LIKE ' . $key;
            $query->bind($key, $like);
        }

        $query->where('(' . implode(' OR ', $conditions) . ')');

        if (in_array('question-reponse', $needles, true)) {
            $question = '%?%';
            $query->where($db->quoteName('a.title') . ' LIKE :questionMark')
                ->bind(':questionMark', $question);
        }

        return $this->panelRows($db->setQuery($query, 0, $limit)->loadAssocList() ?: []);
    }

    private function getQuestionItems(int $limit): array
    {
        $db = $this->getDatabase();
        $universe = $this->rubrique;
        $question = '%?%';
        $query = $db->getQuery(true)
            ->select('a.id, a.title, a.alias')
            ->from($db->quoteName('#__comarquage_items', 'a'))
            ->where($db->quoteName('a.universe') . ' = :universe')
            ->where($db->quoteName('a.state') . ' = 1')
            ->where($db->quoteName('a.title') . ' LIKE :question')
            ->where($db->quoteName('a.title') . ' NOT LIKE ' . $db->quote('[%]'))
            ->bind(':universe', $universe)
            ->bind(':question', $question)
            ->order($db->quoteName('a.title') . ' ASC');

        return $this->panelRows($db->setQuery($query, 0, $limit)->loadAssocList() ?: []);
    }

    private function readHomePanelsCache(): ?array
    {
        $file = $this->getHomePanelsCacheFile();

        if (!is_file($file)) {
            return null;
        }

        $params = ComponentHelper::getParams('com_comarquage');
        $ttl = max(1, (int) $params->get('cache_hours', 24)) * 3600;

        if ((time() - (int) filemtime($file)) > $ttl) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function writeHomePanelsCache(array $panels): void
    {
        $directory = $this->getCacheDirectory();

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return;
        }

        @file_put_contents($this->getHomePanelsCacheFile(), json_encode($panels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function getHomePanelsCacheFile(): string
    {
        // Panels contain routed URLs, so they must not be shared across languages or menu contexts.
        $app = Factory::getApplication();
        $context = [$this->rubrique, $app->getLanguage()->getTag(), $app->getInput()->getInt('Itemid'), $app->getIdentity()->getAuthorisedViewLevels()];
        return $this->getCacheDirectory() . '/home_v10_' . hash('sha256', json_encode($context)) . '.json';
    }

    private function getListCacheKey(string $type): string
    {
        $input = Factory::getApplication()->getInput();
        $parts = [
            'list_schema=20260529-life-events-v1',
            $type,
            $this->rubrique,
            trim($input->getString('q', '')),
            trim($input->getString('theme', '')),
            trim($input->getString('subtheme', '')),
        ];

        if ($type !== 'total') {
            $parts[] = (string) $this->getStart();
            $parts[] = (string) $this->getLimit();
        }

        return sha1(implode('|', $parts));
    }

    private function readListCache(string $key): mixed
    {
        $file = $this->getListCacheFile($key);

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

    private function writeListCache(string $key, mixed $value): void
    {
        $directory = $this->getCacheDirectory();

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return;
        }

        @file_put_contents($this->getListCacheFile($key), json_encode(['value' => $value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function getListCacheFile(string $key): string
    {
        return $this->getCacheDirectory() . '/list_' . $key . '.json';
    }

    private function waitForListCache(string $key): mixed
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            usleep(100000);
            $cached = $this->readListCache($key);

            if ($cached !== null) {
                return $cached;
            }
        }

        return null;
    }

    private function openListCacheLock(string $key): mixed
    {
        $directory = $this->getCacheDirectory();

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return false;
        }

        $handle = @fopen($directory . '/list_' . $key . '.lock', 'c');

        if (!$handle) {
            return false;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }

        return $handle;
    }

    private function closeListCacheLock(mixed $handle): void
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

    private function panelRows(array $rows): array
    {
        return array_map(
            fn (array $row): array => [
                'title' => $row['title'],
                'url' => \Joomla\Component\Comarquage\Site\Service\RouteHelper::getFicheRoute($row['alias'] ?: $row['id'], $this->rubrique),
            ],
            $rows
        );
    }
}


