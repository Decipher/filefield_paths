# File (Field) Paths

[![Pipeline](https://git.drupalcode.org/project/filefield_paths/badges/8.x-1.x/pipeline.svg)](https://git.drupalcode.org/project/filefield_paths/-/pipelines)
[![Test](https://github.com/Decipher/filefield_paths/actions/workflows/test.yml/badge.svg?branch=8.x-1.x)](https://github.com/Decipher/filefield_paths/actions/workflows/test.yml?query=branch%3A8.x-1.x)
[![Coverage](https://codecov.io/gh/Decipher/filefield_paths/branch/8.x-1.x/graph/badge.svg)](https://codecov.io/gh/Decipher/filefield_paths/branch/8.x-1.x)

The File (Field) Paths module extends the default functionality of Drupal's core
File module, Image module and many other File upload modules, by adding the
ability to use entity based tokens in destination paths and file names.

In simple terms, File (Field) Paths allows you to automatically sort and rename
your uploaded files using token based replacement patterns to maintain a nice
clean filesystem.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/filefield_paths).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/filefield_paths).


## Table of contents

- Requirements
- Recommended modules
- Configuration
- Features
- FAQ
- Maintainers


## Requirements

This module requires the Drupal core File module.


## Recommended modules

These modules are optional, and are used when present:

- [Pathauto](https://www.drupal.org/project/pathauto) - for filename/path
  cleanup options.
- [Redirect](https://www.drupal.org/project/redirect) - to create a redirect
  when a previously uploaded file is moved.
- [Token](https://www.drupal.org/project/token) - for the token browser when
  building path/filename patterns.


## Configuration

Once installed, File (Field) Paths needs to be configured for each file field
you wish to use, on that field's settings form. For example, for an Image
field on the Article content type:

Administration > Structure > Content types > Article > Manage fields > Image
(admin/structure/types/manage/article/fields/field_image)

Module-wide settings, such as the temporary upload location, are at
Administration > Configuration > Media > File system > File (Field) Paths
(admin/config/media/file-system/filefield-paths).

## Features

- Configurable file paths now use entity tokens in addition to user tokens.
- Configurable file names.
- Support for file based fields, including but not limited to:
  - Drupal core File module.
  - Drupal core Image module.
  - Video module.
- File path and filename cleanup options:
  - Remove slashes from tokens.
  - Filter out words and punctuation by taking advantage of the Pathauto
      module.
  - Convert unicode characters into US-ASCII with the Transliteration module.
- Retroactive updates - rename and/or move previously uploaded files.
- Active updating - actively rename and/or move previously uploaded files.
- Create redirect - automatically create a redirect when moving uploaded files,
  using the Redirect module.


## FAQ

**Q: Aren't tokens already supported in the File module?**

**A:** A limited selection of tokens are supported in the File module.

   Entity based tokens allow you to use the Entity ID, Title, creation date and
   much more in your directory/filenames where you would otherwise be unable.


**Q: Why aren't my files in the correct folder?**

**A:** When you are creating or updating an entity the full values for the tokens
   may not yet be known by Drupal, so the File (Field) Paths module will upload
   your files to a temporary location and then once you save the entity and
   Drupal is provided with the tokens values the file will be moved to the
   appropriate location.


**Q: Why is there a warning on the 'Retroactive updates' feature?**

**A:** Retroactive updates will go through every single entity of the particular
   bundle and move and/or rename the files.

   While there have been no reports of errors caused by the feature, it is quite
   possible that the moving/renaming of these files could break links. It is
   strongly advised that you only use this functionality on your developmental
   servers so that you can make sure not to introduce any linking issues.


**Q: How do I disable File (Field) Paths?**

**A:** At three levels: uncheck "Enable File (Field) Paths?" on a field's settings
   form to disable it for that field, uncheck "Enable File (Field) Paths" on the
   module's settings form to disable it site-wide, or set the
   `filefield_paths_settings` property on an entity before saving it to disable
   (or otherwise override) processing for that one save only, without changing
   any configuration. See `filefield_paths.api.php` for the per-save override's
   full API.



## Maintainers

- Oleh Vehera - [voleger](https://www.drupal.org/u/voleger)
- Stuart Clark - [Deciphered](https://www.drupal.org/u/deciphered)
- David Pagini - [dpagini](https://www.drupal.org/u/dpagini)
