<?php

declare(strict_types=1);

namespace Drupal\s360_placeable_node_field\Hook;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\s360_placeable_node_field\S360PlaceableNodeFieldHelper;

/**
 * Hook implementations for s360_placeable_node_field module.
 */
final class S360PlaceableNodeFieldHooks {

  use StringTranslationTrait;

  /**
   * Hook implementations for s360_placeable_node_field.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list service.
   * @param \Drupal\s360_placeable_node_field\S360PlaceableNodeFieldHelper $s360PlaceableNodeFieldHelper
   *   The S360 Placeable Node Field Helper service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user service.
   */
  public function __construct(
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly S360PlaceableNodeFieldHelper $s360PlaceableNodeFieldHelper,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.s360_placeable_node_field':
        return '';
    }

    return NULL;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraph__placeable_node_field' => [
        'base hook' => 'paragraph',
        'template' => 'paragraph--placeable-node-field',
      ],
      'paragraph__placeable_node_field__preview' => [
        'base hook' => 'paragraph',
        'template' => 'paragraph',
        'path' => $this->moduleExtensionList->getPath('paragraphs') . '/templates',
      ],
    ];
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for 'field_config_edit_form'.
   */
  #[Hook('form_field_config_edit_form_alter')]
  public function formFieldConfigEditFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $form_object = $form_state->getFormObject();

    // Validate form object type.
    if (!$form_object instanceof EntityFormInterface) {
      return;
    }

    /** @var \Drupal\field\Entity\FieldConfig $field_config */
    $field_config = $form_object->getEntity();

    // Only apply to node fields.
    if (!$field_config || $field_config->getTargetEntityTypeId() !== 'node') {
      return;
    }

    // Check user permissions.
    if (!$this->currentUser->hasPermission('administer node fields')) {
      return;
    }

    $settings = $field_config->getThirdPartySettings('s360_placeable_node_field');

    $form['is_placeable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Make this field placeable'),
      '#default_value' => $settings['is_placeable'] ?? FALSE,
      '#description' => $this->t('When checked, this field will be placeable using the "Placeable Node Field" paragraph.'),
    ];

    $form['#entity_builders'][] = [$this->s360PlaceableNodeFieldHelper, 'fieldConfigEditFormBuilder'];
  }

}
