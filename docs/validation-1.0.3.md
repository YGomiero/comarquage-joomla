# Comarquage 1.0.3 validation

Validated on 29 October 2026, after the JED language-file review.

## Package checks

- All 18 shipped INI files pass strict assignment, duplicate-key, UTF-8 and PHP INI parsing checks (normal and raw modes).
- PHP and XML translation references are checked separately for each client and plugin, in French and English.
- The new language check rejects the four malformed English assignments in the original 1.0.2 sources.
- All 25 existing regression checks pass, as does PHP syntax validation.
- Package metadata, XML/JSON, the update feed checksum and reproducible ZIP generation pass.

## JED Checker

The official JED Checker 2.4.4 tagged source was run on the extracted installable package with unchanged rules. On Windows, all INI/PHP files were explicitly enumerated for the language rule to avoid the checker's path-discovery issue.

The 12 rules report **0 errors**, **0 compatibility findings** and **0 language findings**. Seven warnings remain and were reviewed:

| Rule | File | Reason |
| --- | --- | --- |
| Encoding (3) | `site/src/Service/RouteHelper.php` | Base64url encoding/decoding of validated internal return URLs; no encoded executable code. |
| JAMSS | `site/src/Service/RouteHelper.php` | The same return-URL encoding. |
| JAMSS | `admin/src/Service/SourceStorageService.php` | ZIP magic-byte signature. |
| JAMSS | `site/src/Service/ContentRendererService.php` | Regular expression removing control characters from URLs. |
| JAMSS | `site/src/Model/BaseRubriqueModel.php` | The French word `vandalisme` in the search classification synonyms. |

These warnings remain available for the JED editor's review; this report does not imply JED approval.

## Local Joomla validation

- Joomla 6.2.0 / PHP 8.3.30: upgrade from 1.0.2 through Joomla's native installer; all four extensions report 1.0.3.
- A backup was taken first. A repeated installation with Comarquage scheduled imports temporarily paused preserves all seven component tables, extension settings and four menus exactly. Task states were restored.
- Administrator dashboard checked in French and English: imported-record counts and Actions heading render correctly. The English configuration shows the Service-Public classic theme label.
- Individuals, Associations and Companies pages checked in both interface languages.
- A real search for `passeport` returns 391 results; a record opens and its return link restores the results. English search labels render correctly. Official DILA content remains in French.
- The administrator's original language preference was restored after the test.

Package SHA-256: `9a120135869d59988a4a6da144495754fd0c9033347311dc6ac40092242e28a2`.
