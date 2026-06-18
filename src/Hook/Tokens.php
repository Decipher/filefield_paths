<?php

declare(strict_types=1);

namespace Drupal\filefield_paths\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Tokens for the File (Field) Paths module.
 */
final class Tokens {

  use StringTranslationTrait;

  /**
   * Implements hook_token_info().
   */
  // @phpstan-ignore-next-line
  #[Hook('token_info')]
  public function tokenInfo(): array {// phpcs:ignore Squiz.WhiteSpace.FunctionSpacing.Before
    $info['tokens']['file']['ffp-name-only'] = [
      'name'        => $this->t("File name"),
      'description' => $this->t("File name without extension."),
    ];

    $info['tokens']['file']['ffp-name-only-original'] = [
      'name'        => $this->t("File name - original"),
      'description' => $this->t("File name without extension - original."),
    ];

    $info['tokens']['file']['ffp-extension-original'] = [
      'name'        => $this->t("File extension - original"),
      'description' => $this->t("File extension - original."),
    ];

    return $info;
  }

  /**
   * Implements hook_tokens().
   */
  // @phpstan-ignore-next-line
  #[Hook('tokens')]
  public function tokensProvider(string $type, array $tokens, array $data, array $options = [], ?BubbleableMetadata $bubbleable_metadata = NULL): array {// phpcs:ignore Squiz.WhiteSpace.FunctionSpacing.Before
    $replacements = [];

    if ($type === 'file' && !empty($data['file'])) {
      /** @var \Drupal\file\Entity\File $file */
      $file = $data['file'];

      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'ffp-name-only':
            $basename = basename((string) $file->filename->value);
            $extension = preg_match('/\.[^.]+$/', $basename, $matches) ? $matches[0] : NULL;
            $replacements[$original] = is_null($extension) ? $basename : mb_substr($basename, 0, mb_strlen($basename) - mb_strlen($extension));
            break;

          case 'ffp-name-only-original':
            $basename = basename((string) $file->origname->value);
            $extension = preg_match('/\.[^.]+$/', $basename, $matches) ? $matches[0] : NULL;
            $replacements[$original] = is_null($extension) ? $basename : mb_substr($basename, 0, mb_strlen($basename) - mb_strlen($extension));
            break;

          case 'ffp-extension-original':
            $replacements[$original] = preg_match('/[^.]+$/', basename((string) $file->origname->value), $matches) ? $matches[0] : NULL;
            break;
        }
      }
    }

    return $replacements;
  }

}
