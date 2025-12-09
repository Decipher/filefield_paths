<?php

namespace Drupal\filefield_paths\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\filefield_paths\Utility\FieldConfigEditFormHandlerInterface;
use Drupal\token\TokenEntityMapperInterface;

/**
 * File (Field) Paths field configuration form alter hook implementation.
 */
final class FieldConfigEditForm {

  use StringTranslationTrait;

  /**
   * Constructs a new FileFieldPathsFieldConfigEditForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\token\TokenEntityMapperInterface|null $tokenEntityMapper
   *   The token entity mapper service.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ?TokenEntityMapperInterface $tokenEntityMapper = NULL,
  ) {}

  /**
   * Implements hook_form_FORM_ID_alter() for field_config_edit_form.
   */
  // @phpstan-ignore-next-line
  #[Hook('form_field_config_edit_form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state) {// phpcs:ignore Squiz.WhiteSpace.FunctionSpacing.Before

    /** @var \Drupal\field\Entity\FieldConfig $field */
    $field = $form_state->getFormObject()->getEntity();
    $class = $field->getClass();

    if (!(class_exists($class) && ($class === FileFieldItemList::class || is_subclass_of($class, FileFieldItemList::class)))) {
      // Not supported the field config edit form.
      return;
    }

    $entity_info = $this->entityTypeManager->getDefinition($field->getTargetEntityTypeId());
    $settings = $field->getThirdPartySettings('filefield_paths');

    $form['settings']['filefield_paths'] = [
      '#type'    => 'container',
      '#tree'    => TRUE,
      '#weight'  => 2,
      '#parents' => ['third_party_settings', 'filefield_paths'],
    ];

    $form['settings']['filefield_paths']['enabled'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable File (Field) Paths?'),
      '#default_value' => $settings['enabled'] ?? TRUE,
      '#description'   => $this->t('File (Field) Paths provides advanced file path and naming options.'),
    ];

    // Hide standard File directory field.
    $form['settings']['file_directory']['#states'] = [
      'visible' => [
        ':input[name="third_party_settings[filefield_paths][enabled]"]' => ['checked' => FALSE],
      ],
    ];

    // File (Field) Paths details element.
    $form['settings']['filefield_paths']['details'] = [
      '#type'    => 'details',
      '#title'   => $this->t('File (Field) Path settings'),
      '#weight'  => 3,
      '#tree'    => TRUE,
      '#states'  => [
        'visible' => [
          ':input[name="third_party_settings[filefield_paths][enabled]"]' => ['checked' => TRUE],
        ],
      ],
      '#parents' => ['third_party_settings', 'filefield_paths'],
    ];

    // Additional File (Field) Paths widget fields.
    $settings_fields = $this->moduleHandler
      ->invokeAll('filefield_paths_field_settings', [$form]);
    foreach ($settings_fields as $name => $settings_field) {
      // Attach widget fields.
      $form['settings']['filefield_paths']['details'][$name] = [
        '#type' => 'container',
      ];

      // Attach widget field form elements.
      if (!(isset($settings_field['form']) && is_array($settings_field['form']))) {
        // No expected form elements.
        continue;
      }
      foreach (array_keys($settings_field['form']) as $delta => $key) {
        $form['settings']['filefield_paths']['details'][$name][$key] = $settings_field['form'][$key];
        if ($this->tokenEntityMapper !== NULL && $this->moduleHandler->moduleExists('token')) {
          $form['settings']['filefield_paths']['details'][$name][$key]['#element_validate'][] = 'token_element_validate';
          $form['settings']['filefield_paths']['details'][$name][$key]['#token_types'] = [
            'date',
            'file',
          ];
          $token_type = $this->tokenEntityMapper->getTokenTypeForEntityType($entity_info->id());
          if (!empty($token_type)) {
            $form['settings']['filefield_paths']['details'][$name][$key]['#token_types'][] = $token_type;
          }
        }

        // Fetch stored value from instance.
        if (isset($settings[$name][$key])) {
          $form['settings']['filefield_paths']['details'][$name][$key]['#default_value'] = $settings[$name][$key];
        }
      }

      // Field options.
      $form['settings']['filefield_paths']['details'][$name]['options'] = [
        '#type'       => 'details',
        '#title'      => $this->t('@title options', ['@title' => $settings_field['title']]),
        '#weight'     => 1,
        '#attributes' => [
          'class' => ["{$name} cleanup"],
        ],
      ];
      // Cleanup slashes (/).
      $form['settings']['filefield_paths']['details'][$name]['options']['slashes'] = [
        '#type'          => 'checkbox',
        '#title'         => $this->t('Remove slashes (/) from tokens'),
        '#default_value' => $settings[$name]['options']['slashes'] ?? FALSE,
        '#description'   => $this->t('If checked, any slashes (/) in tokens will be removed from %title.', ['%title' => $settings_field['title']]),
      ];

      // Cleanup field with Pathauto module.
      $form['settings']['filefield_paths']['details'][$name]['options']['pathauto'] = [
        '#type'          => 'checkbox',
        '#title'         => $this->t('Cleanup using Pathauto'),
        '#default_value' => isset($settings[$name]['options']['pathauto']) && $this->moduleHandler
          ->moduleExists('pathauto') ? $settings[$name]['options']['pathauto'] : FALSE,
        '#description'   => $this->t('Cleanup %title using Pathauto.', [
          '%title' => $settings_field['title'],
        ]),
        '#disabled'      => TRUE,
      ];
      if ($this->moduleHandler->moduleExists('pathauto')) {
        unset($form['settings']['filefield_paths']['details'][$name]['options']['pathauto']['#disabled']);
        $form['settings']['filefield_paths']['details'][$name]['options']['pathauto']['#description'] = $this->t('Cleanup %title using <a href="@pathauto">Pathauto settings</a>.', [
          '%title'    => $settings_field['title'],
          '@pathauto' => Url::fromRoute('pathauto.settings.form')->toString(),
        ]);
      }

      // Transliterate field.
      $form['settings']['filefield_paths']['details'][$name]['options']['transliterate'] = [
        '#type'          => 'checkbox',
        '#title'         => $this->t('Transliterate'),
        '#default_value' => $settings[$name]['options']['transliterate'] ?? 0,
        '#description'   => $this->t('Provides one-way string transliteration (romanization) and cleans the %title during upload by replacing unwanted characters.', ['%title' => $settings_field['title']]),
      ];

      // Replacement patterns for field.
      if ($this->moduleHandler->moduleExists('token')) {
        $form['settings']['filefield_paths']['details']['token_tree'] = [
          '#theme'       => 'token_tree_link',
          '#token_types' => ['file', $entity_info->id()],
          '#weight'      => 10,
          '#text' => $this->t('Browse available tokens.') . ' ' . $this->t('List of tokens available for replacement by File Field Paths.'),
        ];
        if (!empty($token_type)) {
          $form['settings']['filefield_paths']['details']['token_tree']['#token_types'][] = $token_type;
        }
      }

      $description = $this->t('The location that unprocessed files will be uploaded prior to being processed by File (Field) Paths.');
      $description .= '<br />';
      $description .= $this->t('It is recommended to use the temporary file system (temporary://) whenever possible, especially for files that do not require previewing before form submission. Alternatively, if your server configuration permits, the private file system (private://) is preferred for situations where file previews — such as image previews — are needed before the form is submitted, as it provides secure and appropriate access for this functionality.');
      $description .= '<br />';
      $description .= '<strong>' . $this->t('Never use the public directory (public://) if the site supports private files, or private files can be temporarily exposed publicly.') . '</strong>';
      $description .= '<br />';
      $description .= $this->t('Leave blank to use <a href=":url">global setting</a>.', [':url' => Url::fromRoute('filefield_paths.admin_settings')->toString()]);
      // Temporary file path.
      $form['settings']['filefield_paths']['details']['temp_location'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Temporary file location'),
        '#description' => $description,
        '#default_value' => $settings['temp_location'] ?? FALSE,
        '#element_validate' => [[self::class, 'elementTempLocationValidate']],
        '#weight' => 10,
        '#attributes' => [
          'placeholder' => $this->configFactory->get('filefield_paths.settings')
            ->get('temp_location'),
        ],
      ];

      // Redirect.
      $form['settings']['filefield_paths']['details']['redirect'] = [
        '#type'          => 'checkbox',
        '#title'         => $this->t('Create Redirect'),
        '#description'   => $this->t('Create a redirect to the new location when a previously uploaded file is moved.'),
        '#default_value' => $settings['redirect'] ?? FALSE,
        '#weight'        => 11,
      ];
      if (!$this->moduleHandler->moduleExists('redirect')) {
        $form['settings']['filefield_paths']['details']['redirect']['#disabled'] = TRUE;
        $form['settings']['filefield_paths']['details']['redirect']['#description'] .= '<br />' . $this->t('Requires the <a href="https://drupal.org/project/redirect" target="_blank">Redirect</a> module.');
      }

      // Retroactive updates.
      $form['settings']['filefield_paths']['details']['retroactive_update'] = [
        '#type'        => 'checkbox',
        '#title'       => $this->t('Retroactive update'),
        '#description' => $this->t('Move and rename previously uploaded files. After saving the field settings, the paths of all previously uploaded files will be updated immediately. This will only occur once. So after the operation is done, this option will be disabled again.') . '<div>' . $this->t('<strong class="warning">Warning:</strong> This feature should only be used on developmental servers or with extreme caution.') . '</div>',
        '#weight'      => 12,
      ];

      // Active updating.
      $form['settings']['filefield_paths']['details']['active_updating'] = [
        '#type'          => 'checkbox',
        '#title'         => $this->t('Active updating'),
        '#default_value' => $settings['active_updating'] ?? FALSE,
        '#description'   => $this->t('Actively move and rename previously uploaded files as required. If necessary, the paths of previously uploaded files are updated each time the entity they belong to gets saved.') . '<div>' . $this->t('<strong class="warning">Warning:</strong> This feature should only be used on developmental servers or with extreme caution.') . '</div>',
        '#weight'        => 13,
      ];
    }
    $form['actions']['submit']['#submit'][] = [self::class, 'submit'];
  }

  /**
   * Submit handler for the field configuration form.
   *
   * @internal
   */
  public static function submit(array $form, FormStateInterface $form_state): void {
    \Drupal::service(FieldConfigEditFormHandlerInterface::class)->submit($form, $form_state);
  }

  /**
   * Validate the temporary upload location.
   *
   * @internal
   */
  public static function elementTempLocationValidate(array $element, FormStateInterface $form_state): void {
    \Drupal::service(FieldConfigEditFormHandlerInterface::class)->elementTempLocationValidate($element, $form_state);
  }

}
