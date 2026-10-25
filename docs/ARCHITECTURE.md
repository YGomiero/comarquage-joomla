# Architecture / architecture

## Structure

- `component/admin`: installation SQL, configuration, dashboard, import pipeline.
- `component/site`: public MVC views, navigation, renderer and local cache.
- `component/media`: CSS, browser interaction and original vector icons.
- `plugins/task/comarquageimport`: Joomla Scheduler routines.
- `plugins/finder/comarquage`: Joomla Smart Search adapter.
- `script.php`: package prerequisites and preservation of existing settings.
- `tools/build.py`: deterministic ZIPs, update feed and checksums.

## Import contract

The source is an official HTTPS DILA endpoint or an explicitly configured local test path. `SourceStorageService` extracts XML only, rejects path traversal and limits archive entry count, individual size and total expanded size. Generated files live in a site-specific temporary directory. XML document type declarations are rejected; network entity resolution is disabled.

`ImportLockService` uses an operating-system file lock. `ImportService` persists the downloaded snapshot, cursor and seen identifiers in the feed's `params` field. Record writes and the cursor share a transaction, so a failed batch is safely replayed. A complete snapshot updates validators/provenance and deactivates missing records. Partial or invalid imports never perform withdrawal reconciliation. Previously committed batches can be visible while an import is in progress; the entire multi-request import is not one atomic database snapshot.

Associations are filtered by theme/breadcrumb ID `N31931` from the Individuals dataset. The dedicated historical archive is deliberately normalized to the current Individuals archive.

The parser retains source URL and editorial date. The public renderer applies a DOM-based element/attribute allowlist. Internal record links are rewritten against locally imported records. Public controllers restrict supported views; administrator actions enforce CSRF and component permissions. Theme editing additionally requires `core.admin` because CSS can affect site presentation.

## Tables

`#__comarquage_feeds`: feed status, HTTP validators, provenance and resumable cursor.

`#__comarquage_items`, `#__comarquage_categories`, `#__comarquage_item_category_map`: records and taxonomy. Withdrawn rows are retained with state 0.

`#__comarquage_search_index`: local full-text search, refreshed during import.

`#__comarquage_metadata`: import journal. `#__comarquage_local_organisations`: retained schema for local context, not an advertised full directory sync.

## Extension points and boundaries

`FeedAdapterInterface` isolates schema conversion. The COPERIA 3.5 adapter currently shares the generic structured renderer, which tolerates unknown XML elements by preserving their text. More specific handling should be added with representative fixtures and rendering tests.

No remote telemetry, account or API key is required. Outbound requests serve configured public-data imports and Joomla's normal extension update checks. The package does not run shell commands or create scheduled OS tasks. The administrator must arrange reliable Joomla Scheduler execution.

FR : l’import et son curseur sont validés ensemble par lot ; les suppressions du flux sont prises en compte uniquement en fin d’import complet. Les publications DILA restent en français. Les traductions concernent l’extension et sa documentation.
