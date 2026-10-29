# English guide — Comarquage

![Comarquage — French public services on your Joomla website](comarquage-banner-en.svg)

[Home](../README.md) · [Français](README.fr.md)

## Purpose

Comarquage displays French administrative information from Service-Public / DILA Open Data within Joomla. Visitors browse topics, read records and search the local database. Public pages do not download or parse remote XML.

The package retains the historical `pkg_comarquage` and `com_comarquage` identifiers so an existing installation can be upgraded in place.

## Who is Comarquage for?

Comarquage is designed for **French municipalities, intermunicipal authorities and other local public bodies** using Joomla, and for their web agencies and integrators. It provides information for three audiences:

- **Individuals**: family, identity documents, housing, work, health and everyday life.
- **Associations**: creation, governance, volunteering, funding and obligations.
- **Businesses**: starting a business, management, taxation, employment and formalities.

Each section can be enabled separately and has its own menu item type. The extension publishes information and links to official services; it does not itself accept administrative applications.

**Geographic scope and language:** this extension is intended for organisations serving the public in France. Its official administrative records cover French procedures and remain in French. English extension language files and documentation do not translate the imported Service-Public content.

## Requirements and installation

Joomla 6.x, PHP 8.3+, a Joomla-supported MySQL/MariaDB database, PHP DOM, mbstring and ZIP. Allow at least 256 MiB PHP memory; 512 MiB is recommended for large imports. The server needs temporary disk space and outbound HTTPS access to DILA.

1. Back up the site and database before upgrading.
2. Download `pkg_comarquage-1.0.3.zip` from the GitHub release assets.
3. Upload it through **System → Install → Extensions**.
4. Open **Components → Comarquage → Options** and check the enabled audiences and sources.
5. Run the import. Keep the dashboard open until it finishes. If interrupted, run it again: it resumes from the last committed batch.
6. Create menu items for Comarquage's Individuals, Associations, Businesses or Search views.

On a multilingual site, create menu items for each language and assign their language in Joomla. Internal links prefer a menu in the current language, then an All Languages menu. Nested menu items are supported.

On first installation the package enables its task and Smart Search plugins. Upgrades preserve plugin states and existing settings. Create scheduled tasks separately from the dashboard.

## Current sources

The [historical co-branding service has ended](https://www.service-public.gouv.fr/P10004), but the information remains available as Open Data.

| Audience | Source |
| --- | --- |
| Individuals | Official COPERIA 3.5 Individuals archive |
| Associations | The same archive, restricted to official theme N31931 |
| Businesses | Official COPERIA 3.5 Professionals archive |

Recommended addresses are supplied in the configuration. The dashboard's recommended-sources button restores them. Known legacy URLs are normalized to `service-public.gouv.fr`; the old separate associations feed is replaced by the current Individuals subtree.

Remote mode accepts official DILA/data.gouv.fr HTTPS hosts. Local mode is an administrator testing facility for a server-accessible XML file, ZIP or XML directory. Files must use the expected schema; this is not a universal XML importer.

## Data refresh

Use the dashboard's scheduled-task repair/create action to set up daily imports and cache cleaning. Review their schedules under **System → Manage → Scheduled Tasks**.

Joomla's task scheduler must actually run, either through Joomla's supported scheduler mechanisms or a server job executing:

```sh
php /path/to/site/cli/joomla.php scheduler:run
```

Imports are protected against concurrent execution. Time/file limits save a partial status and cursor. The next execution resumes that work. Records missing from a new snapshot are unpublished only after the snapshot has been completely and successfully processed. Errors retain previously committed content and are logged.

The import date is separate from the record's editorial update date. The attribution footer identifies the source file and these dates. An “unchanged” state means that the remote archive has not changed since the last complete import.

## Search and presentation

The component's local search index is updated during imports. Joomla **Smart Search** also needs Joomla's indexer to run after imports, manually or through a separate schedule. Withdrawn records are disabled in existing search results; reindexing refreshes titles and content of other records.

Several visual themes are included. Administrators with component configuration permission can save customized copies or import/export JSON themes. Joomla template overrides remain available. Administrative source content remains French, including when the Joomla administration language is English.

The municipality name and INSEE code provide local context. Comarquage does not promise automatic localization of every procedure or full synchronization of the local public-administration directory.

### Test a municipality and its menus

In the options, enter the **INSEE municipality code**, the municipality name, and enable local customisation. Example: **Béthune — 62119** (not its postal code 62400). Click **Refresh municipal services** on the dashboard to query the official DILA directory. A valid code format alone does not prove that a municipality exists.

The municipality name and code appear on all three sections. A local block selects services located in that municipality for each audience and links to their official directory records. This does not resolve the jurisdiction of services located elsewhere or modify national procedure texts.

The feature is optional. Imports refresh contacts at most once daily; the manual button forces a refresh. Visitor pages make no live directory API requests. Failed refreshes preserve previous contacts and are logged.

Create four published Public menu items in **Menus → Main Menu → New**, using Comarquage's **Individuals**, **Associations**, **Businesses** and **Search** types. Publish the corresponding Menu module when needed by your template. Menus are managed by Joomla and are not automatically added to every installation.

## Extension updates

The package update server is:

`https://github.com/YGomiero/comarquage-joomla/releases/latest/download/update.xml`

Joomla checks new versions under **System → Update → Extensions**. The feed references an immutable versioned ZIP and its SHA-256 digest. Published release files are not replaced; fixes receive a new version. Code updates and daily data imports are separate operations.

## Attribution, limitations and troubleshooting

DILA data is licensed under [Open Licence 2.0](https://www.etalab.gouv.fr/licence-ouverte-open-licence/). Preserve the attribution, complete download URL, file name and file date. Comarquage's footer provides these for remote imports. Follow the publisher's dataset discussions for announced corrections and changes.

Rendering supports common COPERIA 3.5 publications, text, lists, tables, resources and conditional paths. It is not a certified reproduction of every official portal feature. An original-record link is available for verification.

- **Partial import:** run again or let the scheduler continue; this alone is not a reason to purge data.
- **Network error:** check DNS, certificates, outbound HTTPS and DILA availability.
- **No results:** verify enabled audiences, complete the import and clear the component cache.
- **Old Smart Search results:** run Joomla's indexer after importing.
- **Missing code update:** check that the Comarquage update site is enabled, then refresh Joomla's update check.

Uninstalling the package removes the component, plugins and imported-data tables. Back up any required data before uninstalling. Request help through [GitHub issues](https://github.com/YGomiero/comarquage-joomla/issues), including Joomla/PHP versions and an error message stripped of private information.
