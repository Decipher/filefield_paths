# Changelog

This file records the changes in each release of File (Field) Paths.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Releases follow the Drupal.org `8.x-1.x` contrib versioning scheme.

## Unreleased

### Changed

- [#3622262](https://www.drupal.org/i/3622262): Declared PHP 8.2 as the
  minimum version. `8.x-1.0-rc2` already needed it, because it declares five
  classes `readonly`, but it did not say so and failed with a parse error on
  PHP 8.1. Composer and Drupal now refuse PHP 8.1. Sites on PHP 8.1 should
  pin `8.x-1.0-rc1`, because Composer there still resolves to `8.x-1.0-rc2`.

## 8.x-1.0-rc2 - 2026-09-07

### Added

- [#3419618](https://www.drupal.org/i/3419618): Allowed files to move between
  stream wrappers during a retroactive update, and reported the files a
  retroactive update skipped instead of dropping them silently.
- [#3121826](https://www.drupal.org/i/3121826): Delivered image style
  derivatives on demand for files staged under `temporary://`, so image
  previews render before the entity is saved.

### Changed

- The default temporary file location in `config/install` is now
  `temporary://filefield_paths` instead of `public://filefield_paths`. A fresh
  install already ended on the temporary scheme through the install-time
  correction. The value in the file now says so too. Sites with an existing
  setting are not changed.
- [#3598317](https://www.drupal.org/i/3598317): Replaced APIs deprecated in
  Drupal 11.2 (`$entity->original`, `REQUIREMENT_ERROR`) through
  `DeprecationHelper`, keeping Drupal 10 support.
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
- [#3432653](https://www.drupal.org/i/3432653): Fixed "Call to a member
  function getType() on bool" during migrations.
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

## Earlier releases

Release notes for 8.x-1.0-beta2 (2018-06-21) through 8.x-1.0-rc1 (2025-12-12)
are on the [Drupal.org release pages](https://www.drupal.org/project/filefield_paths/releases).
The entries below are carried over from the previous `CHANGELOG.txt` and are
not complete.

- [#2679329](https://www.drupal.org/i/2679329) by alexverb, Deciphered: Added
  improved support for field types.
- [#2662420](https://www.drupal.org/i/2662420) by jonhattan: Fixed issue with
  Base fields.
- Fixed issue with Token validation.
- Fixed issue with Token tree.
- 8.x-1.0-beta1 (2015-12-25): Initial Drupal 8 release.
