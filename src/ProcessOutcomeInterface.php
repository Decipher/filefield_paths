<?php

declare(strict_types=1);

namespace Drupal\filefield_paths;

/**
 * Records what happened to each file during processing.
 *
 * File processing runs through a hook, which cannot return a value to its
 * caller. A batch run needs to know the result for each file, so the
 * processor records it here and the batch reads it back.
 */
interface ProcessOutcomeInterface {

  /**
   * The file is at the location the field settings ask for.
   */
  const UPDATED = 'updated';

  /**
   * The file is not at the location the field settings ask for.
   */
  const SKIPPED = 'skipped';

  /**
   * Records that a file is at its correct location.
   *
   * @param int|string $fid
   *   The file ID.
   */
  public function recordUpdated(int|string $fid): void;

  /**
   * Records that a file could not be moved to its correct location.
   *
   * @param int|string $fid
   *   The file ID.
   */
  public function recordSkipped(int|string $fid): void;

  /**
   * Gets the recorded outcome for each file.
   *
   * @return array<int|string, string>
   *   The outcome for each file, keyed by file ID. Each value is
   *   self::UPDATED or self::SKIPPED.
   */
  public function getOutcomes(): array;

  /**
   * Removes all recorded outcomes.
   */
  public function reset(): void;

}
