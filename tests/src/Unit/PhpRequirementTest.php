<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Unit;

use PHPUnit\Framework\Attributes\Group;
use Composer\Semver\Semver;
use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;

/**
 * Tests that the module declares the PHP version its code needs.
 *
 * The code uses syntax that an older PHP cannot parse. Composer and Drupal
 * refuse that PHP only if the module declares its minimum version.
 *
 * @group filefield_paths
 * @coversNothing
 */
#[Group('filefield_paths')]
class PhpRequirementTest extends UnitTestCase {

  /**
   * The minimum PHP version for a readonly class.
   */
  private const READONLY_CLASS_PHP = '8.2';

  /**
   * The minimum PHP version for Drupal 10, the oldest supported core.
   */
  private const DRUPAL_10_PHP = '8.1';

  /**
   * Tests that the info file declares the PHP version the code needs.
   */
  public function testInfoFileDeclaresRequiredPhp(): void {
    $php = $this->infoFilePhp();
    $required = $this->requiredPhp();

    // Drupal compares this value with version_compare() too.
    $this->assertTrue(
      version_compare($php, $required, '>='),
      sprintf('filefield_paths.info.yml declares PHP %s, but the code needs PHP %s.', $php, $required),
    );
  }

  /**
   * Tests that composer.json rejects a PHP version the code cannot run on.
   */
  public function testComposerRejectsOlderPhp(): void {
    $constraint = $this->composerPhp();
    $required = $this->requiredPhp();
    $below = $this->versionBelow($required);

    $this->assertFalse(
      Semver::satisfies($below, $constraint),
      sprintf('composer.json allows PHP %s with "%s", but the code needs PHP %s.', $below, $constraint, $required),
    );
  }

  /**
   * Tests that composer.json and the info file declare the same minimum.
   */
  public function testComposerMatchesInfoFile(): void {
    $constraint = $this->composerPhp();
    $php = $this->infoFilePhp();

    $this->assertTrue(
      Semver::satisfies($php . '.0', $constraint),
      sprintf('composer.json "%s" rejects PHP %s, the minimum in the info file.', $constraint, $php),
    );
    $this->assertFalse(
      Semver::satisfies($this->versionBelow($php), $constraint),
      sprintf('composer.json "%s" allows a PHP older than %s, the minimum in the info file.', $constraint, $php),
    );
  }

  /**
   * Returns the minimum PHP version in the info file.
   *
   * @return string
   *   The value of the php key, for example "8.2".
   */
  private function infoFilePhp(): string {
    $info = Yaml::decode($this->readModuleFile('filefield_paths.info.yml'));
    $this->assertIsArray($info);
    $php = $info['php'] ?? NULL;
    $this->assertIsScalar($php, 'filefield_paths.info.yml does not declare a minimum PHP version.');

    return (string) $php;
  }

  /**
   * Returns the PHP constraint in composer.json.
   *
   * @return string
   *   The constraint, for example ">=8.2".
   */
  private function composerPhp(): string {
    $composer = json_decode($this->readModuleFile('composer.json'), TRUE);
    $this->assertIsArray($composer);
    $require = $composer['require'] ?? [];
    $this->assertIsArray($require);
    $constraint = $require['php'] ?? NULL;
    $this->assertIsString($constraint, 'composer.json does not require a minimum PHP version.');

    return $constraint;
  }

  /**
   * Returns the minimum PHP version that the code in src/ needs.
   *
   * @return string
   *   The PHP version, for example "8.2".
   */
  private function requiredPhp(): string {
    $files = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($this->moduleRoot() . '/src', \FilesystemIterator::SKIP_DOTS),
    );
    foreach ($files as $file) {
      if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
        continue;
      }
      $code = (string) file_get_contents($file->getPathname());
      if (preg_match('/^\s*(?:(?:final|abstract)\s+)*readonly\s+(?:(?:final|abstract)\s+)*class\s/m', $code) === 1) {
        return self::READONLY_CLASS_PHP;
      }
    }

    return self::DRUPAL_10_PHP;
  }

  /**
   * Returns the last patch release before a minor version.
   *
   * @param string $version
   *   A minor version, for example "8.2".
   *
   * @return string
   *   The release before it, for example "8.1.99".
   */
  private function versionBelow(string $version): string {
    [$major, $minor] = array_map(intval(...), explode('.', $version));

    return $minor > 0 ? sprintf('%d.%d.99', $major, $minor - 1) : sprintf('%d.99.99', $major - 1);
  }

  /**
   * Reads a file from the module root.
   *
   * @param string $name
   *   The file name, relative to the module root.
   *
   * @return string
   *   The file contents.
   */
  private function readModuleFile(string $name): string {
    $contents = file_get_contents($this->moduleRoot() . '/' . $name);
    $this->assertIsString($contents, sprintf('Cannot read %s.', $name));

    return $contents;
  }

  /**
   * Returns the module root directory.
   *
   * @return string
   *   The absolute path of the directory that holds the info file.
   */
  private function moduleRoot(): string {
    return dirname(__DIR__, 3);
  }

}
