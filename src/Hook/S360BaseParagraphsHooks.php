<?php

declare(strict_types=1);

namespace Drupal\s360_base_paragraphs\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs\Entity\ParagraphInterface;
use Drupal\s360_base_paragraphs\S360BaseParagraphsHelper;
use Drupal\views\Views;
use Drupal\webform\Entity\Webform;

/**
 * Hook implementations for the s360_base_paragraphs module.
 */
final class S360BaseParagraphsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match) {
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
          $paragraph_config = \Drupal::config("paragraphs.paragraphs_type.$paragraph_key");

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
  }

  /**
   * Implements hook_preprocess_paragraph().
   */
  #[Hook('preprocess_paragraph')]
  public function preprocessParagraph(array &$variables): void {
    /** @var \Drupal\paragraphs\Entity\ParagraphInterface $paragraph */
    $paragraph = $variables['paragraph'];
    $paragraph_bundle = $paragraph->bundle();

    switch ($paragraph_bundle) {
      case 'view_block':
        self::viewBlock($variables, $paragraph);
        break;

      case 'webform':
        self::webform($variables, $paragraph);
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
  private static function viewBlock(array &$variables, ParagraphInterface $paragraph): void {
    // Not an Admin route.
    if (!S360BaseParagraphsHelper::isAdminRoute()) {
      return;
    }

    // Paragraph doesn't have view field.
    if (!$paragraph->hasField('field_view')) {
      return;
    }

    $field_view = $paragraph->get('field_view')->getValue();

    // No view field value.
    if (!$field_view) {
      return;
    }

    $field_view = reset($field_view);

    // No view target_id or display_id.
    if (empty($field_view['target_id']) || empty($field_view['display_id'])) {
      return;
    }

    /** @var \Drupal\views\ViewExecutable|null $view */
    $view = Views::getView($field_view['target_id']);

    // Default message is something goes wrong loading the view/display.
    $field_item_text = 'Problem loading the view.';

    // View doesn't exist or can't be loaded.
    if (!$view) {
      return;
    }

    $view->setDisplay($field_view['display_id']);
    $view_display = $view->getDisplay();
    $view_display_title = $view_display->display['display_title'] ?? $field_view['display_id'];
    $field_item_text = $view->storage->label() . " ($view_display_title)";

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
  private static function webform(array &$variables, ParagraphInterface $paragraph): void {
    // Not an Admin route.
    if (!S360BaseParagraphsHelper::isAdminRoute()) {
      return;
    }

    // Paragraph doesn't have webform field.
    if (!$paragraph->hasField('field_webform')) {
      return;
    }

    $field_webform = $paragraph?->get('field_webform')->getValue();

    // No webform field value.
    if (!$field_webform) {
      return;
    }

    $field_webform = reset($field_webform);

    // No webform target_id.
    if (empty($field_webform['target_id'])) {
      return;
    }

    /** @var \Drupal\webform\WebformInterface|null $webform */
    $webform = Webform::load($field_webform['target_id']);

    // No access to webform.
    if ($webform && !$webform->access('view')) {
      return;
    }

    // Default message if something goes wrong loading the webform.
    $field_item_text = 'Problem loading the webform.';

    if ($webform) {
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
