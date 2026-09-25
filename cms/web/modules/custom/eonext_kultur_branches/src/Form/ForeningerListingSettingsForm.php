<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_branches\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\eonext_kultur_locale\LokaleConstants;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings for the foreninger branches listing (/foreninger).
 */
final class ForeningerListingSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'eonext_kultur_branches_listing_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['eonext_kultur_branches.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('eonext_kultur_branches.settings');

    $form['help'] = [
      '#type' => 'item',
      '#markup' => $this->t(
        'Configure the hero and teaser metadata for the <a href=":url">Foreninger overview</a>. Choose which branches appear and their order on the <a href=":branches">Branches</a> tab.',
        [
          ':url' => Url::fromUserInput('/foreninger')->toString(),
          ':branches' => Url::fromRoute('eonext_kultur_branches.branch_listing')->toString(),
        ],
      ),
    ];

    $paragraph = $form_state->get('hero_paragraph');
    if (!$paragraph instanceof Paragraph) {
      $paragraph = Paragraph::create([
        'type' => LokaleConstants::PAGE_HERO_BUNDLE,
      ]);
      $paragraph->set('field_locale_heading', $config->get('hero_heading'));
      $media_id = $config->get('hero_media');
      if (is_numeric($media_id) && (int) $media_id > 0) {
        $paragraph->set('field_locale_hero_image', (int) $media_id);
      }
      $form_state->set('hero_paragraph', $paragraph);
    }

    $form_display = EntityFormDisplay::load('paragraph.' . LokaleConstants::PAGE_HERO_BUNDLE . '.default');
    if ($form_display === NULL) {
      $form['error'] = [
        '#markup' => $this->t('The page hero paragraph is not available.'),
      ];
      return parent::buildForm($form, $form_state);
    }

    $hero_form = [];
    $form_display->buildForm($paragraph, $hero_form, $form_state);

    $form['hero'] = [
      '#type' => 'details',
      '#title' => $this->t('Listing hero'),
      '#open' => TRUE,
    ];
    if (isset($hero_form['field_locale_hero_image'])) {
      $form['hero']['field_locale_hero_image'] = $hero_form['field_locale_hero_image'];
    }
    if (isset($hero_form['field_locale_heading'])) {
      $form['hero']['field_locale_heading'] = $hero_form['field_locale_heading'];
    }

    $form['teaser'] = [
      '#type' => 'details',
      '#title' => $this->t('Overview teaser (for links from other pages)'),
      '#open' => TRUE,
    ];
    $form['teaser']['teaser_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Teaser title'),
      '#default_value' => $config->get('teaser_title'),
    ];
    $form['teaser']['teaser_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Teaser text'),
      '#default_value' => $config->get('teaser_text'),
      '#rows' => 4,
    ];
    $form['teaser']['teaser_media'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Teaser image'),
      '#target_type' => 'media',
      '#default_value' => $this->loadMedia($config->get('teaser_media')),
      '#selection_settings' => [
        'target_bundles' => ['image'],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $paragraph = $form_state->get('hero_paragraph');
    $heading = '';
    $media_id = NULL;

    if ($paragraph instanceof Paragraph) {
      $form_display = EntityFormDisplay::load('paragraph.' . LokaleConstants::PAGE_HERO_BUNDLE . '.default');
      if ($form_display !== NULL) {
        $form_display->extractFormValues($paragraph, $form, $form_state);
      }

      $heading = trim((string) ($paragraph->get('field_locale_heading')->value ?? ''));
      if (!$paragraph->get('field_locale_hero_image')->isEmpty()) {
        $media_id = (int) $paragraph->get('field_locale_hero_image')->target_id;
      }
    }

    $teaser_media = $form_state->getValue('teaser_media');
    $teaser_media_id = is_numeric($teaser_media) ? (int) $teaser_media : NULL;

    $this->config('eonext_kultur_branches.settings')
      ->set('hero_heading', $heading)
      ->set('hero_media', $media_id)
      ->set('teaser_title', trim((string) $form_state->getValue('teaser_title')))
      ->set('teaser_text', trim((string) $form_state->getValue('teaser_text')))
      ->set('teaser_media', $teaser_media_id)
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Loads media for the autocomplete default value.
   */
  private function loadMedia(mixed $media_id): mixed {
    if (!is_numeric($media_id) || (int) $media_id <= 0) {
      return NULL;
    }

    return $this->entityTypeManager->getStorage('media')->load((int) $media_id);
  }

}
