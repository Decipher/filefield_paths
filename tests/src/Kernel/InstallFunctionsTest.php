<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\filefield_paths\MoveFileProcessorInterface;

/**
 * Tests functions in filefield_paths.install.
 *
 * @group filefield_paths
 */
#[Group('filefield_paths')]
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
   * Tests that requirements() flags the global toggle being disabled.
   */
  public function testRequirementsGlobalDisabledIsWarning(): void {
    $this->config('filefield_paths.settings')
      ->set('enabled', FALSE)
      ->save();

    $requirements = filefield_paths_requirements('runtime');

    $this->assertArrayHasKey('filefield_paths_enabled', $requirements);
    $this->assertSame(DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn(): RequirementSeverity => RequirementSeverity::Warning, fn() => REQUIREMENT_WARNING), $requirements['filefield_paths_enabled']['severity']);
  }

  /**
   * Tests that requirements() passes when the global toggle is enabled.
   */
  public function testRequirementsGlobalEnabledIsClean(): void {
    $this->config('filefield_paths.settings')
      ->set('enabled', TRUE)
      ->save();

    $requirements = filefield_paths_requirements('runtime');

    $this->assertArrayNotHasKey('filefield_paths_enabled', $requirements);
  }

  /**
   * Tests that requirements() does not warn when 'enabled' is merely unset.
   *
   * An unset value means an existing site predates the setting and the
   * update hook has not run yet, not that it was deliberately disabled.
   */
  public function testRequirementsUnsetEnabledIsClean(): void {
    $this->config('filefield_paths.settings')
      ->clear('enabled')
      ->save();

    $requirements = filefield_paths_requirements('runtime');

    $this->assertArrayNotHasKey('filefield_paths_enabled', $requirements);
  }

  /**
   * Tests that update_9002 defaults an unset 'enabled' setting to TRUE.
   */
  public function testUpdate9002DefaultsToEnabled(): void {
    $this->config('filefield_paths.settings')
      ->clear('enabled')
      ->save();

    filefield_paths_update_9002();

    $this->assertTrue($this->config('filefield_paths.settings')->get('enabled'));
  }

  /**
   * Tests that update_9002 leaves an explicit 'enabled' value untouched.
   */
  public function testUpdate9002LeavesExplicitValueUntouched(): void {
    $this->config('filefield_paths.settings')
      ->set('enabled', FALSE)
      ->save();

    filefield_paths_update_9002();

    $this->assertFalse($this->config('filefield_paths.settings')->get('enabled'));
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

  /**
   * Tests that update_8001 installs the origname field storage definition.
   */
  public function testUpdate8001InstallsOrigname(): void {
    filefield_paths_update_8001();

    $field_definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('file', 'file');
    $this->assertArrayHasKey('origname', $field_definitions);
  }

}
