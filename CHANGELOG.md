# Changelog

This file records the changes in each release of File (Field) Paths, from the
first commit in July 2008.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Releases follow the Drupal.org contrib versioning scheme and are grouped by
branch, newest first: `8.x-1.x`, then `7.x-1.x`, `6.x-1.x` and `5.x-1.x`. Each
release is dated by its Drupal.org release node.

## Unreleased

### Changed

- Rewrote this changelog to cover every release since the module's first
  commit in 2008, checked against the commits between the release tags, the
  `CHANGELOG.txt` on each older branch and the Drupal.org release notes.

## 8.x-1.0-rc2 - 2026-09-07

### Added

- [#3419618](https://www.drupal.org/i/3419618): Allowed files to move between
  stream wrappers during a retroactive update, and reported the files a
  retroactive update skipped instead of dropping them silently.
- [#3121826](https://www.drupal.org/i/3121826): Delivered image style
  derivatives on demand for files staged under `temporary://`, so image
  previews render before the entity is saved.
- [#3432653](https://www.drupal.org/i/3432653): Covered the migration that
  reported "Call to a member function getType() on bool" with kernel tests.
  The crash itself went away with the 8.x-1.0-rc1 rewrite of the file
  processing code.
- Added kernel and unit test coverage for the services, the hook classes, the
  Drush commands and the install code, and converted the PHPUnit annotations
  to attributes.

### Changed

- The default temporary file location in `config/install` is now
  `temporary://filefield_paths` instead of `public://filefield_paths`. A fresh
  install already ended on the temporary scheme through the install-time
  correction. The value in the file now says so too. Sites with an existing
  setting are not changed.
- [#3598317](https://www.drupal.org/i/3598317): Replaced APIs deprecated in
  Drupal 11.2 (`$entity->original`, `REQUIREMENT_ERROR`) through
  `DeprecationHelper`, keeping Drupal 10 support.
- [#3331483](https://www.drupal.org/i/3331483): Restructured the README to
  follow the Drupal.org README template.
- [#3592443](https://www.drupal.org/i/3592443): Stopped the CI pipeline
  testing against the next major Drupal version (Drupal 12).
- Adopted drupal_extension_scaffold v4.17.0 for the development environment
  and CI.
- [#3619767](https://www.drupal.org/i/3619767): Rewrote the README section on
  the temporary file location: the correct settings path, the `temporary://`
  default, and when to use `private://` instead.
- The "Temporary file location" description on the settings form and the field
  settings form now says the same as the README. It no longer recommends
  `private://` for image previews, which work under `temporary://` since
  [#3121826](https://www.drupal.org/i/3121826).

### Fixed

- [#3569210](https://www.drupal.org/i/3569210): Stopped a retroactive update
  treating `target_id` as text, which failed on PostgreSQL.
- [#3616606](https://www.drupal.org/i/3616606): Stopped files being renamed on
  every entity update when active updating is off.
- [#3045063](https://www.drupal.org/i/3045063): Generated the redirect
  deduplication hash from the source path and language, matching the Redirect
  module.
- [#3269636](https://www.drupal.org/i/3269636): Resolved `private://` redirect
  paths through the stream wrapper, so the redirect created when a file moves
  from public to private points at a URL that works.
- [#3580359](https://www.drupal.org/i/3580359): Fixed "Call to a member
  function isEmpty() on array" in `File::filePresave()` after updating to
  8.x-1.0-rc1.
- Generated file names now go through core's upload name sanitising before
  a file is moved, and keep an extension the field allows.

## 8.x-1.0-rc1 - 2025-12-12

### Added

- [#3257811](https://www.drupal.org/i/3257811): Added a "Temporary file
  location" setting to each file field, overriding the site-wide setting.
  Installing the module now corrects a `public://` temporary location to the
  recommended scheme, the same way the 8.x-1.0-beta6 update did.
- [#3491194](https://www.drupal.org/i/3491194): Added test coverage for the
  Drush command.
- [#3327975](https://www.drupal.org/i/3327975): Added unit test coverage for
  `FieldItem`.
- [#2827652](https://www.drupal.org/i/2827652): Added test coverage showing
  that image style derivatives are generated for an uploaded image.
- [#3477033](https://www.drupal.org/i/3477033): Added test coverage showing
  that the file URI is updated for programmatically saved entities.

### Changed

- [#3562058](https://www.drupal.org/i/3562058),
  [#3562121](https://www.drupal.org/i/3562121),
  [#3562124](https://www.drupal.org/i/3562124),
  [#3562127](https://www.drupal.org/i/3562127),
  [#3562149](https://www.drupal.org/i/3562149),
  [#3562158](https://www.drupal.org/i/3562158),
  [#3562160](https://www.drupal.org/i/3562160),
  [#3562293](https://www.drupal.org/i/3562293),
  [#3562299](https://www.drupal.org/i/3562299): Moved the hook
  implementations into hook classes under `src/Hook`. The functions in
  `filefield_paths.module` now hand over to those classes.
- [#3562442](https://www.drupal.org/i/3562442): Moved the recommended
  temporary scheme detection into the `MoveFileProcessor` service.
- [#3562822](https://www.drupal.org/i/3562822): Moved the token replacement
  and cleanup of paths into the `PathProcessor` service.
- [#3399471](https://www.drupal.org/i/3399471): Skipped the file move, from
  the batch and from Drush, when the file does not exist on disk.
- [#3258865](https://www.drupal.org/i/3258865): Explained on the field
  settings form what "Retroactive update" and "Active updating" do.
- [#2847431](https://www.drupal.org/i/2847431): Listed the entity's own
  tokens in the token browser on the field settings form.
- [#3562820](https://www.drupal.org/i/3562820): Dropped the ctools test
  dependency.

### Deprecated

- `filefield_paths.inc`, `filefield_paths.tokens.inc`,
  `filefield_paths_process_string()` and
  `filefield_paths_recommended_temporary_scheme()` are deprecated and will be
  removed in 2.0.0. Use the hook classes and services instead.

### Fixed

- [#3403246](https://www.drupal.org/i/3403246): Stopped a retroactive update
  crashing when a file is missing or cannot be moved. The file is logged and
  skipped. The stream wrapper scheme is now read from the URI instead of the
  wrapper type.
- [#3327975](https://www.drupal.org/i/3327975): Fixed a `TypeError` in
  `FieldItem::getFromSupportedWidget()`.
- [#3175118](https://www.drupal.org/i/3175118): Marked a processed file as
  permanent, so its status changes after it is added.
- [#3556209](https://www.drupal.org/i/3556209): Installed the `origname`
  field storage on a fresh install, not only through the update hook.
- [#3339688](https://www.drupal.org/i/3339688): Guarded `hook_file_presave()`
  against a file entity with no `origname` or `filename` field.
- [#3405450](https://www.drupal.org/i/3405450): Passed `$settings` by
  reference in `hook_filefield_paths_process_file()`, as the API
  documentation and the caller expected.
- [#3520824](https://www.drupal.org/i/3520824): Fixed "Undefined array keys
  #element_validate and #default_value" on the field settings form when the
  core file directory element does not carry them.
- [#3074642](https://www.drupal.org/i/3074642): Ran token replacement in its
  own render context, so the cache metadata it bubbles no longer leaks.

## 8.x-1.0-beta8 - 2024-12-03

### Added

- [#3430580](https://www.drupal.org/i/3430580): Added Drupal 11 support.
- [#3459464](https://www.drupal.org/i/3459464): Added a project logo for
  Project Browser.

### Changed

- [#2930945](https://www.drupal.org/i/2930945): Ported the Drush command to a
  Drush 12 command class, and moved the batch operations into the
  `filefield_paths.batch.updater` service. The module now conflicts with
  Drush older than 12.5.0.
- [#3192110](https://www.drupal.org/i/3192110): Fixed coding standards, and
  removed the commented-out Drupal 7 update hooks from the install file.
- Added the DrupalCI GitLab CI template, and
  [#3480113](https://www.drupal.org/i/3480113) updated it to the current
  version and removed the Travis CI configuration.
- [#3486732](https://www.drupal.org/i/3486732): Fixed the tests and turned
  the previous major (Drupal 10) test job back on.

### Removed

- [#3192110](https://www.drupal.org/i/3192110): Dropped Drupal 9 support.
  The module now requires Drupal 10.3 or later.

## 8.x-1.0-beta7 - 2023-07-24

### Fixed

- [#3360236](https://www.drupal.org/i/3360236): Fixed the retroactive update
  crashing on Drupal 10. The widget form alter now implements
  `hook_field_widget_single_element_form_alter()`, and the entity query
  behind the update declares its access check, as Drupal 10 requires.

## 8.x-1.0-beta6 - 2022-12-14

### Added

- [#3287576](https://www.drupal.org/i/3287576): Added Drupal 10 support.

### Changed

- [#3315016](https://www.drupal.org/i/3315016): Built the retroactive update
  batch with `BatchBuilder`.

### Removed

- [#3287576](https://www.drupal.org/i/3287576): Dropped Drupal 8 support.
  The module now requires Drupal 9.3 or later.

### Fixed

- [#3119771](https://www.drupal.org/i/3119771): Installed the `origname`
  field storage on the file table through an update hook, so the original
  file name is stored.
- [#2858308](https://www.drupal.org/i/2858308): Stopped file paths being
  written with backslashes on Windows servers.
- [#2718783](https://www.drupal.org/i/2718783): Fixed "Call to undefined
  method BaseFieldDefinition::getThirdPartySettings()" by reading the field
  settings through a `FieldItem` utility that handles base fields.

### Security

- Partial fix for
  [SA-CONTRIB-2022-065](https://www.drupal.org/sa-contrib-2022-065). The
  status report now reports an error when the temporary file location is
  under `public://` while `temporary://` or `private://` is available, an
  update hook moves an existing `public://` temporary location to the
  recommended scheme, and the settings form defaults to that scheme.

## 8.x-1.0-beta5 - 2020-12-01

### Fixed

- Fixed the `createFileField()` method declaration in the test base class,
  which broke the tests.

## 8.x-1.0-beta4 - 2020-12-01

### Changed

- [#2923206](https://www.drupal.org/i/2923206): Finished converting the tests
  from Simpletest to PHPUnit, and moved redirect creation into the
  `filefield_paths.redirect` service.
- [#3185646](https://www.drupal.org/i/3185646): Fixed coding standards.

### Deprecated

- `_filefield_paths_create_redirect()` is deprecated and will be removed in
  2.0.0. Use `RedirectInterface::createRedirect()` instead.

## 8.x-1.0-beta3 - 2020-11-30

### Added

- [#3099885](https://www.drupal.org/i/3099885): Added Drupal 9 support and
  replaced the code deprecated for Drupal 9. The module now requires Drupal
  8.8 or later.

### Fixed

- [#3052424](https://www.drupal.org/i/3052424): Created redirects with the
  Redirect module's default status code instead of status code 0.

## 8.x-1.0-beta2 - 2020-03-17

### Added

- [#2679329](https://www.drupal.org/i/2679329): Supported every field type
  whose item list extends `FileFieldItemList`, not only the core File field.

### Changed

- [#2923206](https://www.drupal.org/i/2923206): Moved the tests to
  `tests/src/Functional` and fixed them for current Drupal 8.
- [#2758243](https://www.drupal.org/i/2758243): Removed the `@file` docblocks
  from the PHP class files.
- Updated the Travis CI configuration and the test dependencies.

### Removed

- [#2680747](https://www.drupal.org/i/2680747): Removed the
  `hook_filefield_paths_field_type_info()` documentation from the API file.
  The hook no longer exists in 8.x.

### Fixed

- [#2662420](https://www.drupal.org/i/2662420): Fixed "Call to undefined
  method FileFieldItemList::getThirdPartySettings()" by skipping base fields.
- [#2902158](https://www.drupal.org/i/2902158): Fixed a typo on the settings
  form.
- Validated the file path and file name patterns against the tokens that are
  available, and took the entity's token type from the Token module's entity
  mapper.
- Fixed the token browser link on the field settings form.

## 8.x-1.0-beta1 - 2015-12-25

### Added

- Initial Drupal 8 release, ported from 7.x-1.0: token-based file paths and
  file names for file-based fields, the cleanup options (remove slashes,
  Pathauto, transliteration), retroactive and active updating, redirects
  through the Redirect module, and the Drush command.

## 7.x-1.2 - 2022-12-14

### Changed

- Declared PHP 5.3 as the minimum PHP version in the `.info` file.

### Fixed

- [#3182014](https://www.drupal.org/i/3182014): Fixed typos in the settings
  descriptions.

### Security

- Partial fix for
  [SA-CONTRIB-2022-065](https://www.drupal.org/sa-contrib-2022-065). The
  status report now reports an error when the temporary file location is
  under `public://` while private files are available, an update hook moves
  an existing `public://` temporary location to the recommended scheme, and
  the setting defaults to that scheme.

## 7.x-1.1 - 2018-08-14

### Fixed

- [#2643026](https://www.drupal.org/i/2643026): Logged a watchdog message
  instead of failing when the anonymous function that replaces file
  references could not be created, which broke retroactive updates.

### Security

- Fixed [SA-CONTRIB-2018-056](https://www.drupal.org/sa-contrib-2018-056):
  the path of a newly uploaded file is now sanitised.

## 7.x-1.0 - 2015-11-17

### Added

- Added Variable module integration.

### Fixed

- [#2615704](https://www.drupal.org/i/2615704): Fixed files being left in
  the temporary location, `sites/default/files/filefield_paths` by default,
  when its scheme was not `temporary://`.

## 7.x-1.0-rc3 - 2015-11-11

### Added

- [#2607302](https://www.drupal.org/i/2607302): Added a configurable
  temporary file location for unprocessed uploads.
- [#2612396](https://www.drupal.org/i/2612396): Added a watchdog message when
  a file could not be moved.

### Removed

- Removed the unused `sql` key from the file path and file name settings.

## 7.x-1.0-rc2 - 2015-10-28

### Changed

- Split the tests into one file per area and fixed the text replace and
  relative image style tests.

### Fixed

- [#2592519](https://www.drupal.org/i/2592519): Fixed the temporary upload
  location on field collections.
- [#2576547](https://www.drupal.org/i/2576547): Stopped processing Media
  YouTube files, which broke their URIs.
- [#2570127](https://www.drupal.org/i/2570127): Added stricter checks in
  `filefield_paths_form_alter()` to stop an undefined `#bundle` notice.
- [#2569589](https://www.drupal.org/i/2569589): Fixed unicode characters in
  Pathauto cleanup.

## 7.x-1.0-rc1 - 2015-09-15

### Added

- [#2468547](https://www.drupal.org/i/2468547): Added Redirect module
  integration, with a "Create Redirect" option that records the old path
  when a file moves.
- [#1942720](https://www.drupal.org/i/1942720): Added the "Remove slashes"
  cleanup option for tokens that contain a slash.
- [#2211665](https://www.drupal.org/i/2211665): Validated the file path
  setting and removed unnecessary slashes.
- [#2398411](https://www.drupal.org/i/2398411): Used the field's existing
  file directory as the default file path.
- [#2395903](https://www.drupal.org/i/2395903): Truncated generated file paths
  that were too long for the database.
- Added tests for uploads, tokens, multi-value fields, retroactive updates,
  file usage, read-only stream wrappers, Pathauto and Transliteration, and
  ran them on Travis CI.

### Changed

- [#2551187](https://www.drupal.org/i/2551187): Opened the token tree in a
  dialog to speed up the field settings form.
- [#2383527](https://www.drupal.org/i/2383527): Moved unprocessed uploads to
  `temporary://` instead of the public files directory.
- [#2214409](https://www.drupal.org/i/2214409): Reworked the field settings
  form layout.
- [#1854450](https://www.drupal.org/i/1854450): Updated the file processing
  logic so files managed by the Media module are handled.
- [#1481260](https://www.drupal.org/i/1481260): Moved the processing from
  `hook_entity_update()` to `hook_field_storage_pre_update()`, which stopped
  duplicate rows in the forum index.
- [#2362131](https://www.drupal.org/i/2362131): Removed the executable bit
  from the include files.

### Fixed

- [#2514874](https://www.drupal.org/i/2514874): Fixed the Drush command
  skipping content the running user could not access.
- [#2276435](https://www.drupal.org/i/2276435): Stopped a retroactive update
  resetting the instance settings of the field.
- [#2271595](https://www.drupal.org/i/2271595): Fixed an `array_keys()` error
  when a retroactive update had an empty batch.
- [#2103151](https://www.drupal.org/i/2103151): Replaced the deprecated `/e`
  modifier in `preg_replace()` with `preg_replace_callback()`.
- [#2185755](https://www.drupal.org/i/2185755): Fixed the regex dropping the
  first character of a replacement when it was a number.
- [#2119789](https://www.drupal.org/i/2119789): Fixed the regex failing on
  spaces and other complex characters in the old file name.
- [#2068365](https://www.drupal.org/i/2068365): Fixed the update hook failing
  when a field had no instance.
- [#2062073](https://www.drupal.org/i/2062073): Removed faulty revision
  handling code that could remove files from the server.
- [#2047835](https://www.drupal.org/i/2047835): Fixed Pathauto cleanup
  removing periods from file names.
- [#2019723](https://www.drupal.org/i/2019723): Stopped filtering out remote
  stream wrappers.
- [#1985650](https://www.drupal.org/i/1985650): Fixed the `.info` file
  pointing at the moved Drush include.
- [#1985280](https://www.drupal.org/i/1985280): Fixed an invalid `foreach()`
  argument in `hook_entity_update()` when no file was attached.
- [#1495716](https://www.drupal.org/i/1495716),
  [#1986472](https://www.drupal.org/i/1986472): Fixed the Drupal 6 to Drupal
  7 upgrade path losing the module settings.
- [#1292436](https://www.drupal.org/i/1292436): Fixed `pathinfo()` handling
  of UTF-8 file names.
- Fixed the text replacement of references to unclean URLs.

### Removed

- Removed the dependency on the Token module.

## 7.x-1.0-beta4 - 2013-04-25

### Added

- [#1572206](https://www.drupal.org/i/1572206): Added a per-field option to
  turn File (Field) Paths off.

### Changed

- [#1499442](https://www.drupal.org/i/1499442): Reviewed the translatable
  strings.
- [#1364492](https://www.drupal.org/i/1364492): Improved the `require_once`
  routine that loads the field type includes.
- [#860848](https://www.drupal.org/i/860848): Lengthened the file name field.

### Fixed

- [#1945148](https://www.drupal.org/i/1945148): Stopped the file path
  Pathauto cleanup option also cleaning the file name.
- [#1942720](https://www.drupal.org/i/1942720): Fixed cleanup when a token
  contains a slash.
- [#1925298](https://www.drupal.org/i/1925298): Fixed image derivatives for
  images added by the Insert module on Drupal 7.20.
- [#1866450](https://www.drupal.org/i/1866450): Fixed links in body text not
  updating when the path held non-ASCII characters.
- [#1714596](https://www.drupal.org/i/1714596): Fixed an undefined `original`
  property in `file_field_update()` on Drupal 7.15.
- [#1705298](https://www.drupal.org/i/1705298): Fixed a leading slash in the
  file URI when the file path setting was empty.
- [#1601104](https://www.drupal.org/i/1601104): Fixed a notice when the file
  had no extension.
- [#1549474](https://www.drupal.org/i/1549474): Replaced file paths inside
  summary text as well as body text.
- [#1512466](https://www.drupal.org/i/1512466): Processed every value of a
  multi-value field instead of only the first.
- [#1464404](https://www.drupal.org/i/1464404): Called
  `field_attach_update()` only when a file was processed. The unconditional
  call deleted Organic Groups memberships on user sign-up; the report in this
  queue was [#1481260](https://www.drupal.org/i/1481260).
- [#1438290](https://www.drupal.org/i/1438290): Fixed the Drupal 6 to Drupal
  7 upgrade path.
- [#1432200](https://www.drupal.org/i/1432200): Fixed an undefined `langcode`
  variable.
- [#1420700](https://www.drupal.org/i/1420700): Fixed a pass-by-reference
  error that stopped the file path updating after a title change.
- [#1361884](https://www.drupal.org/i/1361884): Stopped processing remote
  files.
- [#1949508](https://www.drupal.org/i/1949508): Fixed the regex in
  `_filefield_paths_replace_path()` corrupting HTML when more than one path
  was replaced in the same text.
- Fixed malformed URIs and some minor syntax errors.

## 7.x-1.0-beta3 - 2012-02-07

### Changed

- Simplified a large part of the core processing code.

### Fixed

- [#1429238](https://www.drupal.org/i/1429238): Fixed a syntax error in the
  Drush command.
- [#1334448](https://www.drupal.org/i/1334448): Fixed file usage being
  counted twice when a new revision was saved.

## 7.x-1.0-beta2 - 2012-02-05

### Added

- [#1414090](https://www.drupal.org/i/1414090): Added the `ffpu` Drush command
  for retroactive updates.
- Added support for a change of URI scheme during an update.
- Added support for the Video module.

### Fixed

- [#1051736](https://www.drupal.org/i/1051736): Fixed `dirname()` writing
  files to a `public:` directory under the web root.
- [#1335984](https://www.drupal.org/i/1335984): Removed debug output that
  broke Features exports.

## 7.x-1.0-beta1 - 2011-11-07

### Added

- Added support for any entity type, with entity tokens, instead of nodes
  only.
- [#578442](https://www.drupal.org/i/578442): Added Features support for the
  field settings.

### Changed

- [#1253168](https://www.drupal.org/i/1253168): Lengthened the `type` and
  `field` columns of the `{filefield_paths}` table.
- [#1206876](https://www.drupal.org/i/1206876): Stored the "Active updating"
  setting in the `{filefield_paths}` table instead of a variable, which had
  turned it on for every field.
- [#1225752](https://www.drupal.org/i/1225752): Removed the packaging script
  information from the `.info` file.
- Used `hook_file_presave()` instead of a database query to find new files.
- Removed the `pathinfo()` workaround for PHP older than 5.2.

### Fixed

- [#1328064](https://www.drupal.org/i/1328064): Re-enabled retroactive
  updates.
- [#1326094](https://www.drupal.org/i/1326094): Fixed the Coder findings.
- [#1308532](https://www.drupal.org/i/1308532): Cleared tokens that have no
  value instead of leaving them in the path.
- [#1278004](https://www.drupal.org/i/1278004): Fixed files being treated as
  new when a node was edited.
- [#1233004](https://www.drupal.org/i/1233004): Fixed the Pathauto cleanup
  integration.
- [#1225764](https://www.drupal.org/i/1225764): Updated references in text
  fields when a file name changed.
- [#1211038](https://www.drupal.org/i/1211038): Corrected the schema default
  for `active_updating`.
- [#1188074](https://www.drupal.org/i/1188074): Made the schema change in the
  update hook conditional, which fixed the update from the dev release.
- [#1194694](https://www.drupal.org/i/1194694): Replaced the removed
  `field_attach_query()` with `EntityFieldQuery`.
- [#1019380](https://www.drupal.org/i/1019380): Cleaned up the
  `{filefield_paths}` table in `hook_field_delete_instance()` instead of
  `hook_field_delete_field()`.
- [#1017830](https://www.drupal.org/i/1017830): Rewrote the token replacement
  and fixed the Transliteration and Pathauto support.
- [#1023690](https://www.drupal.org/i/1023690): Stopped `_0` being appended
  to the file name on every other save.

## 7.x-1.0-alpha1 - 2011-06-12

### Added

- Initial Drupal 7 release, built on the unreleased `6.x-2.x` rewrite:
  token-based file paths and file names for the core File and Image fields,
  the Pathauto and Transliteration cleanup options, retroactive and active
  updating, and replacement of unprocessed file references in text.

### Fixed

- [#1005574](https://www.drupal.org/i/1005574): Fixed a PHP 5.3
  pass-by-reference warning.
- [#1016078](https://www.drupal.org/i/1016078): Fixed an invalid `foreach()`
  argument in `image_filefield_paths_get_fields()`.
- [#1026434](https://www.drupal.org/i/1026434): Added the missing update hook
  for upgrades from Drupal 6.
- [#1109448](https://www.drupal.org/i/1109448): Updated the Pathauto links on
  the cleanup settings form.
- [#1102166](https://www.drupal.org/i/1102166): Set the package name.
- [#1156104](https://www.drupal.org/i/1156104): Fixed an undefined field index
  in `filefield_paths_node_update()`.

## 6.x-1.5 - 2013-05-21

### Added

- [#578442](https://www.drupal.org/i/578442): Added Features support for the
  field settings.
- [#756898](https://www.drupal.org/i/756898): Added a Swedish translation,
  later moved to localize.drupal.org.

### Changed

- Removed the CVS keywords and the bundled translation directories as part
  of the Drupal.org move to Git.

### Fixed

- [#1249918](https://www.drupal.org/i/1249918): Fixed a pass-by-reference
  error in the Comment Upload integration.
- [#1195374](https://www.drupal.org/i/1195374): Fixed an undefined `type`
  index in the form alter.
- [#1151514](https://www.drupal.org/i/1151514): Removed a `db_rewrite_sql()`
  call that failed on an unknown `n.language` column.
- [#1057340](https://www.drupal.org/i/1057340): Fixed a fatal error unsetting
  string offsets in the FileField integration.
- [#877578](https://www.drupal.org/i/877578): Fixed the call to the removed
  `_pathauto_include()` function.
- [#635854](https://www.drupal.org/i/635854): Fixed nodes linking to the
  original file path until they were saved a second time.
- [#288416](https://www.drupal.org/i/288416): Fixed "The selected file
  /var/www could not be copied."

## 6.x-1.4 - 2010-01-11

### Added

- [#373094](https://www.drupal.org/i/373094): Added active updating, which
  reprocesses files each time the node is saved.
- [#655782](https://www.drupal.org/i/655782): Added support for the Path
  Filter module.
- [#614992](https://www.drupal.org/i/614992): Allowed longer file path
  patterns.
- [#606500](https://www.drupal.org/i/606500): Replaced unprocessed file URLs
  in CCK text fields as well as the body and teaser.
- [#565526](https://www.drupal.org/i/565526): Added an option to replace the
  file description with the processed file name.
- Added support for the Audio module.
- Added cleanup of a field instance's settings when the instance is deleted.

### Changed

- [#564680](https://www.drupal.org/i/564680): Improved the Image module
  support.
- Improved the replacement of unprocessed URLs inserted by FileField Insert.
- Improved support for private file systems.

### Fixed

- [#614190](https://www.drupal.org/i/614190): Stopped processing the "Add
  another item" string, which caused a fatal error.
- [#536384](https://www.drupal.org/i/536384): Ran transliteration on the
  token values.
- [#525354](https://www.drupal.org/i/525354): Fixed the
  `hook_filefield_paths_get_fields()` implementations so they work outside
  the Form API.
- [#522678](https://www.drupal.org/i/522678): Fixed the file path variable
  when used with ImageField Crop.
- [#515044](https://www.drupal.org/i/515044): Fixed the `[filefield-onlyname]`
  and `[filefield-onlyname-original]` tokens on every supported PHP version.
- Fixed revisions support.

## 6.x-1.3 - 2009-07-03

### Added

- [#488264](https://www.drupal.org/i/488264): Added support for ImageField
  Crop.
- [#465848](https://www.drupal.org/i/465848): Added support for the Comment
  Upload module.
- [#457956](https://www.drupal.org/i/457956): Declared the `origname` column
  added to the `{files}` table through `hook_schema_alter()`.
- Added support for the Image module.
- Added SimpleTests.
- Added Dutch and Danish translations and a translation template.

### Changed

- [#478924](https://www.drupal.org/i/478924): Rewrote the README setup
  instructions.

### Fixed

- [#494830](https://www.drupal.org/i/494830): Added support for FileField
  Sources, which fixed a file reused from another node being deleted from the
  server when it was removed.
- [#485528](https://www.drupal.org/i/485528): Fixed content types whose name
  contains a space.
- [#480580](https://www.drupal.org/i/480580): Fixed undefined indexes.
- [#473368](https://www.drupal.org/i/473368): Stopped the Pathauto cleanup
  removing the slashes from a path token.
- [#466412](https://www.drupal.org/i/466412): Fixed node submission with the
  Upload module when CCK is not installed.

## 6.x-1.2 - 2009-05-01

### Added

- [#434038](https://www.drupal.org/i/434038): Ran retroactive updates as a
  batch process.
- Stored the original file name in the `{files}` table and added the original
  file name tokens.
- Added `CHANGELOG.txt` and `README.txt`.

### Changed

- [#447794](https://www.drupal.org/i/447794): Improved the ImageField
  thumbnail support.
- Changed the default pattern for the file name.
- Gave one hook argument a default value.

### Fixed

- [#428542](https://www.drupal.org/i/428542): Fixed a query that used double
  quotes, which PostgreSQL rejects.
- [#311526](https://www.drupal.org/i/311526): Fixed a recursion error.
- Fixed the new tokens when the original file name is empty.

## 6.x-1.1 - 2009-04-07

### Added

- Added support for the core Upload module.
- Added retroactive changes, which rename or move files that were uploaded
  before the settings changed.
- Added API hooks so sub-modules can add support for other field types.
- [#373735](https://www.drupal.org/i/373735): Replaced encoded unprocessed
  URLs, such as those written by FCKeditor.
- [#331488](https://www.drupal.org/i/331488): Added error checking when no
  file field was added or updated.
- [#324736](https://www.drupal.org/i/324736): Added a workaround for token
  values that arrive as an array.
- Improved support for FileField tokens and for unprocessed URL replacement.

### Changed

- Let `hook_filefield_paths_process_file()` handle changes on node update.
- Reworded the retroactive changes warning and reordered the form.

### Fixed

- [#399318](https://www.drupal.org/i/399318): Moved ImageField thumbnails to
  the final directory.
- [#398754](https://www.drupal.org/i/398754): Fixed the wrong file name, and
  deleted new files, when transliteration was applied to the file path.
- [#366997](https://www.drupal.org/i/366997): Fixed several PHP notices.
- [#363105](https://www.drupal.org/i/363105): Fixed an insert query whose
  column count did not match.
- [#360303](https://www.drupal.org/i/360303): Made the strings translatable.
- Fixed the tokens with FileField 6.x-3.0-rc1 and an undefined variable.

### Removed

- Removed the dependency on the FileField module, so the Upload module can be
  used on its own.

## 6.x-1.0 - 2008-11-03

### Added

- Initial release for Drupal 6: token-based file paths and file names for CCK
  FileField and ImageField, replacement of unprocessed file references in the
  body and teaser, and the following added before the release:
  - [#288400](https://www.drupal.org/i/288400): Pathauto cleanup.
  - [#290347](https://www.drupal.org/i/290347): Transliteration.
  - [#296426](https://www.drupal.org/i/296426): ImageField support with
    thumbnails.
  - [#322554](https://www.drupal.org/i/322554): Settings stored in a table
    of their own.

## 5.x-1.3 - 2009-07-03

### Added

- [#465848](https://www.drupal.org/i/465848): Added support for the Comment
  Upload module.
- Added support for the Image module.
- Added a Dutch translation and a translation template.

### Changed

- [#478924](https://www.drupal.org/i/478924): Rewrote the README setup
  instructions.

### Fixed

- [#494830](https://www.drupal.org/i/494830): Added support for FileField
  Sources, which fixed a file reused from another node being deleted from the
  server when it was removed.
- [#485528](https://www.drupal.org/i/485528): Fixed content types whose name
  contains a space.
- [#480580](https://www.drupal.org/i/480580): Fixed undefined indexes.
- [#473368](https://www.drupal.org/i/473368): Stopped the Pathauto cleanup
  removing the slashes from a path token.
- [#466412](https://www.drupal.org/i/466412): Fixed node submission with the
  Upload module when CCK is not installed.

## 5.x-1.2 - 2009-05-01

### Added

- Stored the original file name in the `{files}` table and added the original
  file name tokens.
- Added `CHANGELOG.txt` and `README.txt`.

### Changed

- Improved retroactive updates.
- Changed the default pattern for the file name.
- Gave one hook argument a default value.

### Fixed

- [#428542](https://www.drupal.org/i/428542): Fixed a query that used double
  quotes, which PostgreSQL rejects.
- [#311526](https://www.drupal.org/i/311526): Fixed a recursion error.
- Fixed the new tokens when the original file name is empty.

## 5.x-1.1 - 2009-04-07

### Added

- Added support for the core Upload module.
- Added retroactive changes, which rename or move files that were uploaded
  before the settings changed.
- Added API hooks so sub-modules can add support for other field types.
- [#373735](https://www.drupal.org/i/373735): Replaced encoded unprocessed
  URLs, such as those written by FCKeditor.
- [#331488](https://www.drupal.org/i/331488): Added error checking when no
  file field was added or updated.
- Improved support for FileField tokens and for unprocessed URL replacement.

### Changed

- Let `hook_filefield_paths_process_file()` handle changes on node update.
- Reworded the retroactive changes warning and reordered the form.

### Fixed

- [#398754](https://www.drupal.org/i/398754): Fixed the wrong file name, and
  deleted new files, when transliteration was applied to the file path.
- [#390654](https://www.drupal.org/i/390654): Fixed directories with
  ImageField on Drupal 5.
- [#366997](https://www.drupal.org/i/366997): Fixed several PHP notices.
- [#363105](https://www.drupal.org/i/363105): Fixed an insert query whose
  column count did not match.
- [#360303](https://www.drupal.org/i/360303): Made the strings translatable.

### Removed

- Removed the dependency on the FileField module, so the Upload module can be
  used on its own.

## 5.x-1.0 - 2008-11-03

### Added

- Initial release for Drupal 5, a backport of 6.x-1.0 with the same features
  and the fixes made on both branches before release
  ([#299355](https://www.drupal.org/i/299355),
  [#307248](https://www.drupal.org/i/307248),
  [#318601](https://www.drupal.org/i/318601),
  [#321881](https://www.drupal.org/i/321881),
  [#322554](https://www.drupal.org/i/322554)).
