<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_events\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\eonext_kultur_locale\LokaleConstants;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings for the kultur events listing page (/arrangementer).
 */
final class EventsListingSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
  ) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'eonext_kultur_events_listing_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['eonext_kultur_events.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('eonext_kultur_events.settings');

    $form['help'] = [
      '#type' => 'item',
      '#markup' => $this->t(
        'Configure the hero shown on the <a href=":url">events overview</a> page.',
        [':url' => Url::fromUserInput('/arrangementer')->toString()],
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

    if (isset($hero_form['field_locale_hero_image'])) {
      $form['field_locale_hero_image'] = $hero_form['field_locale_hero_image'];
    }
    if (isset($hero_form['field_locale_heading'])) {
      $form['field_locale_heading'] = $hero_form['field_locale_heading'];
    }

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

    $this->config('eonext_kultur_events.settings')
      ->set('hero_heading', $heading)
      ->set('hero_media', $media_id)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
