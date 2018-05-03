<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Form;

use Apigee\Edge\Api\Management\Controller\StatsController;
use Apigee\Edge\Api\Management\Query\StatsQuery;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\apigee_edge\Entity\DeveloperAppPageTitleInterface;
use Drupal\apigee_edge\Entity\DeveloperStatusCheckTrait;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use League\Period\Period;
use Moment\MomentException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the analytics page of a developer app on the UI.
 */
class DeveloperAppAnalyticsForm extends FormBase implements DeveloperAppPageTitleInterface {

  use DeveloperStatusCheckTrait;

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  protected $sdkConnector;

  /**
   * The developer app entity.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $developerApp;

  /**
   * The PrivateTempStore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $store;

  /**
   * Constructs a new DeveloperAppAnalyticsForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdk_connector
   *   The SDK connector service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempstore_private
   *   The private tempstore factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, SDKConnectorInterface $sdk_connector, MessengerInterface $messenger, PrivateTempStoreFactory $tempstore_private) {
    $this->entityTypeManager = $entity_type_manager;
    $this->sdkConnector = $sdk_connector;
    $this->messenger = $messenger;
    $this->store = $tempstore_private->get('apigee_edge.analytics');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('apigee_edge.sdk_connector'),
      $container->get('messenger'),
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_developer_app_analytics';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?DeveloperAppInterface $developer_app = NULL) {
    $this->developerApp = $developer_app;
    $this->checkDeveloperStatus($developer_app->getOwnerId());

    $form_state->disableRedirect();
    $form['#attached']['library'][] = 'apigee_edge/apigee_edge.analytics';

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

    $form['controls']['metrics'] = [
      '#type' => 'select',
      '#options' => [
        'total_response_time' => $this->t('Average response time'),
        'max_response_time' => $this->t('Max response time'),
        'min_response_time' => $this->t('Min response time'),
        'message_count' => $this->t('Message count'),
        'error_count' => $this->t('Error count'),
      ],
      '#default_value' => 'total_response_time',
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
        '@timezone' => $this->currentUser()->getTimeZone(),
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

    if ($this->validateQueryString($form, $metric, $since, $until)) {
      $form['controls']['metrics']['#default_value'] = $metric;
      $since_datetime = DrupalDatetime::createFromTimestamp($since);
      $since_datetime->setTimezone(new \Datetimezone($this->currentUser()->getTimeZone()));
      $until_datetime = DrupalDatetime::createFromTimestamp($until);
      $until_datetime->setTimezone(new \Datetimezone($this->currentUser()->getTimeZone()));
      $form['controls']['since']['#default_value'] = $since_datetime;
      $form['controls']['until']['#default_value'] = $until_datetime;
      $form['controls']['quick_date_picker']['#default_value'] = 'custom';
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
    }

    if (empty($form_state->getUserInput())) {
      $this->generateResponse($form, $metric, $since, $until);
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
   *
   * @return bool
   *   TRUE if the parameters are correctly set, else FALSE.
   */
  protected function validateQueryString(array $form, $metric, $since, $until) : bool {
    if ($metric === NULL || $since === NULL || $until === NULL) {
      return FALSE;
    }

    try {
      if (!array_key_exists($metric, $form['controls']['metrics']['#options'])) {
        $this->messenger->addError($this->t('Invalid parameter metric in the URL.'));
        return FALSE;
      }

      $since = DrupalDateTime::createFromTimestamp($since);
      $until = DrupalDateTime::createFromTimestamp($until);
      if ($since->diff($until)->invert === 1) {
        $this->messenger->addError($this->t('The end date cannot be before the start date.'));
        return FALSE;
      }
      if ($since->diff(new DrupalDateTime())->invert === 1) {
        $this->messenger->addError($this->t('Start date cannot be in future. The current local time of the Developer Portal: @time', [
          '@time' => new DrupalDateTime(),
        ]));
        return FALSE;
      }
    }
    catch (\InvalidArgumentException $exception) {
      $this->messenger->addError($this->t('Invalid URL query parameters.'));
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Requests analytics data and pass to the JavaScript chart drawing function.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param string $metric
   *   The filter parameter.
   * @param string $since
   *   The start date parameter.
   * @param string $until
   *   The end date parameter.
   *
   * @see apigee_edge.libraries.yml
   * @see apigee_edge.analytics.js
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function generateResponse(array &$form, $metric, $since, $until) {
    $developer = Developer::load($this->developerApp->getDeveloperId());
    $stats_controller = new StatsController($this->config('apigee_edge.common_app_settings')->get('analytics_environment'), $this->sdkConnector->getOrganization(), $this->sdkConnector->getClient());
    $stats_query = new StatsQuery([$metric], new Period(new \DateTimeImmutable('@' . $since), new \DateTimeImmutable('@' . $until)));
    $stats_query->setFilter("(developer_email eq '{$developer->id()}' and developer_app eq '{$this->developerApp->getName()}')")
      ->setTimeUnit('hour');
    try {
      $analytics = $stats_controller->getOptimizedMetricsByDimensions(['apps'], $stats_query);
    }
    catch (MomentException $exception) {
      $this->messenger->addError($this->t('Invalid datetime parameters.'));
    }

    $date_time_zone = new \DateTimeZone($this->currentUser()->getTimeZone());
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
      $form['#attached']['drupalSettings']['analytics']['skip_zero_values'] = TRUE;
      $form['#attached']['drupalSettings']['analytics']['language'] = $this->currentUser()->getPreferredLangcode();
      $form['#attached']['drupalSettings']['analytics']['chart_container'] = $form['chart']['#attributes']['id'];

      $viewWindowMin = $viewWindowMax = 0;
      for ($i = count($analytics['TimeUnit']) - 1; $i > 0; $i--) {
        if ($analytics['stats']['data'][0]['metric'][0]['values'][$i] !== 0) {
          $viewWindowMin = $i;
        }
        if ($viewWindowMax === 0 && $analytics['stats']['data'][0]['metric'][0]['values'][$i] !== 0) {
          $viewWindowMax = $i;
        }
      }

      // Visualization options for Google Charts draw() function,
      // must be JSON encoded before passing.
      // @see: https://developers.google.com/chart/interactive/docs/gallery/linechart#configuration-options
      $visualization_options = [
        'width' => '100%',
        'legend' => 'none',
        'interpolateNulls' => 'true',
        'hAxis' => [
          'viewWindow' => [
            'min' => $analytics['TimeUnit'][$viewWindowMin],
            'max' => $analytics['TimeUnit'][$viewWindowMax],
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

      $visualization_options_to_date = ['hAxis.viewWindow.min', 'hAxis.viewWindow.max'];
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
    $url = $this->getUrlGenerator()->generateFromRoute('<current>', [], [
      'query' => [
        'metric' => $form_state->getValue('metrics'),
        'since' => $form_state->getValue('since')->getTimeStamp(),
        'until' => $form_state->getValue('until')->getTimeStamp(),
      ],
    ]);
    $form_state->setRedirectUrl(Url::fromUri('internal:' . $url));
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitle(RouteMatchInterface $routeMatch): string {
    return $this->t('Analytics of @name', [
      '@name' => $routeMatch->getParameter('developer_app')->getDisplayName(),
    ]);
  }

}
