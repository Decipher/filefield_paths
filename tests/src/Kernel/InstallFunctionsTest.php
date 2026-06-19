<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\filefield_paths\MoveFileProcessorInterface;

/**
 * Tests functions in filefield_paths.install.
 *
 * @group filefield_paths
 */
class InstallFunctionsTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array<string>
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'filefield_paths',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['filefield_paths']);

    // The .install file is not auto-loaded in kernel tests.
    \Drupal::moduleHandler()->loadInclude('filefield_paths', 'install');
  }

  /**
   * Tests that requirements() flags public:// temp location at runtime.
   */
  public function testRequirementsPublicSchemeIsError(): void {
    $this->config('filefield_paths.settings')
      ->set('temp_location', 'public://filefield_paths')
      ->save();

    $requirements = filefield_paths_requirements('runtime');

    $this->assertArrayHasKey('filefield_paths', $requirements);
    $this->assertSame(DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn(): RequirementSeverity => RequirementSeverity::Error, fn() => REQUIREMENT_ERROR), $requirements['filefield_paths']['severity']);
  }

  /**
   * Tests that requirements() passes for a secure temp location.
   */
  public function testRequirementsSecureSchemeIsClean(): void {
    $this->config('filefield_paths.settings')
      ->set('temp_location', 'temporary://filefield_paths')
      ->save();

    $requirements = filefield_paths_requirements('runtime');

    $this->assertArrayNotHasKey('filefield_paths', $requirements);
  }

  /**
   * Tests that requirements() returns empty when temp_location is unset.
   */
  public function testRequirementsNoTempLocation(): void {
    $this->config('filefield_paths.settings')
      ->clear('temp_location')
      ->save();

    $requirements = filefield_paths_requirements('runtime');

    $this->assertSame([], $requirements);
  }

  /**
   * Tests that requirements() only checks during the runtime phase.
   */
  public function testRequirementsNonRuntimePhase(): void {
    $this->config('filefield_paths.settings')
      ->set('temp_location', 'public://filefield_paths')
      ->save();

    $requirements = filefield_paths_requirements('install');

    $this->assertSame([], $requirements);
  }

  /**
   * Tests that update_temporary_location_configuration migrates public://.
   */
  public function testUpdateMigratesPublicScheme(): void {
    $this->config('filefield_paths.settings')
      ->set('temp_location', 'public://filefield_paths')
      ->save();

    filefield_paths_update_temporary_location_configuration();

    $updated = $this->config('filefield_paths.settings')->get('temp_location');
    $recommended = $this->container->get(MoveFileProcessorInterface::class)
      ->recommendedTemporaryScheme();

    \assert($recommended !== '');
    $this->assertStringStartsWith($recommended, $updated);
    $this->assertStringEndsWith('filefield_paths', $updated);
  }

  /**
   * Tests that update leaves non-public:// temp locations unchanged.
   */
  public function testUpdateLeavesSecureScheme(): void {
    $this->config('filefield_paths.settings')
      ->set('temp_location', 'temporary://custom-path')
      ->save();

    filefield_paths_update_temporary_location_configuration();

    $this->assertSame(
      'temporary://custom-path',
      $this->config('filefield_paths.settings')->get('temp_location')
    );
  }

  /**
   * Tests that update returns early when temp_location is unset.
   */
  public function testUpdateReturnsEarlyWhenUnset(): void {
    $this->config('filefield_paths.settings')
      ->clear('temp_location')
      ->save();

    filefield_paths_update_temporary_location_configuration();

    $this->assertNull($this->config('filefield_paths.settings')->get('temp_location'));
  }

  /**
   * Tests that update_9001 delegates to the config migration helper.
   */
  public function testUpdate9001Delegates(): void {
    $this->config('filefield_paths.settings')
      ->set('temp_location', 'public://filefield_paths')
      ->save();

    filefield_paths_update_9001();

    $updated = $this->config('filefield_paths.settings')->get('temp_location');
    $this->assertNotSame('public://filefield_paths', $updated);
    $this->assertStringEndsWith('filefield_paths', $updated);
  }

}
