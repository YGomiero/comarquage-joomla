# Comarquage — Service-Public Open Data

![Comarquage — Service-Public Open Data](docs/comarquage-banner.svg)

**Les démarches administratives de Service-Public / DILA dans Joomla.**

[Documentation française](docs/README.fr.md) · [English documentation](docs/README.en.md) · [Téléchargements / Downloads](https://github.com/YGomiero/comarquage-joomla/releases) · [Support](https://github.com/YGomiero/comarquage-joomla/issues)

Comarquage importe les données ouvertes officielles dans votre base Joomla, puis affiche les fiches, les thèmes et la recherche sur votre site. Il réunit un composant, un plugin de tâches planifiées et un plugin de recherche avancée dans un seul package.

Comarquage imports official French public-service Open Data into Joomla and provides topic browsing, record pages and local search. One package includes the component, scheduled import and Smart Search plugins.

## À qui s’adresse Comarquage ?

L’extension s’adresse aux **mairies, intercommunalités et autres organismes publics locaux** disposant d’un site Joomla, ainsi qu’aux agences et intégrateurs qui les accompagnent. Elle permet de proposer un espace de démarches aux trois publics :

- **Particuliers** : famille, papiers, logement, travail, santé et vie quotidienne.
- **Associations** : création, fonctionnement, bénévolat, financement et obligations.
- **Entreprises** : création, gestion, fiscalité, emploi et formalités professionnelles.

Les trois rubriques peuvent être activées séparément et possèdent leurs propres liens de menu. Comarquage diffuse l’information et renvoie vers les services officiels pour effectuer les démarches ; ce n’est pas un téléservice de dépôt de dossier.

## Who is Comarquage for?

Comarquage is designed for **French municipalities, intermunicipal authorities and other local public bodies** using Joomla, and for their web agencies and integrators. It provides information for three audiences:

- **Individuals**: family, identity documents, housing, work, health and everyday life.
- **Associations**: creation, governance, volunteering, funding and obligations.
- **Businesses**: starting a business, management, taxation, employment and formalities.

Each section can be enabled separately and has its own menu item type. The extension publishes information and links to official services; it does not itself accept administrative applications.

## Compatibility

- Joomla **6.x**, PHP **8.3+**, MySQL/MariaDB supported by Joomla.
- PHP DOM, mbstring, ZIP and an HTTPS HTTP transport.
- French and English extension language files and documentation. Official administrative content remains in French.
- Joomla 6.2 / PHP 8.3 is the local validation environment. Other Joomla major versions and PostgreSQL are not advertised as supported.

## Installation

Install **`pkg_comarquage-1.0.1.zip`** from the release assets in Joomla's extension installer. GitHub's automatically generated source ZIP is for developers and is not the installable package.

Open **Components → Comarquage**, run the first import and create menu items for Individuals, Associations, Businesses or Search. Leave the import page open until completion; an interrupted import resumes from its saved position.

Updates are delivered through Joomla's normal extension updater. Daily data refresh is a separate scheduled task; installing a code update does not itself refresh the DILA data.

## Sources and maintenance

The former co-branding service has ended. Comarquage reuses the current **COPERIA 3.5 Open Data feeds**, identified through the [DILA reuse page](https://www.service-public.gouv.fr/P10004) and [data.gouv.fr](https://www.data.gouv.fr/datasets/fiches-pratiques-et-ressources-de-service-public-gouv-fr-particuliers).

Associations are selected from the current Individuals dataset, under official theme **N31931**. The historical separate associations archive is no longer the default: it was still dated 20 May 2026 when checked on 25 October 2026.

The extension displays the source, download URL, file name, source-file date, local import date and original record link. Data publishers can change the schema or remove records; follow their dataset discussions and keep Comarquage updated.

## Captures / Screenshots

[Voir les trois rubriques, les contacts locaux et les menus / View the demo screenshots](docs/SCREENSHOTS.md).

## Development

```sh
php tests/run.php
python tools/audit.py
python tools/build.py
```

Builds are deterministic. The resulting ZIP, `update.xml` and `SHA256SUMS` are written to `dist/`. The update feed includes the ZIP's SHA-256 digest. [Architecture](docs/ARCHITECTURE.md) · [Release process](docs/RELEASING.md) · [Changes](CHANGELOG.md).

## License and support

Extension code and original artwork: **GNU GPL version 2 or later**. DILA content: **Licence Ouverte 2.0**, with its own attribution requirements. Joomla and Service-Public are their respective owners' trademarks. Comarquage is an independent extension and does not imply official endorsement.

Use [GitHub issues](https://github.com/YGomiero/comarquage-joomla/issues) for support. Do not post passwords, private configuration or personal data. See [SECURITY.md](SECURITY.md) and [THIRD-PARTY.md](THIRD-PARTY.md).
