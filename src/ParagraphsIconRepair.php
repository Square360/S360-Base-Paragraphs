<?php

declare(strict_types=1);

namespace Drupal\s360_base_paragraphs;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Psr\Log\LoggerInterface;

/**
 * Repairs paragraph type icons whose files are missing from the filesystem.
 *
 * Paragraph types ship their icon artwork as a base64 data URI in the
 * icon_default config key, alongside an icon_uuid referencing a managed file.
 * On first use Paragraphs decodes icon_default into a real file under
 * public://paragraphs_type_icon/ and creates the file entity.
 *
 * That file lives in the public files directory, which is not in version
 * control and does not travel with a code deploy. A database clone therefore
 * carries the file *entity* to an environment where the file's *bytes* do not
 * exist. Paragraphs only falls back to icon_default when the entity is
 * missing (see ParagraphsType::getIconFile()), not when the bytes are, so the
 * icon resolves to a URL that 404s on every render.
 *
 * This service detects that state and repairs it by removing the stale entity
 * so Paragraphs regenerates the file from icon_default.
 */
final class ParagraphsIconRepair {

  /**
   * Constructs a ParagraphsIconRepair service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Repairs every paragraph type whose icon file is missing.
   *
   * @param bool $dryRun
   *   When TRUE, report what would be repaired without changing anything.
   *
   * @return array
   *   An associative array with keys 'repaired', 'intact', 'skipped' and
   *   'failed', each holding a list of paragraph type IDs.
   */
  public function repair(bool $dryRun = FALSE): array {
    $result = [
      'repaired' => [],
      'intact' => [],
      'skipped' => [],
      'failed' => [],
    ];

    $types = $this->entityTypeManager
      ->getStorage('paragraphs_type')
      ->loadMultiple();

    foreach ($types as $type) {
      assert($type instanceof ParagraphsTypeInterface);
      $uuid = $type->get('icon_uuid');

      // No icon configured: nothing to repair.
      if (empty($uuid)) {
        $result['skipped'][] = $type->id();
        continue;
      }

      $file = $this->loadFileByUuid($uuid);
      if ($file !== NULL && $this->fileExists($file)) {
        $result['intact'][] = $type->id();
        continue;
      }

      if ($dryRun) {
        $result['repaired'][] = $type->id();
        continue;
      }

      // Remove the stale entity so getIconFile() falls through to
      // restoreDefaultIcon(), which rewrites the file from icon_default.
      $file?->delete();

      if ($type->getIconFile()) {
        $result['repaired'][] = $type->id();
        $this->logger->info('Regenerated missing icon for paragraph type %type.', [
          '%type' => $type->id(),
        ]);
      }
      else {
        $result['failed'][] = $type->id();
        $this->logger->warning('Could not regenerate the icon for paragraph type %type; no usable icon_default.', [
          '%type' => $type->id(),
        ]);
      }
    }

    return $result;
  }

  /**
   * Loads a managed file by UUID.
   *
   * @param string $uuid
   *   The file UUID.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity, or NULL when no file carries that UUID.
   */
  private function loadFileByUuid(string $uuid): ?FileInterface {
    $files = $this->entityTypeManager
      ->getStorage('file')
      ->loadByProperties(['uuid' => $uuid]);

    $file = $files ? reset($files) : NULL;

    return $file instanceof FileInterface ? $file : NULL;
  }

  /**
   * Checks whether a managed file's bytes are present on disk.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   *
   * @return bool
   *   TRUE when the file exists on the filesystem.
   */
  private function fileExists(FileInterface $file): bool {
    $path = $this->fileSystem->realpath($file->getFileUri());

    return $path !== FALSE && file_exists($path);
  }

}
