# Changelog

## 1.0.3 — 2026-10-29

- Fix malformed English INI assignments in the component language and system language files.
- Translate the dashboard Actions heading and the Service-Public classic theme option.
- Validate all 18 INI files with strict syntax and both PHP parsing modes, and check translation references separately for each client and plugin.

FR : correction des fichiers anglais signalés lors de la revue JED, des libellés Actions et du thème Service-Public classique ; contrôle automatique renforcé des traductions.

## 1.0.2 — 2026-10-25

- Route menu links in the visitor's language, including nested menus and access restrictions.
- Isolate cached quick links by language, menu and access context.
- Keep attribution links readable with municipal templates.

FR : correction des menus multilingues et imbriqués, des liens d'accès rapides en cache et du contraste des liens de source. Validation sur la démonstration municipale Civora.

## 1.0.1 — 2026-10-25

- Correct the update feed client to match Joomla's installed package identity, so the extension updater can discover new releases.
- Make ZIP metadata deterministic across Windows and Linux builds.

FR : correction de la détection des mises à jour du package par Joomla et des métadonnées ZIP pour des builds identiques sous Windows et Linux.

## 1.0.0 — 2026-10-25

- First maintained Comarquage package, based on the local 0.2.32 component.
- Unified component, scheduled import and Smart Search installation.
- Joomla update server, deterministic ZIP build and SHA-256 release checksums.
- Complete author, license, support and version metadata; French/English guides.
- Current DILA domain and COPERIA 3.5 feeds; Associations now use the maintained Individuals subtree.
- Resumable imports, atomic locking and withdrawal reconciliation after complete snapshots.
- Bounded XML-only archive extraction, XML entity rejection and stricter HTML filtering.
- Portable dashboard import without shell execution; administrator permission checks.
- Source-file provenance and record date separated from the local import date.
- Corrected Smart Search route arguments and unpublished-record filtering.
- Daily scheduler defaults; original vector artwork and cleaned package structure.

FR : première version maintenue ; reprise des imports, sources actuelles, associations à jour, mises à jour Joomla, documentation bilingue et durcissement du traitement des données.

- Contexte communal INSEE : import de l’annuaire DILA, contacts adaptés aux trois publics et conservation du dernier résultat valide.
