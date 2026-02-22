<?php

declare(strict_types=1);

namespace Drupal\s360_base_paragraphs\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\s360_base_paragraphs\S360BaseParagraphsHelper;
use Drupal\views\Views;
use Drupal\webform\Entity\Webform;

/**
 * Hook implementations for the s360_base_paragraphs module.
 */
final class S360BaseParagraphsHooks {

  use StringTranslationTrait;

  /**
   * Hook implementations for s360_base_paragraphs.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\s360_base_paragraphs\S360BaseParagraphsHelper $s360BaseParagraphsHelper
   *   The S360 Base Paragraph Helper service.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly S360BaseParagraphsHelper $s360BaseParagraphsHelper,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): string|null {
    switch ($route_name) {
      case 'help.page.s360_base_paragraphs':
        $paragraphs = [
          'cta_link' => $this->t('CTA Link'),
          'curated_content' => $this->t('Curated Content'),
          'document_list' => $this->t('Document List'),
          'embed_code' => $this->t('Embed Code'),
          'faq' => $this->t('FAQ'),
          'html_content' => $this->t('HTML Content'),
          'image' => $this->t('Image'),
          'in_this_section' => $this->t('In this Section'),
          'link_list' => $this->t('Link List'),
          'placeholder' => $this->t('Placeholder'),
          'video' => $this->t('Video'),
          'view_block' => $this->t('View Block'),
          'webform' => $this->t('Webform'),
        ];

        $output = '';

        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('This module adds commonly used paragraphs types.') . '</p>';

        $output .= '<h3>' . $this->t('Paragraph types') . '</h3>';
        $output .= '<dl>';

        foreach ($paragraphs as $paragraph_key => $paragraph_label) {
          $paragraph_config = $this->configFactory->get("paragraphs.paragraphs_type.$paragraph_key");

          // The paragraph is still configured (not deleted).
          if (!empty($paragraph_config->getRawData())) {
            $output .= '<dt><strong>' . $paragraph_config->get('label') . '</strong></dt>';
            $output .= '<dd>' . $paragraph_config->get('description') . '</dd>';
          }
          // The paragraph was deleted.
          else {
            $output .= "<dt><strong>$paragraph_label</strong></dt>";
            $output .= "<dd>This paragraph was removed.</dd>";
          }
        }

        $output .= '</dl>';

        return $output;
    }

    return NULL;
  }

  /**
   * Implements hook_preprocess_paragraph().
   */
  #[Hook('preprocess_paragraph')]
  public function preprocessParagraph(array &$variables): void {
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
    $paragraph = $variables['paragraph'];
    $paragraph_bundle = $paragraph->bundle();

    switch ($paragraph_bundle) {
      case 'view_block':
        $this->viewBlock($variables, $paragraph);
        break;

      case 'webform':
        $this->webform($variables, $paragraph);
        break;
    }
  }

  /**
   * Preprocesses view_block paragraph variables.
   *
   * @param array $variables
   *   The paragraph variables array being preprocessed.
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The Views Reference paragraph entity.
   */
  private function viewBlock(array &$variables, ParagraphInterface $paragraph): void {
    // Not an Admin route.
    if (!$this->s360BaseParagraphsHelper->isEditContext()) {
      return;
    }

    // Paragraph doesn't have the view field.
    if (!$paragraph->hasField('field_view')) {
      return;
    }

    $field_view = $paragraph->get('field_view');

    // The view field has no value.
    if ($field_view->isEmpty()) {
      return;
    }

    $field_view_value = $field_view->first()?->getValue();

    // There is no target_id or display_id.
    if (empty($field_view_value['target_id']) || empty($field_view_value['display_id'])) {
      return;
    }

    /** @var \Drupal\views\ViewExecutable|null $view */
    $view = Views::getView($field_view_value['target_id']);

    // Determine the field item text based on view existence and access.
    if (!$view) {
      $field_item_text = 'View not found: ' . $field_view_value['target_id'];
    }
    elseif (!$view->access($field_view_value['display_id'])) {
      $field_item_text = 'Access denied to view: ' . $field_view_value['target_id'] . '(' . $field_view_value['display_id'] . ')';
    }
    else {
      $view->setDisplay($field_view_value['display_id']);
      $view_display = $view->getDisplay();
      $view_display_title = $view_display->display['display_title'] ?? $field_view_value['display_id'];
      $field_item_text = $view->storage->label() . " ($view_display_title)";
    }

    $variables['content']['field_view'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => [
          'field',
          'field--name-field-view',
          'field--type-string',
          'field--label-inline',
        ],
      ],
      'child' => [
        [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => 'View',
          '#attributes' => [
            'class' => 'field__label',
          ],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $field_item_text,
          '#attributes' => [
            'class' => 'field__item',
          ],
        ],
      ],
    ];
  }

  /**
   * Preprocesses webform paragraph variables.
   *
   * @param array $variables
   *   The paragraph variables array being preprocessed.
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The Webform paragraph entity.
   */
  private function webform(array &$variables, ParagraphInterface $paragraph): void {
    // Not an Admin route.
    if (!$this->s360BaseParagraphsHelper->isEditContext()) {
      return;
    }

    // Paragraph doesn't have webform field.
    if (!$paragraph->hasField('field_webform')) {
      return;
    }

    $field_webform = $paragraph?->get('field_webform');

    // No webform field value.
    if ($field_webform->isEmpty()) {
      return;
    }

    $field_webform_value = $field_webform->first()?->getValue();

    // No webform target_id.
    if (empty($field_webform_value['target_id'])) {
      return;
    }

    /** @var \Drupal\webform\WebformInterface|null $webform */
    $webform = Webform::load($field_webform_value['target_id']);

    // Determine the field item text based on webform existence and access.
    if (!$webform) {
      $field_item_text = 'Webform not found: ' . $field_webform_value['target_id'];
    }
    elseif (!$webform->access('view')) {
      $field_item_text = 'Access denied to webform: ' . $webform->label();
    }
    else {
      $field_item_text = $webform->label();
    }

    $variables['content']['field_webform'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => [
          'field',
          'field--name-field-webform',
          'field--type-string',
          'field--label-inline',
        ],
      ],
      'child' => [
        [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => 'Webform',
          '#attributes' => [
            'class' => 'field__label',
          ],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $field_item_text,
          '#attributes' => [
            'class' => 'field__item',
          ],
        ],
      ],
    ];
  }

}
