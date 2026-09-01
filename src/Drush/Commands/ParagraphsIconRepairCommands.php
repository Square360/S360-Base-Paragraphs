<?php

declare(strict_types=1);

namespace Drupal\s360_base_paragraphs\Drush\Commands;

use Drupal\s360_base_paragraphs\ParagraphsIconRepair;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for repairing paragraph type icons.
 */
final class ParagraphsIconRepairCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs a ParagraphsIconRepairCommands object.
   *
   * @param \Drupal\s360_base_paragraphs\ParagraphsIconRepair $paragraphsIconRepair
   *   The paragraph icon repair service.
   */
  public function __construct(
    private readonly ParagraphsIconRepair $paragraphsIconRepair,
  ) {
    parent::__construct();
  }

  /**
   * Regenerates paragraph type icons whose files are missing from disk.
   *
   * @param array $options
   *   The command options.
   *
   * @return int
   *   The command exit code.
   */
  #[CLI\Command(name: 's360-base-paragraphs:repair-icons', aliases: ['s360:repair-icons'])]
  #[CLI\Option(name: 'dry-run', description: 'Report what would be repaired without changing anything.')]
  #[CLI\Usage(name: 'drush s360:repair-icons --dry-run', description: 'List paragraph types with a missing icon file.')]
  public function repairIcons(array $options = ['dry-run' => FALSE]): int {
    $dryRun = (bool) $options['dry-run'];
    $result = $this->paragraphsIconRepair->repair($dryRun);

    foreach ($result['repaired'] as $id) {
      $this->logger()->success(dt('@verb icon for @type.', [
        '@verb' => $dryRun ? 'Would regenerate' : 'Regenerated',
        '@type' => $id,
      ]));
    }

    foreach ($result['failed'] as $id) {
      $this->logger()->warning(dt('No usable icon_default for @type.', ['@type' => $id]));
    }

    $this->logger()->notice(dt('@repaired repaired, @intact intact, @skipped without an icon, @failed failed.', [
      '@repaired' => count($result['repaired']),
      '@intact' => count($result['intact']),
      '@skipped' => count($result['skipped']),
      '@failed' => count($result['failed']),
    ]));

    return $result['failed'] === [] ? self::EXIT_SUCCESS : self::EXIT_FAILURE;
  }

}
