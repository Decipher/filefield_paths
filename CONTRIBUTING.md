# Contributing

This guide is for developers working on this extension. Follow it to build a local Drupal site with the extension, check the coding standards and run the tests.

## Local development

1. Install the following tools:
   - PHP 8.3 or later with SQLite support. The build tooling needs 8.3, though the module itself supports PHP 8.2
   - Composer
   - Node.js with npm, which the build uses
   - [Ahoy](https://github.com/ahoy-cli/ahoy)
2. Clone this repository.
3. Build the site:

   ```bash
   make build
   ```

   ```bash
   ahoy build
   ```

   The build ends by printing the site URL and a one-time login link.

## Building website

The build runs in 3 stages: it assembles the codebase, starts the PHP server and provisions a Drupal site with this extension enabled. The [`drevops/eddy-tooling`](https://github.com/drevops/eddy-tooling) package provides a command for each stage. `scripts/eddy-tooling` installs that package into `vendor/`. CI runs the same commands to build and test this extension.

Never modify `scripts/eddy-tooling`, by hand or with AI. It comes from Eddy. A scaffold update replaces it with the copy from the new release. To change what a tooling command does, declare a patch for the package, as [Patching dependencies](#patching-dependencies) shows.

`scripts/eddy-tooling` installs the newest release that the `drevops/eddy-tooling` constraint in `composer.dev.json` allows. It runs Composer again only in these cases:

- The constraint changes.
- The patches declared for the package change, or a local patch file changes.
- A command of the package is missing from `vendor/bin`.

To pick up a newer release that the constraint allows, remove `vendor/` and build again.

The assembled codebase goes in the `build` directory, with the extension's files symlinked into the Drupal site. Before the assemble command replaces an earlier build, it stops the PHP server that serves that build.

The `build` command is a wrapper for 3 commands that each run 1 stage:

```bash
make assemble     # Assemble the codebase
make start        # Start the PHP server
make provision    # Provision the Drupal website
```

```bash
ahoy assemble     # Assemble the codebase
ahoy start        # Start the PHP server
ahoy provision    # Provision the Drupal website
```

To reinstall the Drupal site without assembling the codebase again, run the `provision` command on its own.

The `info` command prints a read-only summary of the environment, such as the PHP, Drupal and Drush versions, the site URL and the database path. The `login` command prints a new one-time login link.

The `reset` command stops the development server and removes the `build`, `.logs` and `vendor` directories.

### Drupal versions

The `DRUPAL_VERSION` variable sets the Drupal version that the build assembles. It defaults to `extra.eddy.drupal-version` in `composer.dev.json`, the newest Drupal major this extension targets that has a stable release.

To build another Drupal version, set `DRUPAL_VERSION` when you run the build:

```bash
# Newest stable Drupal 11 release.
DRUPAL_VERSION=11 make build

# Newest Drupal 11.1.x patch release.
DRUPAL_VERSION=11.1.0 make build

# Newest Drupal 11 beta, release candidate or stable release.
DRUPAL_VERSION=11@beta make build

# Newest stable Drupal 12 release, or newest pre-release if none.
DRUPAL_VERSION=12 make build
```

```bash
# Newest stable Drupal 11 release.
DRUPAL_VERSION=11 ahoy build

# Newest Drupal 11.1.x patch release.
DRUPAL_VERSION=11.1.0 ahoy build

# Newest Drupal 11 beta, release candidate or stable release.
DRUPAL_VERSION=11@beta ahoy build

# Newest stable Drupal 12 release, or newest pre-release if none.
DRUPAL_VERSION=12 ahoy build
```

The build pins Drupal core to the newest release that matches `DRUPAL_VERSION` and prints that release. A version without a stable release resolves to its newest pre-release.

The build resolves its dependencies for 1 PHP version: the `config.platform.php` of `composer.dev.json` or `composer.json` when either sets one, and the PHP that runs the build otherwise. A `config.platform.php` in your global Composer configuration doesn't apply. Before the build changes anything, it checks that PHP version and the PHP that runs the build against the requirement of the Drupal release. When either doesn't meet the requirement, the build stops and names the PHP version that the release requires.

Every other dependency installs its most stable release that supports that core and that PHP version. It installs a pre-release or a development branch only when no stable release fits. The build output lists every dependency from a development branch.

### Patching dependencies

The build installs [`cweagans/composer-patches`](https://github.com/cweagans/composer-patches) 2.x and applies the patches declared in the `patches` section of `composer.dev.json` or `composer.json`. Keep local patch files in the `patches` directory and reference them from `composer.dev.json` by their path:

```json
"extra": {
    "patches": {
        "drupal/core": {
            "Describe what the patch fixes": "patches/core-fix.patch"
        }
    }
}
```

A patch that no longer applies fails the build. `scripts/eddy-tooling` applies the patches declared for `drevops/eddy-tooling` when it installs the tooling into `vendor/`, so those patches stay out of the build.

`composer.json` ships with the extension, so a site that installs the extension with composer-patches 2.x applies the patches declared there too. That site has no `patches/` directory, so reference each patch in `composer.json` by a public URL.

### Providing `GITHUB_TOKEN`

To avoid GitHub API rate limits, set the `GITHUB_TOKEN` environment variable to a GitHub personal access token. The build passes the token to its own Composer commands through `COMPOSER_AUTH` and leaves your global Composer configuration as it is.

### Provisioning the website

The provision command installs a Drupal site from the `standard` profile. It enables the extension and every `drupal/*` module and theme in the `suggest` section of `composer.json`. To install from another profile, set the `DRUPAL_PROFILE` environment variable.

The start command serves the site on `localhost` and prints its URL. To serve the site on another host name, set the `WEBSERVER_HOST` environment variable.

The start command resolves the port, `WEBSERVER_PORT`, in this order:

1. **`WEBSERVER_PORT` exported in the shell** - used as is. For a one-off run, prefix a command with `WEBSERVER_PORT=9000`.
2. **A `WEBSERVER_PORT` line in the `.env` file of the project root** - used as is. The start command leaves the line unchanged, so the `start`, `stop`, `provision`, `drush` and `login` commands all use the same port.
3. **Neither** - the start command finds the first free port from 8000 to 8099 and writes it to `.env` as `WEBSERVER_PORT=NNNN`.

To pick a new port, delete the `WEBSERVER_PORT` line from `.env` and run the start command again.

The assemble, start and stop commands stop only the webserver that serves the `build/web` directory of this project. Another process on the port keeps running, such as the server of another project that recorded the same port. In that case, the start command fails and names the process. Stop that process, or set `WEBSERVER_PORT` in `.env` to a free port.

The site's database is the SQLite file `/tmp/site_filefield_paths.sqlite`. To browse its tables, open the file in [DB Browser for SQLite](https://sqlitebrowser.org/).

### Step-debugging with XDebug

The development server can run with [XDebug](https://xdebug.org/docs/install) step-debugging. Install the XDebug PHP extension on your host first, so that `php -v` prints `with Xdebug`. Then restart the server with XDebug turned on or off:

```bash
make debug      # restart with XDebug enabled
make start      # restart without XDebug
```

```bash
ahoy debug      # restart with XDebug enabled
ahoy start      # restart without XDebug
```

The `debug` command has the aliases `debug-on`, `xdebug` and `xdebug-on`. The `start` command has the aliases `debug-off` and `xdebug-off`.

When the command line of the running server already holds `xdebug.mode=debug`, the `debug` command skips the restart.

The XDebug settings apply only to the development server, so the tests still collect code coverage with [pcov](https://github.com/krakjoe/pcov).

With XDebug on, every request starts a debug session, so a browser extension that sets the XDebug trigger has no effect. To skip debugging, stop listening in your IDE.

## Coding standards

The following tools check the codebase, each with its configuration file in the root of the project:
- PHPCS checks PHP code against the `Drupal`, `DrupalPractice`, `DrevOps` and `PHPCompatibility` standards.
- PHPStan analyzes PHP code statically.
- Drupal Rector finds deprecated PHP code and fixes it.
- Twig CS Fixer checks Twig templates.
- ESLint checks JavaScript code.
- Stylelint checks CSS code.
- CSpell checks the spelling.

To run every check:

```bash
make lint
```

```bash
ahoy lint
```

### Fixing coding standards issues

To fix the issues that have an automatic fix, run the fixers:

```bash
make lint-fix
```

```bash
ahoy lint-fix
```

## Testing

To run every test:

```bash
make test
```

```bash
ahoy test
```

The PHPUnit tests live in the `tests/src` directory. The `phpunit.xml` file configures PHPUnit to run the tests, and `phpunit.d10.xml` replaces it in a Drupal 10 build. Both use Drupal core's bootstrap file, `web/core/tests/bootstrap.php`, to boot Drupal before the tests run.

To run 1 test suite:

```bash
make test-unit                    # Run Unit tests
make test-kernel                  # Run Kernel tests
make test-functional              # Run Functional tests
```

```bash
ahoy test-unit                    # Run Unit tests
ahoy test-kernel                  # Run Kernel tests
ahoy test-functional              # Run Functional tests
```

### Running specific tests

To run some of the tests, pass a test file path or a PHPUnit option, such as `--filter` or `--group`, to a test command. PHPUnit runs inside `build`, so a test path starts at the extension's symlink in the assembled site:

```bash
make test-unit web/modules/custom/filefield_paths/tests/src/Unit/MyUnitTest.php
make test-unit -- --group=wip
make test-unit -- --filter=MyUnitTest::testSomething
```

With `make`, put `--` before arguments that include an option, because `make` reads an option on its command line as its own. An argument can hold a space or name a `make` target, such as `test`, only as an option value joined with `=`: `--filter='my test'` or `--filter=test`. `make` keeps 1 value per option joined with `=`, so to repeat an option, separate it from each value with a space: `--group wip --group slow`.

```bash
ahoy test-unit web/modules/custom/filefield_paths/tests/src/Unit/MyUnitTest.php
ahoy test-unit -- --group=wip
ahoy test-unit -- --filter=MyUnitTest::testSomething
```

To run PHPUnit directly, run it from `build`:

```bash
cd build
php -d pcov.directory=.. vendor/bin/phpunit \
  web/modules/custom/filefield_paths/tests/src/Unit/MyUnitTest.php
php -d pcov.directory=.. vendor/bin/phpunit --group=wip
php -d pcov.directory=.. vendor/bin/phpunit --filter=MyUnitTest::testSomething
```
