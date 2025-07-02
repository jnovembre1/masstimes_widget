<?php

namespace Drupal\masstimes_widget\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\masstimes_widget\massTimesService;

/**
 * Class massTimesController.
 */
class massTimesController extends ControllerBase {
    
    /**
     * @var massTimesService $service
     * @var PrivateTempStoreFactory $tempStore
     * @var ClientInterface $httpClient
     */
    protected $service;
    protected $tempStore;
    protected $httpClient;

    /**
     * class Constructor.
     */
    public function __construct(massTimesService $service, PrivateTempStoreFactory $tempStore,
    ClientInterface $httpClient) {
        $this->service = $service;
        $this->tempStore = $tempStore;
        $this->httpClient = $httpClient;
    }
   
    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
        $container->get('masstimes_widget.service'),
        $container->get('tempstore.private'),
        $container->get('http_client')
        );
    }

    /**
     * processes the location data
     * @param  Symfony\Component\HttpFoundation\Request $locationData
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function processing(Request $request) {
        $locationData = json_decode($request->getContent());
        
        $this->tempStore->get('masstimes/user_location')->set('location_data', $locationData);
        
        return new JsonResponse($locationData);
      }

    
      /**
       * fetches location data. calls our service and passes the relevant coords to it
       * @param  Symfony\Component\HttpFoundation\Request $locationData
       * @return array
       */
    public function output(Request $request) {
        $locationData = $this->tempStore->get('masstimes/user_location')->get('location_data');
        $latitude = $locationData->lat;
        $longitude = $locationData->long;
        
        return [
            $this->service->fetchData($latitude, $longitude),
        ];
    }
}