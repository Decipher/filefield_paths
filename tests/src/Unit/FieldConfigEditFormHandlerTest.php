<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\filefield_paths\Batch\BatchUpdaterInterface;
use Drupal\filefield_paths\Utility\FieldConfigEditFormHandler;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the FieldConfigEditFormHandler utility.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Utility\FieldConfigEditFormHandler
 */
#[Group('filefield_paths')]
class FieldConfigEditFormHandlerTest extends UnitTestCase {

  /**
   * Tests that submit() does nothing when retroactive update is disabled.
   */
  public function testSubmitSkipsWhenRetroactiveDisabled(): void {
    $handler = $this->buildHandler(
      static fn () => throw new \LogicException('Updater should not be called.'),
      static fn () => throw new \LogicException('SWM should not be called.'),
    );

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->with('third_party_settings')
      ->willReturn(['filefield_paths' => ['enabled' => TRUE, 'retroactive_update' => FALSE]]);

    $handler->submit([], $form_state);
    $this->addToAssertionCount(1);
  }

  /**
   * Tests that submit() does nothing when disabled is false.
   */
  public function testSubmitSkipsWhenNotEnabled(): void {
    $handler = $this->buildHandler(
      static fn () => throw new \LogicException('Updater should not be called.'),
      static fn () => throw new \LogicException('SWM should not be called.'),
    );

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->with('third_party_settings')
      ->willReturn(['filefield_paths' => ['enabled' => FALSE, 'retroactive_update' => TRUE]]);

    $handler->submit([], $form_state);
    $this->addToAssertionCount(1);
  }

  /**
   * Tests that submit() bails when the form object is not EntityFormInterface.
   */
  public function testSubmitSkipsNonEntityForm(): void {
    $handler = $this->buildHandler(
      static fn () => throw new \LogicException('Updater should not be called.'),
      static fn () => throw new \LogicException('SWM should not be called.'),
    );

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->with('third_party_settings')
      ->willReturn(['filefield_paths' => ['enabled' => TRUE, 'retroactive_update' => TRUE]]);
    $form_state->method('getFormObject')->willReturn($this->createMock(FormInterface::class));

    $handler->submit([], $form_state);
    $this->addToAssertionCount(1);
  }

  /**
   * Tests that submit() bails when the entity is not a FieldConfigInterface.
   */
  public function testSubmitSkipsNonFieldConfig(): void {
    $handler = $this->buildHandler(
      static fn () => throw new \LogicException('Updater should not be called.'),
      static fn () => throw new \LogicException('SWM should not be called.'),
    );

    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn(new \stdClass());

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->with('third_party_settings')
      ->willReturn(['filefield_paths' => ['enabled' => TRUE, 'retroactive_update' => TRUE]]);
    $form_state->method('getFormObject')->willReturn($form_object);

    $handler->submit([], $form_state);
    $this->addToAssertionCount(1);
  }

  /**
   * Tests that submit() bails when batchUpdate() returns FALSE.
   */
  public function testSubmitSkipsWhenNoPathsToUpdate(): void {
    $updater = $this->createMock(BatchUpdaterInterface::class);
    $field_config = $this->createMock(FieldConfigInterface::class);

    $updater->expects($this->once())->method('batchUpdate')
      ->with($field_config)->willReturn(FALSE);

    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($field_config);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->with('third_party_settings')
      ->willReturn(['filefield_paths' => ['enabled' => TRUE, 'retroactive_update' => TRUE]]);
    $form_state->method('getFormObject')->willReturn($form_object);

    $handler = $this->buildHandler(
      static fn (): MockObject => $updater,
      static fn () => throw new \LogicException('SWM should not be called.'),
    );
    $handler->submit([], $form_state);
    $this->addToAssertionCount(1);
  }

  /**
   * Tests that submit() re-enables the form redirect when a batch is set.
   *
   * AJAX submissions disable the redirect, and the batch would keep that
   * state. The finished batch then falls back to the request URL, which
   * still carries the ajax_form query arguments and fails on a normal
   * page load.
   */
  public function testSubmitReEnablesRedirectWhenBatchSet(): void {
    $updater = $this->createMock(BatchUpdaterInterface::class);
    $field_config = $this->createMock(FieldConfigInterface::class);

    $updater->expects($this->once())->method('batchUpdate')
      ->with($field_config)->willReturn(TRUE);

    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($field_config);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->with('third_party_settings')
      ->willReturn(['filefield_paths' => ['enabled' => TRUE, 'retroactive_update' => TRUE]]);
    $form_state->method('getFormObject')->willReturn($form_object);
    $form_state->expects($this->once())->method('disableRedirect')->with(FALSE);

    $handler = $this->buildHandler(
      static fn (): MockObject => $updater,
      static fn () => throw new \LogicException('SWM should not be called.'),
    );
    $handler->submit([], $form_state);
  }

  /**
   * Tests elementTempLocationValidate() with an empty value.
   */
  public function testValidateSkipsEmptyValue(): void {
    $handler = $this->buildHandler(
      static fn () => throw new \LogicException('Updater should not be called.'),
      static fn () => throw new \LogicException('SWM should not be called.'),
    );

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->never())->method('setError');

    $handler->elementTempLocationValidate(['#value' => ''], $form_state);
    $this->addToAssertionCount(1);
  }

  /**
   * Tests elementTempLocationValidate() accepts a valid stream wrapper URI.
   */
  public function testValidateAcceptsValidUri(): void {
    $swm = $this->createMock(StreamWrapperManagerInterface::class);
    $swm->expects($this->once())->method('getViaUri')
      ->with('temporary://custom')->willReturnSelf();

    $handler = $this->buildHandler(
      static fn () => throw new \LogicException('Updater should not be called.'),
      static fn (): MockObject => $swm,
    );

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->never())->method('setError');

    $handler->elementTempLocationValidate(['#value' => 'temporary://custom'], $form_state);
    $this->addToAssertionCount(1);
  }

  /**
   * Tests elementTempLocationValidate() rejects an invalid URI.
   */
  public function testValidateRejectsInvalidUri(): void {
    $swm = $this->createMock(StreamWrapperManagerInterface::class);
    $swm->expects($this->once())->method('getViaUri')
      ->with('invalid://path')->willReturn(FALSE);

    $handler = $this->buildHandler(
      static fn () => throw new \LogicException('Updater should not be called.'),
      static fn (): MockObject => $swm,
    );

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())->method('setError');

    $handler->setStringTranslation($this->createMock(TranslationInterface::class));
    $handler->elementTempLocationValidate(['#value' => 'invalid://path'], $form_state);
    $this->addToAssertionCount(1);
  }

  /**
   * Builds a handler with the given dependency closures.
   */
  private function buildHandler(\Closure $updater, \Closure $swm): FieldConfigEditFormHandler {
    return new FieldConfigEditFormHandler($updater, $swm);
  }

}
