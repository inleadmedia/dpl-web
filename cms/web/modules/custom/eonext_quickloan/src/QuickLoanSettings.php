<?php

namespace Drupal\eonext_quickloan;

/**
 * Shared constants for the quick loan module.
 */
class QuickLoanSettings {

  public const CONFIG_ID = 'eonext_quickloan.settings';

  /**
   * Data attribute toggling quick loan on the material React app.
   */
  public const MATERIAL_DATA_ATTRIBUTE = 'data-material-quick-loan-info';

  /**
   * Standalone bundle rendering the quick loan text on the material page.
   */
  public const LIBRARY = 'eonext_quickloan/material_page_quick_loan';

}
