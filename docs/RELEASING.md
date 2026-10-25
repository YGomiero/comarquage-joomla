# Release process

1. Update package/component/plugin versions, asset version, changelog and documentation download examples.
2. Run `php tests/run.php`, `python tools/audit.py` and PHP syntax checks.
3. Build with `python tools/build.py`.
4. Install the exact ZIP on a clean Joomla 6 site. Test remote imports, resume, search, record rendering, scheduler, upgrade and uninstall.
5. Run JED Checker and inspect findings. JED Checker is a heuristic aid, not directory approval. Keep detailed local reports out of the public release.
6. Commit and tag the tested source with the exact numeric manifest version, e.g. `1.0.0`.
7. Publish a release with the installable ZIP, `update.xml` and `SHA256SUMS`. Do not overwrite existing release assets.
8. Download the published ZIP, compare its digest with the tested artifact, and verify Joomla reads the published update feed.

The workflow also supports a manual rehearsal, which builds and tests without publishing. No JED listing is submitted automatically.

FR : chaque version publique doit correspondre à un ZIP effectivement testé. Le flux Joomla et les empreintes font partie de la même release. Une correction exige une nouvelle version ; aucun ancien ZIP ne doit être remplacé.
