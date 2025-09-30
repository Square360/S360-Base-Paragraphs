<?php

namespace Drupal\s360_base_paragraphs;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class ConfigFormAlter.
 */
class ConfigFormBuilder {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * ConfigFormAlter constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * Add the "Is Placeable" field config form to the given form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $form_id
   *   The form id.
   */
  public function addIsPlaceableFieldToEntityForm(array &$form, FormStateInterface $form_state, string $form_id): void {
    $form_object = $form_state->getFormObject();
    assert($form_object instanceof EntityFormInterface);

    /** @var \Drupal\field\Entity\FieldConfig $field_config */
    $field_config = $form_object->getEntity();
    $settings = $field_config->getThirdPartySettings('s360_base_paragraphs');

    $form['is_placeable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Make this field placeable'),
      '#default_value' => $settings['is_placeable'] ?? FALSE,
      '#description' => $this->t('When checked, this field will be placeable using the "Placeable Node Field" paragraph.'),
    ];

    $form['#entity_builders'][] = [
      $this,
      'assignIsPlaceableFieldThirdPartySettingsToEntity',
    ];
  }

  /**
   * Assign the "Is Placeable" field third party settings to the given entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param \Drupal\Core\Field\FieldConfigInterface $field_config
   *   The field config entity.
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function assignIsPlaceableFieldThirdPartySettingsToEntity(string $entity_type, FieldConfigInterface $field_config, array &$form, FormStateInterface $form_state): void {
    $field_config->setThirdPartySetting(
      's360_base_paragraphs',
      'is_placeable',
      $form_state->getValue('is_placeable')
    );
  }

}
