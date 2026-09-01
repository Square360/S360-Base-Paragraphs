<?php

declare(strict_types=1);

namespace Drupal\s360_base_paragraphs\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\s360_base_paragraphs\ParagraphsIconRepair;
use Drupal\s360_base_paragraphs\S360BaseParagraphsHelper;
use Drupal\views\Views;
use Drupal\webform\Entity\Webform;

/**
 * Hook implementations for the s360_base_paragraphs module.
 */
final class S360BaseParagraphsHooks {

  use StringTranslationTrait;

  /**
   * State key holding the last paragraph icon repair check timestamp.
   */
  private const ICON_REPAIR_STATE_KEY = 's360_base_paragraphs.icon_repair_last_run';

  /**
   * Minimum seconds between paragraph icon repair checks.
   */
  private const ICON_REPAIR_INTERVAL = 86400;

  /**
   * Hook implementations for s360_base_paragraphs.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\s360_base_paragraphs\S360BaseParagraphsHelper $s360BaseParagraphsHelper
   *   The S360 Base Paragraph Helper service.
   * @param \Drupal\s360_base_paragraphs\ParagraphsIconRepair $paragraphsIconRepair
   *   The paragraph icon repair service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly S360BaseParagraphsHelper $s360BaseParagraphsHelper,
    private readonly ParagraphsIconRepair $paragraphsIconRepair,
    private readonly StateInterface $state,
    private readonly TimeInterface $time,
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

  /**
   * Implements hook_cron().
   *
   * Paragraph type icon files live in the public files directory, which is not
   * in version control and does not travel with a code deploy. A database
   * clone therefore lands the icon file *entity* on an environment where the
   * file's *bytes* do not exist, and every icon render 404s.
   *
   * An update hook cannot cover this: its schema version rides along in the
   * cloned database, so it is recorded as already run. Cron is checked here
   * instead, throttled to once a day, so any environment self-heals however
   * its database arrived.
   */
  #[Hook('cron')]
  public function cron(): void {
    $last = (int) $this->state->get(self::ICON_REPAIR_STATE_KEY, 0);

    if (($last + self::ICON_REPAIR_INTERVAL) > $this->time->getRequestTime()) {
      return;
    }

    $this->state->set(self::ICON_REPAIR_STATE_KEY, $this->time->getRequestTime());
    $this->paragraphsIconRepair->repair();
  }

}
