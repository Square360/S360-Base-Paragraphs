<?php

declare(strict_types=1);

namespace Drupal\s360_placeable_node_field;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Psr\Log\LoggerInterface;

/**
 * Helper class for s360_placeable_node_field operations.
 */
final class S360PlaceableNodeFieldHelper {

  /**
   * Construct an S360PlaceableNodeFieldHelper service.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   The cache tags invalidator service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  private function __construct(
    private readonly CacheBackendInterface $cache,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private LoggerInterface $logger,
  ) {}

  /**
   * Allowed values callback for field_node_field.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $definition
   *   The field storage definition.
   * @param \Drupal\\Entity\ParagraphInterface|null $entity
   *   The entity being created if applicable.
   * @param bool $cacheable
   *   Boolean indicating if the results are cacheable.
   *
   * @return array
   *   An associative array of field names and labels.
   *
   * @uses s360_placeable_node_field_get_placeable_node_fields()
   */
  public static function nodeFieldAllowedValuesFunction(FieldStorageDefinitionInterface $definition, ?ParagraphInterface $entity = NULL, &$cacheable = TRUE) {
    /** @var Drupal\layout_paragraphs\LayoutParagraphsLayout $paragraphs_layout */
    $paragraphs_layout = $entity?->_layoutParagraphsLayout;

    /** @var Drupal\node\NodeInterface $node */
    $node = $paragraphs_layout?->getEntity();

    // Validate that we have a proper node entity.
    if (!$node instanceof NodeInterface) {
      return [];
    }

    $node_bundle = $node->bundle();
    if (!$node_bundle) {
      return [];
    }

    return self::getPlaceableNodeFields($node_bundle);
  }

  /**
   * Form builder for the field config edit form.
   */
  public function fieldConfigEditFormBuilder(string $entity_type, FieldConfigInterface $field_config, array &$form, FormStateInterface $form_state) {
    try {
      $field_config->setThirdPartySetting(
        's360_placeable_node_field',
        'is_placeable',
        $form_state->getValue('is_placeable')
      );

      // Make sure the cache for the node bundle is invalidated so the
      // allowed_values function caching is reset.
      $this->cacheTagsInvalidator->invalidateTags(['node_type:' . $field_config->get('bundle')]);
    }
    catch (\Exception $e) {
      $this->logger->error(
        'Failed to update field placeable setting: @message',
        ['@message' => $e->getMessage()]
      );
    }
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

  /**
   * Get all placeable field by $node_bundle.
   *
   * @param string $node_bundle
   *   The target bundle to get the placeable fields from.
   *
   * @return array
   *   An array of valid values in the form:
   *   - field_name => "Field Label"
   */
  private static function getPlaceableNodeFields(string $node_bundle) {
    // Create a cache ID based on the node bundle.
    $cache_key = "s360_placeable_node_field:{$node_bundle}";

    // Get the cache service.
    $cache = \Drupal::cache();

    if ($cached = $cache->get($cache_key)) {
      return $cached->data;
    }

    $fields = [];

    // Get the entity field manager service.
    $entity_field_manager = \Drupal::service('entity_field.manager');

    // Get all field definitions for the node entity type and bundle.
    $field_definitions = $entity_field_manager->getFieldDefinitions('node', $node_bundle);

    // Collect all field config IDs first to batch load them.
    $field_config_ids = [];
    foreach ($field_definitions as $field_name => $field_definition) {
      // Skip base fields - we only want configurable fields (user-added).
      if ($field_definition instanceof FieldConfig) {
        $field_config_ids[] = "node.{$node_bundle}.{$field_name}";
      }
    }

    // Batch load all field configs to avoid N+1 queries.
    if (!empty($field_config_ids)) {
      $field_configs = FieldConfig::loadMultiple($field_config_ids);

      foreach ($field_configs as $field_config) {
        if ($field_config->getThirdPartySetting('s360_placeable_node_field', 'is_placeable')) {
          $field_name = $field_config->getName();
          $fields[$field_name] = $field_config->label();
        }
      }
    }

    // Cache the results with appropriate cache tags.
    $cache_tags = [
      "node_type:$node_bundle",
    ];

    // Cache for 1 hour (3600 seconds) or until cache tags are invalidated.
    $cache->set($cache_key, $fields, time() + 3600, $cache_tags);

    return $fields;
  }

}
