<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Form;

use Apigee\Edge\Api\Management\Controller\StatsController;
use Apigee\Edge\Api\Management\Query\StatsQuery;
use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use League\Period\Period;
use Moment\MomentException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * App analytics form builder for developer- and team apps.
 */
abstract class AppAnalyticsFormBase extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  protected $connector;

  /**
   * The PrivateTempStore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $store;

  /**
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Constructs a new AppAnalyticsFormBase.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdk_connector
   *   The SDK connector service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempstore_private
   *   The private temp store factory.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The URL generator.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, SDKConnectorInterface $sdk_connector, PrivateTempStoreFactory $tempstore_private, UrlGeneratorInterface $url_generator) {
    $this->entityTypeManager = $entity_type_manager;
    $this->connector = $sdk_connector;
    $this->store = $tempstore_private->get('apigee_edge.analytics');
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('apigee_edge.sdk_connector'),
      $container->get('tempstore.private'),
      $container->get('url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?AppInterface $app = NULL) {
    // Little sanity check, child classes must set this parameter from route
    // before they call parent.
    if ($app === NULL) {
      $this->messenger()->addError($this->t('Something went wrong.'));
      $this->logger('apigee_edge')
        ->critical('App parameter was missing when the app analytics form got built.');
      return $form;
    }

    $config = $this->config('apigee_edge.common_app_settings');
    $analytics_environment = $config->get('analytics_environment');
    $analytics_available_environments = $config->get('analytics_available_environments');

    $form_state->disableRedirect();
    $form['#attached']['library'][] = 'apigee_edge/apigee_edge.analytics';
    $form['#attributes']['class'][] = 'apigee-edge-app-analytics';

    $form['controls'] = [
      '#type' => 'container',
    ];

    $form['controls']['label_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'controls-label',
        ],
      ],
    ];

    $form['controls']['label_container']['label'] = [
      '#markup' => $this->t('Filter:'),
    ];

    $form['controls']['environment'] = [
      '#type' => 'value',
      '#value' => $analytics_environment,
    ];

    if (count($analytics_available_environments) > 1) {
      $form['controls']['environment'] = [
        '#type' => 'select',
        '#required' => TRUE,
        '#title' => t('Environment'),
        '#title_display' => 'invisible',
        '#default_value' => $analytics_environment,
        '#options' => array_combine($analytics_available_environments, $analytics_available_environments),
      ];
    }

    $form['controls']['metrics'] = [
      '#type' => 'select',
      '#options' => [
        'avg(total_response_time)' => $this->t('Average response time'),
        'max(total_response_time)' => $this->t('Max response time'),
        'min(total_response_time)' => $this->t('Min response time'),
        'sum(message_count)' => $this->t('Message count'),
        'sum(is_error)' => $this->t('Error count'),
      ],
      '#default_value' => 'avg(total_response_time)',
      '#title' => t('Metrics'),
      '#title_display' => 'invisible'
    ];

    $form['controls']['since'] = [
      '#type' => 'datetime',
    ];

    $form['controls']['date_separator_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'date-separator',
        ],
      ],
    ];

    $form['controls']['date_separator_container']['date_separator'] = [
      '#markup' => '-',
    ];

    $form['controls']['until'] = [
      '#type' => 'datetime',
    ];

    $form['controls']['quick_date_picker'] = [
      '#type' => 'select',
      '#options' => [
        '1d' => $this->t('Last Day'),
        '1w' => $this->t('Last 7 Days'),
        '2w' => $this->t('Last 2 Weeks'),
        'custom' => $this->t('Custom range'),
      ],
      '#title' => t('Date range'),
      '#title_display' => 'invisible'
    ];

    $form['controls']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
    ];

    $offset = date('Z') / 3600;
    if ($offset > 0) {
      $offset = "+{$offset}";
    }
    elseif ($offset === 0) {
      $offset = "\u{00B1}{$offset}";
    }

    $form['timezone'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Your timezone: @timezone (UTC@offset)', [
        '@timezone' => date_default_timezone_get(),
        '@offset' => $offset,
      ]),
    ];

    $form['export_csv'] = [
      '#type' => 'link',
      '#title' => $this->t('Export CSV'),
      '#attributes' => [
        'role' => 'button',
      ],
    ];

    $form['chart'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'chart_container',
      ],
    ];

    $metric = $this->getRequest()->query->get('metric');
    $since = $this->getRequest()->query->get('since');
    $until = $this->getRequest()->query->get('until');
    $environment = $this->getRequest()->query->get('environment');

    if ($this->validateQueryString($form, $metric, $since, $until, $environment)) {
      $form['controls']['metrics']['#default_value'] = $metric;
      $since_datetime = DrupalDatetime::createFromTimestamp($since);
      $since_datetime->setTimezone(new \Datetimezone(date_default_timezone_get()));
      $until_datetime = DrupalDatetime::createFromTimestamp($until);
      $until_datetime->setTimezone(new \Datetimezone(date_default_timezone_get()));
      $form['controls']['since']['#default_value'] = $since_datetime;
      $form['controls']['until']['#default_value'] = $until_datetime;
      $form['controls']['quick_date_picker']['#default_value'] = 'custom';
      $form['controls']['environment']['#default_value'] = $environment;
    }
    else {
      $default_since_value = new DrupalDateTime();
      $default_since_value->sub(new \DateInterval('P1D'));
      $default_until_value = new DrupalDateTime();
      $form['controls']['since']['#default_value'] = $default_since_value;
      $form['controls']['until']['#default_value'] = $default_until_value;
      $metric = $form['controls']['metrics']['#default_value'];
      $since = $default_since_value->getTimestamp();
      $until = $default_until_value->getTimestamp();
      $environment = $analytics_environment;
    }

    if (empty($form_state->getUserInput())) {
      // The last parameter allows to expose and make analytics environment
      // configurable later on the form.
      $this->generateResponse($form, $app, $metric, $since, $until, $environment);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $since = $form_state->getValue('since');
    $until = $form_state->getValue('until');

    if ($since instanceof DrupalDateTime && $until instanceof DrupalDateTime) {
      if ($since->getTimestamp() !== $until->getTimestamp()) {
        if ($since->diff($until)->invert === 1) {
          $form_state->setError($form['controls']['until'], $this->t('The end date cannot be before the start date.'));
        }
      }
    }
  }

  /**
   * Validates the URL query string parameters.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param string $metric
   *   The filter parameter.
   * @param string $since
   *   The start date parameter.
   * @param string $until
   *   The end date parameter.
   * @param string $environment
   *   The environment parameter.
   *
   * @return bool
   *   TRUE if the parameters are correctly set, else FALSE.
   */
  protected function validateQueryString(array $form, $metric, $since, $until, $environment): bool {
    if ($metric === NULL || $since === NULL || $until === NULL || $environment === NULL) {
      return FALSE;
    }

    try {
      if (!array_key_exists($metric, $form['controls']['metrics']['#options'])) {
        $this->messenger()
          ->addError($this->t('Invalid parameter metric in the URL.'));
        return FALSE;
      }

      $since = DrupalDateTime::createFromTimestamp($since);
      $until = DrupalDateTime::createFromTimestamp($until);
      if ($since->diff($until)->invert === 1) {
        $this->messenger()
          ->addError($this->t('The end date cannot be before the start date.'));
        return FALSE;
      }
      if ($since->diff(new DrupalDateTime())->invert === 1) {
        $this->messenger()
          ->addError($this->t('Start date cannot be in future. The current local time of the Developer Portal: @time', [
            '@time' => new DrupalDateTime(),
          ]));
        return FALSE;
      }
      if (!in_array($environment, $this->config('apigee_edge.common_app_settings')->get('analytics_available_environments'))) {
        $this->messenger()
          ->addError($this->t('Invalid parameter environment in the URL.'));
        return FALSE;
      }
    }
    catch (\InvalidArgumentException $exception) {
      $this->messenger()->addError($this->t('Invalid URL query parameters.'));
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Requests analytics data and pass to the JavaScript chart drawing function.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\apigee_edge\Entity\AppInterface $app
   *   The app entity that analytics data gets displayed.
   * @param string $metric
   *   The filter parameter.
   * @param string $since
   *   The start date parameter.
   * @param string $until
   *   The end date parameter.
   * @param string $environment
   *   The analytics environment to query.
   *
   * @see apigee_edge.libraries.yml
   * @see apigee_edge.analytics.js
   */
  protected function generateResponse(array &$form, AppInterface $app, string $metric, string $since, string $until, string $environment): void {
    $analytics = [];
    try {
      $analytics = $this->getAnalytics($app, $metric, $since, $until, $environment);
    }
    catch (MomentException $e) {
      $this->messenger()->addError($this->t('Invalid date parameters.'));
    }
    catch (\Exception $e) {
      $this->messenger()
        ->addError($this->t('Unable to retrieve analytics data. Please try again.'));
      watchdog_exception('apigee_edge', $e);
    }

    $date_time_zone = new \DateTimeZone(date_default_timezone_get());
    $timezone_offset = $date_time_zone->getOffset(new \DateTime());
    $form['#attached']['drupalSettings']['analytics']['timezone_offset'] = $timezone_offset / 60;

    // Pass every necessary data to JavaScript.
    // Possible parameters:
    // - metric: name of the requested metric,
    // - timestamps: all time units in the given time interval,
    // - values: returned optimized metrics data.
    // - skip_zero_values: skip the zero analytics values or not,
    // - visualization_options: options for Google Charts draw() function,
    // - visualization_options_to_date: which property values should be
    // converted to JavaScript Date object on the client-side (timestamps),
    // - version: Google Charts library version (default is the current stable),
    // - language: to load a chart formatted for a specific locale,
    // - chart_container: ID attribute of the chart's HTML container element.
    if (isset($analytics['stats']['data'][0]['metric'][0]['values'])) {
      // Store analytics data in private temp storage.
      $analytics['metric'] = $form['controls']['metrics']['#options'][$metric];
      $this->store->set($data_id = Crypt::randomBytesBase64(), $analytics);
      $form['export_csv']['#url'] = Url::fromRoute('apigee_edge.export_analytics.csv', ['data_id' => $data_id]);

      $form['#attached']['drupalSettings']['analytics']['metric'] = $form['controls']['metrics']['#options'][$metric];
      $form['#attached']['drupalSettings']['analytics']['timestamps'] = $analytics['TimeUnit'];
      $form['#attached']['drupalSettings']['analytics']['values'] = $analytics['stats']['data'][0]['metric'][0]['values'];
      $form['#attached']['drupalSettings']['analytics']['skip_zero_values'] = FALSE;
      $form['#attached']['drupalSettings']['analytics']['language'] = $this->currentUser()
        ->getPreferredLangcode();
      $form['#attached']['drupalSettings']['analytics']['chart_container'] = $form['chart']['#attributes']['id'];

      // Visualization options for Google Charts draw() function,
      // must be JSON encoded before passing.
      // @see: https://developers.google.com/chart/interactive/docs/gallery/linechart#configuration-options
      $visualization_options = [
        'width' => '100%',
        'legend' => 'none',
        'interpolateNulls' => 'true',
        'hAxis' => [
          'viewWindow' => [
            'min' => !empty($analytics['TimeUnit']) ? min($analytics['TimeUnit']) : 0,
            'max' => !empty($analytics['TimeUnit']) ? max($analytics['TimeUnit']) : 0,
          ],
          'gridlines' => [
            'count' => -1,
            'units' => [
              'days' => [
                'format' => ['MMM dd'],
              ],
              'hours' => [
                'format' => ['HH:mm', 'ha'],
              ],
            ],
          ],
          'minorGridlines' => [
            'units' => [
              'hours' => [
                'format' => ['hh:mm:ss a', 'ha'],
              ],
            ],
          ],
        ],
      ];
      $form['#attached']['drupalSettings']['analytics']['visualization_options'] = json_encode($visualization_options);

      $visualization_options_to_date = [
        'hAxis.viewWindow.min',
        'hAxis.viewWindow.max',
      ];
      $form['#attached']['drupalSettings']['analytics']['visualization_options_to_date'] = $visualization_options_to_date;
    }
    else {
      $form['chart']['#attributes']['class'][] = 'chart-container-no-data';
      $form['chart']['no_data_text'] = [
        '#markup' => $this->t('No performance data is available for the criteria you supplied.'),
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $options = [
      'query' => [
        'metric' => $form_state->getValue('metrics'),
        'since' => $form_state->getValue('since')->getTimeStamp(),
        'until' => $form_state->getValue('until')->getTimeStamp(),
        'environment' => $form_state->getValue('environment'),
      ],
    ];
    $form_state->setRedirect('<current>', [], $options);
  }

  /**
   * Retrieves the app analytics for the given criteria.
   *
   * @param \Drupal\apigee_edge\Entity\AppInterface $app
   *   The app entity that analytics data gets displayed.
   * @param string $metric
   *   The filter parameter.
   * @param string $since
   *   The start date parameter.
   * @param string $until
   *   The end date parameter.
   * @param string $environment
   *   The analytics environment to query.
   *
   * @return array
   *   The raw analytics API response for the given criteria.
   *
   * @throws \Moment\MomentException
   *   If provided date values are invalid.
   * @throws \Apigee\Edge\Exception\ApiException
   *   If analytics query fails.
   */
  final protected function getAnalytics(AppInterface $app, string $metric, string $since, string $until, string $environment): array {
    $stats_controller = new StatsController($environment, $this->connector->getOrganization(), $this->connector->getClient());
    $stats_query = new StatsQuery([$metric], new Period(new \DateTimeImmutable('@' . $since), new \DateTimeImmutable('@' . $until)));
    $stats_query
      ->setFilter("({$this->getAnalyticsFilterCriteriaByAppOwner($app)} and developer_app eq '{$app->getName()}')")
      ->setTimeUnit('hour');
    return $stats_controller->getOptimizedMetricsByDimensions(['apps'], $stats_query);
  }

  /**
   * Returns the analytics filter criteria that limits the result by app owner.
   *
   * @param \Drupal\apigee_edge\Entity\AppInterface $app
   *   The app entity.
   *
   * @return string
   *   The analytics filter criteria for the app owner.
   *
   * @see getAnalytics()
   */
  abstract protected function getAnalyticsFilterCriteriaByAppOwner(AppInterface $app): string;

}
