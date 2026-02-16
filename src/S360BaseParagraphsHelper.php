<?php

declare(strict_types=1);

namespace Drupal\s360_base_paragraphs;

use Drupal\Core\Routing\RouteMatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Helper class for s360 base paragraphs operations.
 */
final class S360BaseParagraphsHelper {

  /**
   * Construct an S360BaseParagraphsHelper service.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  private function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private LoggerInterface $logger,
  ) {}

  /**
   * Checks if the current route is an edit context.
   *
   * This includes any entity edit form or layout_paragraphs route where
   * layout paragraph content may be edited.
   *
   * @return bool
   *   TRUE if on an entity edit form or layout_paragraphs route.
   */
  public function isEditContext(): bool {
    $route_name = $this->routeMatch->getRouteName();

    if (empty($route_name)) {
      return FALSE;
    }

    return (
      str_contains($route_name, 'edit_form') ||
      str_contains($route_name, 'layout_paragraphs')
    );
  }

  /**
   * Gets the logger service.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger service.
   */
  public function getLogger(): LoggerInterface {
    return $this->logger;
  }

}
