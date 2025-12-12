<?php

namespace Drupal\filefield_paths;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\Token;
use Drupal\pathauto\AliasCleanerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides functionality to process and clean strings.
 *
 * Ensures that strings are cleaned according to specified settings, such as
 * token replacement, transliteration of non-roman characters, and removal of
 * slashes. Additionally, it supports integration with the Pathauto module
 * for alias cleaning when available.
 */
class PathProcessor implements PathProcessorInterface {

  /**
   * Constructs a PathProcessor object.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
   *   The transliteration service.
   * @param \Drupal\pathauto\AliasCleanerInterface|null $aliasCleaner
   *   The alias cleaner service.
   */
  public function __construct(
    protected readonly RendererInterface $renderer,
    protected readonly Token $token,
    #[Autowire(service: 'transliteration')]
    protected readonly TransliterationInterface $transliteration,
    #[Autowire(service: 'pathauto.alias_cleaner')]
    protected readonly ?AliasCleanerInterface $aliasCleaner = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function processString(string $value, array $data, array $settings = []): string {
    $context = new RenderContext();
    $that = $this;
    $result = $this->renderer->executeInRenderContext(
      $context,
      static function () use ($that, $value, $data, $settings) {
        return $that->doProcessString($value, $data, $settings);
      },
    );

    // Handle any bubbled cacheability metadata.
    if (!$context->isEmpty()) {
      $bubbleable_metadata = $context->pop();
      BubbleableMetadata::createFromObject($result)
        ->merge($bubbleable_metadata);
    }

    return $result;
  }

  /**
   * Processes and cleans strings without using the render context.
   *
   * @param string $value
   *   The string to clean, can contain tokens.
   * @param array $data
   *   An array of keyed objects. This data is passed to the token service when
   *   replacing tokens. See \Drupal\Core\Utility\Token::replace() for more
   *   information.
   * @param array $settings
   *   (optional) A keyed array of settings to control the cleanup process.
   *   Supported options are:
   *   - transliterate: A boolean flag indicating that non-roman characters
   *     should be replaced.
   *   - pathauto: A boolean flag indicating that the string should be cleaned
   *     using Pathauto's Alias cleaner service.
   *   - slashes: A boolean flag indicating that any slashes should be removed
   *     from the string.
   *
   * @return string
   *   The cleaned string, in which tokens are replaced and other alterations
   *   may have been applied, depending on the settings.
   *
   * @see \Drupal\filefield_paths\PathProcessor::processString()
   * @internal
   */
  public function doProcessString(string $value, array $data, array $settings = []): string {
    $transliterate = (bool) $settings['transliterate'];
    $pathauto = (isset($settings['pathauto']) && $settings['pathauto']) &&
      ($this->aliasCleaner !== NULL);
    $remove_slashes = !empty($settings['slashes']);

    // If '/' is to be removed from tokens, token replacement needs to happen
    // after splitting the paths to subdirectories. Otherwise,
    // tokens containing '/' will be part of the final path.
    if (!$remove_slashes) {
      $value = $this->token->replace($value, $data, ['clear' => TRUE]);
    }
    $paths = explode('/', $value);

    foreach ($paths as $i => &$path) {
      if ($remove_slashes) {
        $path = $this->token->replace($path, $data, ['clear' => TRUE]);
      }
      if ($pathauto) {
        if (('file_name' === $settings['context']) && (count($paths) === $i + 1)) {
          $pathinfo = pathinfo($path);
          $basename = basename($path);
          $extension = preg_match('/\.[^.]+$/', $basename, $matches) ? $matches[0] : NULL;
          $pathinfo['filename'] = !is_null($extension) ? mb_substr($basename, 0, mb_strlen($basename) - mb_strlen($extension)) : $basename;

          if ($remove_slashes) {
            $path = '';
            if (!empty($pathinfo['dirname']) && $pathinfo['dirname'] !== '.') {
              $path .= $pathinfo['dirname'] . '/';
            }
            $path .= $pathinfo['filename'];
            $path = $this->aliasCleaner->cleanstring($path);
            if (!empty($pathinfo['extension'])) {
              $path .= '.' . $this->aliasCleaner->cleanstring($pathinfo['extension']);
            }
            $path = str_replace('/', '', $path);
          }
          else {
            $path = str_replace(
              $pathinfo['filename'],
              $this->aliasCleaner->cleanstring($pathinfo['filename']),
              $path,
            );
          }
        }
        else {
          $path = $this->aliasCleaner->cleanstring($path);
        }
      }
      elseif ($remove_slashes) {
        $path = str_replace('/', '', $path);
      }

      // Transliterate string.
      if ($transliterate) {
        $path = $this->transliteration->transliterate($path);
      }
    }
    $value = implode('/', $paths);

    // Ensure that there are no double-slash sequences due to empty token
    // values.
    return preg_replace('/\/+/', '/', $value);
  }

}
