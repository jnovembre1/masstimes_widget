<?php

namespace Drupal\masstimes_widget;

use GuzzleHttp\ClientInterface;
use Drupal\Component\Serialization\Json;

class MassTimesService {
  protected ClientInterface $http;

  public function __construct(ClientInterface $http) {
    $this->http = $http;
  }

  /**
   * fetch nearest parishes from the API.
   */
  public function fetchParishes(float $lat, float $lon): array {
    $url = "https://apiv4.updateparishdata.org/Churchs/?lat={$lat}&long={$lon}&pg=1";
    $resp = $this->http->request('GET', $url);
    return Json::decode($resp->getBody()->getContents());
  }
}
