<?php

declare(strict_types=1);

namespace Drupal\eonext_event_status\Model;

use DanskernesDigitaleBibliotek\CMS\Api\Model\EventsGET200ResponseInner as EventsGET200ResponseInnerBase;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;

/**
 * Extends the DPL events REST model with optional ribbon data.
 */
class EventsGET200ResponseInner extends EventsGET200ResponseInnerBase {

  /**
   * Custom ribbon label and color for the event.
   *
   * @var array{text: string, color: string}|null
   *
   * @SerializedName("ribbon")
   * @Type("array")
   */
  protected ?array $ribbon = NULL;

  /**
   * Returns the ribbon payload.
   */
  public function getRibbon(): ?array {
    return $this->ribbon;
  }

  /**
   * Sets the ribbon payload.
   */
  public function setRibbon(?array $ribbon): self {
    $this->ribbon = $ribbon;
    return $this;
  }

  /**
   * Clones a base REST response and attaches ribbon data.
   */
  public static function fromResponse(EventsGET200ResponseInnerBase $response, array $ribbon): self {
    $extended = new self();
    $source = new \ReflectionObject($response);
    $target = new \ReflectionObject($extended);

    foreach ($source->getProperties() as $property) {
      $property->setAccessible(TRUE);
      $name = $property->getName();

      if (!$target->hasProperty($name)) {
        continue;
      }

      $target_property = $target->getProperty($name);
      $target_property->setAccessible(TRUE);
      $target_property->setValue($extended, $property->getValue($response));
    }

    return $extended->setRibbon($ribbon);
  }

}
