<?php

declare(strict_types=1);

namespace Drupal\filefield_paths;

/**
 * Collects the result of processing each file.
 */
final class ProcessOutcome implements ProcessOutcomeInterface {

  /**
   * The result for each file, keyed by file ID.
   *
   * @var array<int|string, string>
   */
  private array $outcomes = [];

  /**
   * {@inheritdoc}
   */
  public function recordUpdated(int|string $fid): void {
    $this->outcomes[$fid] = self::UPDATED;
  }

  /**
   * {@inheritdoc}
   */
  public function recordSkipped(int|string $fid): void {
    $this->outcomes[$fid] = self::SKIPPED;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutcomes(): array {
    return $this->outcomes;
  }

  /**
   * {@inheritdoc}
   */
  public function reset(): void {
    $this->outcomes = [];
  }

}
