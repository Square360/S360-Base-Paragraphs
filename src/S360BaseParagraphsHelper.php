<?php

declare(strict_types=1);

namespace Drupal\s360_base_paragraphs;

use Psr\Log\LoggerInterface;

class S360BaseParagraphsHelper {

  /**
   * The logger instance.
   *
   * @var \Psr\Log\LoggerInterface|null
   */
  private static ?LoggerInterface $logger = NULL;

  /**
   * Gets the logger instance for the s360_base_theme theme.
   *
   * Lazy-loads and returns a logger instance for this themes channel.
   * Uses a static property to ensure only one logger instance is created.
   */
  public static function logger(): LoggerInterface {
    if (self::$logger === NULL) {
      self::$logger = \Drupal::logger('s360_base_paragraphs');
    }

    return self::$logger;
  }

  /**
   * Checks the route name to see if it's an "admin route".
   *
   * @return bool
   *   Returns true if the current route is found in the array, false otherwise.
   */
  public static function isAdminRoute() {
    $admin_paths = [
      'node.add',
      'entity.node.edit_form',
      'entity.group.edit_form',
      'layout_paragraphs',
    ];

    $route_name = \Drupal::routeMatch()->getRouteName();

    if (empty($route_name)) {
      return FALSE;
    }

    // Check each pattern and return immediately on first match.
    foreach ($admin_paths as $pattern) {
      if (str_starts_with($route_name, $pattern)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
