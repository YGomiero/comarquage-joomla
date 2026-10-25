# Guide français — Comarquage

[Accueil](../README.md) · [English](README.en.md)

## Ce que fait l’extension

Comarquage rediffuse les fiches administratives ouvertes de Service-Public / DILA sur un site Joomla. Le visiteur parcourt les thèmes, consulte une fiche ou effectue une recherche locale. Les fiches sont stockées dans la base Joomla : leur affichage ne déclenche pas de téléchargement XML.

Le package conserve les identifiants historiques `pkg_comarquage` et `com_comarquage`, ce qui permet la mise à niveau d’une installation existante sans créer un second composant.

## À qui s’adresse Comarquage ?

L’extension s’adresse aux **mairies, intercommunalités et autres organismes publics locaux** disposant d’un site Joomla, ainsi qu’aux agences et intégrateurs qui les accompagnent. Elle permet de proposer un espace de démarches aux trois publics :

- **Particuliers** : famille, papiers, logement, travail, santé et vie quotidienne.
- **Associations** : création, fonctionnement, bénévolat, financement et obligations.
- **Entreprises** : création, gestion, fiscalité, emploi et formalités professionnelles.

Les trois rubriques peuvent être activées séparément et possèdent leurs propres liens de menu. Comarquage diffuse l’information et renvoie vers les services officiels pour effectuer les démarches ; ce n’est pas un téléservice de dépôt de dossier.

## Prérequis et installation

Joomla 6.x, PHP 8.3 ou supérieur, base MySQL/MariaDB compatible Joomla, extensions PHP DOM, mbstring et ZIP. Prévoir 256 Mo de mémoire PHP au minimum, 512 Mo conseillés pour les gros imports, de l’espace temporaire et une connexion HTTPS sortante vers les sources DILA.

1. Sauvegarder le site et sa base avant une mise à niveau.
2. Télécharger le fichier `pkg_comarquage-1.0.0.zip` joint à la release GitHub.
3. Dans Joomla, ouvrir **Système → Installation → Extensions**, puis déposer ce ZIP.
4. Ouvrir **Composants → Comarquage → Paramètres** pour vérifier les sources et les univers activés.
5. Lancer **Relancer l’import**. Garder la page ouverte jusqu’à la fin. En cas d’arrêt, relancer : le traitement reprend au dernier lot validé.
6. Créer un lien de menu de type **Comarquage → Particuliers**, **Associations**, **Entreprises** ou **Recherche**.

Le package installe et active les plugins de tâches planifiées et de recherche avancée lors d’une première installation. Une mise à jour préserve leurs états et les paramètres existants. Les tâches automatiques doivent être créées depuis le tableau de bord.

## Sources actuelles

Le [co-marquage historique a pris fin](https://www.service-public.gouv.fr/P10004). Les données restent réutilisables en Open Data.

| Vue | Source utilisée |
| --- | --- |
| Particuliers | Archive officielle COPERIA 3.5 Particuliers |
| Associations | Même archive, sélection du thème officiel N31931 |
| Entreprises | Archive officielle COPERIA 3.5 Professionnels |

Les adresses recommandées sont fournies dans les paramètres. Le bouton **Appliquer les sources recommandées** rétablit ces adresses. Les anciennes URL connues sont normalisées vers le domaine `service-public.gouv.fr`. L’ancienne archive Associations est remplacée par la rubrique actuelle du flux Particuliers.

Le mode distant accepte les hôtes officiels HTTPS DILA et data.gouv.fr. Le mode local est réservé aux administrateurs qui testent un fichier XML, un ZIP ou un dossier XML accessible au serveur. Le fichier importé doit suivre le schéma attendu : ce mode n’est pas un importeur XML universel.

## Actualisation des données

Dans le tableau de bord, utiliser **Réparer / créer les tâches planifiées** pour créer les imports quotidiens des trois univers et le nettoyage du cache. Vérifier ensuite **Système → Gestion → Tâches planifiées** et ajuster leurs horaires si nécessaire.

Le planificateur Joomla doit réellement être exécuté, par le mécanisme de tâches de Joomla ou une tâche serveur lançant :

```sh
php /chemin/du/site/cli/joomla.php scheduler:run
```

L’import est protégé contre les exécutions concurrentes. Une limite de temps ou de fichiers enregistre un état partiel et un curseur. Le passage suivant reprend ce traitement. Les fiches absentes d’un nouvel instantané sont dépubliées uniquement après la lecture complète et valide de celui-ci. Une erreur conserve les données déjà validées et reste visible dans le journal.

La date d’import n’est pas la date de mise à jour de la fiche. Le pied de fiche distingue ces informations et indique le fichier source. Un état « inchangé » signifie que le fichier distant n’a pas changé depuis le dernier import complet.

## Recherche et présentation

La recherche du composant est alimentée pendant les imports. Pour la **recherche avancée Joomla**, lancer aussi l’indexeur Joomla après un import ou le programmer séparément. Les fiches retirées sont désactivées dans les résultats existants ; la réindexation actualise les titres et le texte des autres fiches.

Plusieurs thèmes visuels sont fournis. Un administrateur autorisé à configurer le composant peut créer une copie personnalisée ou importer/exporter un thème JSON. Les substitutions de vues Joomla restent possibles. Les informations administratives officielles restent en français, même si l’administration Joomla est en anglais.

### Tester une commune et ses menus

Dans les paramètres, saisir le **code INSEE** (pas le code postal), le nom de commune et activer la personnalisation locale. Exemple vérifiable : **Béthune — 62119** ; son code postal 62400 n’est pas le code à saisir. Puis cliquer sur **Actualiser les services de la commune** dans le tableau de bord. Cette vérification interroge l’API officielle de l’annuaire DILA ; un code de bon format ne garantit pas à lui seul l’existence d’une commune.

Le nom et le code apparaissent en tête des trois rubriques. Un bloc présente une sélection de services implantés dans cette commune, adaptée aux particuliers, associations ou entreprises, avec des liens vers leurs fiches officielles. Les coordonnées restent consultables sur l’annuaire officiel. La compétence territoriale des services situés ailleurs n’est pas calculée. Les textes nationaux des démarches restent inchangés.

Le contexte est optionnel. Les imports renouvellent aussi les contacts, au plus une fois par jour ; le bouton manuel force l’actualisation. Aucune requête vers l’annuaire n’est faite lors de la visite d’une page. Une erreur conserve le dernier annuaire valide et est inscrite au journal.

Créer dans **Menus → Menu principal → Nouveau** quatre liens de type Comarquage : **Particuliers**, **Associations**, **Entreprises**, **Recherche**. Publier les liens avec l’accès Public ; publier un module Menu associé si le template n’affiche pas déjà ce menu. Les liens restent gérés par Joomla, et ne sont pas créés automatiquement sur chaque installation.


## Mise à jour de l’extension

Le serveur de mise à jour du package est :

`https://github.com/YGomiero/comarquage-joomla/releases/latest/download/update.xml`

Joomla détecte les nouvelles versions dans **Système → Mise à jour → Extensions**. Le flux désigne un ZIP versionné et son empreinte SHA-256. Un package publié n’est pas remplacé : une correction donne lieu à une nouvelle version.

## Attribution, limites et dépannage

Les contenus DILA sont sous [Licence Ouverte 2.0](https://www.etalab.gouv.fr/licence-ouverte-open-licence/). Conserver la source, l’URL complète de téléchargement, le nom et la date du fichier. Le pied de page Comarquage fournit ces indications pour les imports distants. Consulter les discussions des jeux de données DILA pour suivre les corrections et changements annoncés.

Le rendu couvre les publications, textes, listes, tableaux, ressources et parcours conditionnels courants de COPERIA 3.5. Il ne constitue pas une reproduction certifiée de l’ensemble du portail officiel. Pour une information décisive, le lien vers la fiche officielle reste disponible.

- **Import partiel :** relancer ou laisser le planificateur reprendre ; ne pas purger les données pour cette seule raison.
- **Erreur réseau :** vérifier DNS, certificats, accès HTTPS sortant et disponibilité DILA.
- **Aucun résultat :** vérifier les univers, terminer l’import et vider le cache du composant.
- **Recherche avancée ancienne :** lancer l’indexeur Joomla après l’import.
- **Mise à jour absente :** vérifier que le serveur Comarquage est activé puis actualiser la recherche de mises à jour Joomla.

La désinstallation du package supprime le composant, ses plugins et les tables de données importées. Sauvegarder les données utiles avant de désinstaller. Pour le support, ouvrir une [issue GitHub](https://github.com/YGomiero/comarquage-joomla/issues) avec les versions Joomla/PHP et un message d’erreur expurgé des informations privées.
