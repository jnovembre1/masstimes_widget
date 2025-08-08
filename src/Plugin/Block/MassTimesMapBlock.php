<?php

namespace Drupal\masstimes_widget\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\masstimes_widget\MassTimesService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Provides a 'MassTimes Map Fullscreen' block.
 *
 * @Block(
 *   id = "masstimes_map_block",
 *   admin_label = @Translation("MassTimes Map Fullscreen Block"),
 *   category = @Translation("MassTimes Widget")
 * )
 */
class MassTimesMapBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The MassTimes service.
   *
   * @var \Drupal\masstimes_widget\MassTimesService
   */
  protected MassTimesService $service;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MassTimesService $service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->service = $service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('masstimes_widget.service')
    );
  }

  /**
   * provide default values for our lat and long
   */
  public function defaultConfiguration() {
    return [
      'default_lat' => '',
      'default_lon' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * adds fields for default lat and long
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['default_lat'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default latitude'),
      '#default_value' => $config['default_lat'],
      '#description' => $this->t('If no URL query or geolocation is available, use this latitude.'),
    ];
    $form['default_lon'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default longitude'),
      '#default_value' => $config['default_lon'],
      '#description' => $this->t('If no URL query or geolocation is available, use this longitude.'),
    ];

    return $form;
  }

  /**
   * saving default lat/long to block
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $vals = $form_state->getValues();
    $this->configuration['default_lat'] = $vals['default_lat'];
    $this->configuration['default_lon'] = $vals['default_lon'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $request = \Drupal::request();
    $lat = $request->query->get('lat');
    $lon = $request->query->get('long');

    // if url doesn't have lat/long, we fall back to block defaults
    if ((string) $lat === '' || (string) $lon === '') {
      $lat = $this->configuration['default_lat'];
      $lon = $this->configuration['default_lon'];
    }

    $parishes = [];
    if (is_numeric($lat) && is_numeric($lon)) {
      try {
        $parishes = $this->service->fetchParishes((float) $lat, (float) $lon);
        usort($parishes, fn($a, $b) => ($a['distance'] ?? 0) <=> ($b['distance'] ?? 0));
      }
      catch (RequestException $e) {
        \Drupal::logger('masstimes_widget')->error($e->getMessage());
      }
    }

    // build GeoJSON features
    $features = [];
    foreach ($parishes as $i => $p) {
      if (empty($p['latitude']) || empty($p['longitude'])) {
        continue;
      }
      $features[] = [
        'type' => 'Feature',
        'geometry' => [
          'type' => 'Point',
          'coordinates' => [(float) $p['longitude'], (float) $p['latitude']],
        ],
        'properties' => [
          'index'   => $i,
          'name'    => $p['name'] ?? '',
          'address' => $p['church_address_street_address'] ?? '',
          'wptimes' => $p['church_worship_times'] ?? [],
          'distance' => round($p['distance'], 1),
        ],
      ];
    }

    // find our map center.
    if (!empty($features)) {
      [$lon0, $lat0] = $features[0]['geometry']['coordinates'];
      $center = [$lat0, $lon0];
    }
    elseif (is_numeric($lat) && is_numeric($lon)) {
      $center = [(float) $lat, (float) $lon];
    }
    else {
      $center = [0, 0];
    }

    // build the settings array we will pass into twig and the javascript.
    $settings = [
      'mapOptions'  => ['center' => $center, 'zoom' => 12],
      'geojson'     => ['type' => 'FeatureCollection', 'features' => $features],
      'parishes'    => $parishes,
      // default lat and long from block settings
      'defaultLat'  => is_numeric($this->configuration['default_lat']) ? (float)$this->configuration['default_lat'] : NULL,
      'defaultLon'  => is_numeric($this->configuration['default_lon']) ? (float)$this->configuration['default_lon'] : NULL,
    ];

    return [
      '#theme'    => 'masstimes_map',
      '#settings' => $settings,
      '#attached' => [
        'library'        => ['masstimes_widget/masstimes_map'],
        'drupalSettings' => ['masstimes_widget' => $settings],
      ],
      '#cache' => [
        'contexts' => ['url.query_args:lat', 'url.query_args:long'],
      ],
    ];
  }

}
