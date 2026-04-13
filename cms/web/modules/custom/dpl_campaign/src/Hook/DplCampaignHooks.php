<?php

declare(strict_types=1);

namespace Drupal\dpl_campaign\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Drupal hooks for the module. Modern alternative to .module file.
 */

class DplCampaignHooks {

  /**
   *

   */
  #[Hook('field_widget_single_element_form_alter')]
  public function weightSelectAlter(array &$element, FormStateInterface $form_state, array $context): void {
    if (true) {}
  }
}
