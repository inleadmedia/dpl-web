<?php

declare(strict_types=1);

namespace Drupal\eonext_event_status\Services;

use DanskernesDigitaleBibliotek\CMS\Api\Model\EventsGET200ResponseInner;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\dpl_event\Entity\EventInstance;
use Drupal\dpl_event\Services\EventRestMapper as DplEventRestMapper;
use Drupal\eonext_event_status\Model\EventsGET200ResponseInner as EventsGET200ResponseInnerExtended;
use Drupal\recurring_events\Entity\EventSeries;

/**
 * Extends the DPL event REST mapper to include ribbon data.
 */
final class EventRestMapper extends DplEventRestMapper {

  /**
   * Constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    FileUrlGeneratorInterface $fileUrlGenerator,
    ConfigFactoryInterface $configFactory,
    protected RibbonService $ribbonService,
  ) {
    parent::__construct($entityTypeManager, $fileUrlGenerator, $configFactory);
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(EventInstance $event_instance): EventsGET200ResponseInner {
    $response = parent::getResponse($event_instance);

    $event_series = $event_instance->getEventSeries();
    if (!($event_series instanceof EventSeries)) {
      return $response;
    }

    $ribbon = $this->ribbonService->getRibbon($event_series, $event_instance);
    if (empty($ribbon)) {
      return $response;
    }

    return EventsGET200ResponseInnerExtended::fromResponse($response, $ribbon);
  }

}
