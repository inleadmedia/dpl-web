<?php

declare(strict_types=1);

namespace Drupal\eonext_event_status\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Lets site builders install ribbon fields without Drush.
 */
class EventStatusAdminForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'eonext_event_status_admin';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    \Drupal::moduleHandler()->loadInclude('eonext_event_status', 'install');

    $missing = _eonext_event_status_missing_owned_config();
    $installed = count(EONEXT_EVENT_STATUS_OWNED_CONFIG) - count($missing);

    if ($missing === []) {
      $form['status'] = [
        '#markup' => '<p>' . $this->t('All @count ribbon field configuration objects are installed.', [
          '@count' => count(EONEXT_EVENT_STATUS_OWNED_CONFIG),
        ]) . '</p>',
      ];
    }
    else {
      $form['status'] = [
        '#markup' => '<p>' . $this->t('@installed of @total ribbon field configuration objects are installed. The event edit form will not show ribbon widgets until all are present.', [
          '@installed' => $installed,
          '@total' => count(EONEXT_EVENT_STATUS_OWNED_CONFIG),
        ]) . '</p>',
      ];

      $items = [];
      foreach ($missing as $name) {
        $items[] = $name;
      }
      $form['missing'] = [
        '#theme' => 'item_list',
        '#title' => $this->t('Missing configuration'),
        '#items' => $items,
      ];
    }

    $form['help'] = [
      '#markup' => '<p>' . $this->t('After installing, clear caches and edit an event series. Ribbon fields appear in a <em>Ribbon</em> section in the right sidebar.') . '</p>',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['install'] = [
      '#type' => 'submit',
      '#value' => $this->t('Install ribbon fields'),
      '#button_type' => 'primary',
    ];

    if ($missing === []) {
      $form['actions']['install']['#value'] = $this->t('Re-run field install');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    \Drupal::moduleHandler()->loadInclude('eonext_event_status', 'install');
    _eonext_event_status_install_owned_config();

    $missing = _eonext_event_status_missing_owned_config();
    if ($missing === []) {
      drupal_flush_all_caches();
      $this->messenger()->addStatus($this->t('Ribbon fields are installed. Edit an event series and open the Ribbon section in the sidebar.'));
    }
    else {
      $this->messenger()->addError($this->t('Some ribbon field configuration could not be installed. Check Reports → Recent log messages for eonext_event_status errors.'));
    }

    $form_state->setRedirectUrl(Url::fromRoute('eonext_event_status.admin'));
  }

}
