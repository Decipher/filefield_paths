<?php

namespace Drupal\filefield_paths;

/**
 * Interface defining methods for processing file moves.
 */
interface MoveFileProcessorInterface {

  public const TEMP_SCHEME_CID = 'filefield_paths:recommended_temporary_scheme';

  /**
   * Get the recommended file scheme based on which file systems are enabled.
   */
  public function recommendedTemporaryScheme(): string;

  /**
   * Detect the recommended file scheme based on which file systems are enabled.
   *
   * @internal
   */
  public function detectRecommendedScheme(array $wrappers): string;

}
