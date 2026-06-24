<?php

namespace Drupal\eonext_easysearch\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves module documentation for the help page.
 */
class EasysearchHelpController extends ControllerBase {

  /**
   * Returns the interactive architecture documentation HTML.
   */
  public function documentation(): Response {
    $path = $this->moduleHandler()->getModule('eonext_easysearch')->getPath()
      . '/docs/semantic-search-flow.html';

    if (!is_readable($path)) {
      throw new NotFoundHttpException();
    }

    $content = file_get_contents($path);
    if ($content === FALSE) {
      throw new NotFoundHttpException();
    }

    return new Response($content, 200, [
      'Content-Type' => 'text/html; charset=UTF-8',
    ]);
  }

}
