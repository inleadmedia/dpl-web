<?php

declare(strict_types=1);

namespace Drupal\eonext_translation;

use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Skips GO redirect subscriber when dpl_go is not installed (e.g. Korsør kultur).
 */
final class EonextTranslationServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if (!$container->hasDefinition('dpl_go.go_site')) {
      $container->removeDefinition('eonext_translation.go_redirect_cleaner_subscriber');
    }
  }

}
