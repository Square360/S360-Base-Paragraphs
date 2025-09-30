<?php

namespace Drupal\s360_base_paragraphs\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\node\Entity\Node;

/**
 * Plugin implementation of the 's360_base_paragraphs' placed field formatter.
 *
 * @FieldFormatter(
 *   id = "s360_base_paragraphs_rendered_node_field",
 *   label = @Translation("Default Rendered Node Field"),
 *   field_types = {
 *     "list_string"
 *   }
 * )
 */
class S360BaseParagraphsPlacedFieldFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getName() === 'field_node_field';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    if ($items->isEmpty()) {
      return [];
    }

    $elements = [];

    // Pre-load entities to avoid N+1 queries.
    $paragraph = $items->getEntity();
    $node = $paragraph instanceof Paragraph ? $paragraph->getParentEntity() : null;

    if (!$node instanceof Node) {
      return [];
    }

    // Cache field definitions for the node.
    $node_fields = $node->getFieldDefinitions();

    foreach ($items as $delta => $item) {
      $elements[$delta] = $this->viewElement($item, $node, $node_fields);
    }

    return $elements;
  }

  /**
   * Render a single field item.
   */
  protected function viewElement(FieldItemInterface $item, Node $node, array $node_fields): array {
    $field_machine_name = $item->getValue()['value'];

    // Check if field exists using cached definitions
    if (!isset($node_fields[$field_machine_name])) {
      return $this->buildErrorElement($field_machine_name);
    }

    $field = $node->get($field_machine_name);
    if ($field->isEmpty()) {
      return $this->buildErrorElement($field_machine_name);
    }

    return $field->view('default');
  }

  /**
   * Build error element for missing/empty fields.
   */
  private function buildErrorElement(string $field_machine_name): array {
    return [
      '#type' => 'inline_template',
      '#template' => '<strong>{{ field_machine_name }}</strong> does not exist or its value is empty.',
      '#context' => [
        'field_machine_name' => $field_machine_name,
      ],
    ];
  }

}
