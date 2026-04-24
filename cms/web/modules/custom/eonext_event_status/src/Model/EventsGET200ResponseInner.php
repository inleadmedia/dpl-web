<?php

namespace Drupal\eonext_event_status\Model;

use DanskernesDigitaleBibliotek\CMS\Api\Model\EventsGET200ResponseInner as EventsGET200ResponseInnerDefault;

/**
 * Class representing the EventsGET200ResponseInner model.
 */
class EventsGET200ResponseInner extends EventsGET200ResponseInnerDefault {

  /**
   * The event ribbon information.
   */
  protected ?array $ribbon = NULL;

  /**
   * Constructor.
   *
   * @param array|null $data
   *   Associated array of property values initializing the model.
   */
  public function __construct(array $data = NULL) {
    parent::__construct($data);
    if (is_array($data)) {
      $this->ribbon = array_key_exists('ribbon', $data) ? $data['ribbon'] : $this->ribbon;
    }
  }

}
