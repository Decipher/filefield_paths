<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Language\Language;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests deprecated procedural wrappers in filefield_paths.module.
 *
 * Each wrapper should trigger an E_USER_DEPRECATED notice and delegate to
 * the corresponding service.
 *
 * @group filefield_paths
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
class ModuleDeprecatedWrappersTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array<string>
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'field',
    'entity_test',
    'filefield_paths',
    'token',
    'path_alias',
    'pathauto',
    'redirect',
    'link',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('redirect');
    $this->installEntitySchema('path_alias');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['filefield_paths', 'pathauto']);

    FieldStorageConfig::create([
      'field_name' => 'field_file',
      'entity_type' => 'entity_test',
      'type' => 'file',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_file',
      'bundle' => 'entity_test',
    ])->save();

    $this->config('redirect.settings')->set('default_status_code', 301)->save();
  }

  /**
   * Calls a callable and asserts that a specific deprecation was triggered.
   *
   * @param callable $fn
   *   The callable to execute.
   * @param string $expectedFragment
   *   A fragment expected in the deprecation message.
   *
   * @return mixed
   *   The return value of the callable.
   */
  private function callExpectingDeprecation(callable $fn, string $expectedFragment): mixed {
    $caught = FALSE;
    \set_error_handler(function (int $errno, string $errstr) use ($expectedFragment, &$caught): bool {
      if ($errno === \E_USER_DEPRECATED && \str_contains($errstr, $expectedFragment)) {
        $caught = TRUE;
        return TRUE;
      }
      return FALSE;
    }, \E_USER_DEPRECATED);
    try {
      $result = $fn();
    }
    finally {
      \restore_error_handler();
    }
    $this->assertTrue($caught, 'Expected deprecation containing: ' . $expectedFragment);
    return $result;
  }

  /**
   * Tests that filefield_paths_process_string() is deprecated and delegates.
   */
  public function testProcessStringDeprecated(): void {
    $result = $this->callExpectingDeprecation(
      fn(): string => filefield_paths_process_string('foo/bar', [], ['transliterate' => FALSE]),
      'filefield_paths_process_string() is deprecated'
    );
    $this->assertSame('foo/bar', $result);
  }

  /**
   * Tests that filefield_paths_recommended_temporary_scheme() is deprecated.
   */
  public function testRecommendedTemporarySchemeDeprecated(): void {
    $result = $this->callExpectingDeprecation(
      fn(): string => filefield_paths_recommended_temporary_scheme(),
      'filefield_paths_recommended_temporary_scheme() is deprecated'
    );
    $this->assertStringEndsWith('://', $result);
  }

  /**
   * Tests that filefield_paths_batch_update() is deprecated and delegates.
   */
  public function testBatchUpdateDeprecated(): void {
    $field_config = FieldConfig::load('entity_test.entity_test.field_file');
    $result = $this->callExpectingDeprecation(
      fn(): bool => filefield_paths_batch_update($field_config),
      'filefield_paths_batch_update() is deprecated'
    );
    $this->assertFalse($result);
  }

  /**
   * Tests that filefield_paths_form_submit() is deprecated and delegates.
   */
  public function testFormSubmitDeprecated(): void {
    $form_state = new FormState();
    $form_state->setValues([
      'third_party_settings' => [
        'filefield_paths' => [
          'enabled' => FALSE,
          'retroactive_update' => FALSE,
        ],
      ],
    ]);

    $this->callExpectingDeprecation(
      fn() => filefield_paths_form_submit([], $form_state),
      'filefield_paths_form_submit() is deprecated'
    );
  }

  /**
   * Tests filefield_paths_element_temp_location_validate() is deprecated.
   */
  public function testElementTempLocationValidateDeprecated(): void {
    $this->callExpectingDeprecation(
      fn() => filefield_paths_element_temp_location_validate(['#default_value' => ''], new FormState()),
      'filefield_paths_element_temp_location_validate() is deprecated'
    );
  }

  /**
   * Tests that _filefield_paths_create_redirect() is deprecated and delegates.
   */
  public function testCreateRedirectDeprecated(): void {
    $this->callExpectingDeprecation(
      fn() => _filefield_paths_create_redirect(
        'public://old/source.txt',
        'public://new/destination.txt',
        new Language(['id' => 'en'])
      ),
      '_filefield_paths_create_redirect() is deprecated'
    );

    $redirects = $this->container->get('entity_type.manager')
      ->getStorage('redirect')->loadMultiple();
    $this->assertCount(1, $redirects);
  }

}
