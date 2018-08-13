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

use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to start developer synchronization.
 */
class DeveloperSyncForm extends FormBase {

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  protected $sdkConnector;

  /**
   * Constructs a new DeveloperSyncForm.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdk_connector
   *   SDK connector service.
   */
  public function __construct(SDKConnectorInterface $sdk_connector) {
    $this->sdkConnector = $sdk_connector;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('apigee_edge.sdk_connector')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_developer_sync_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    try {
      $this->sdkConnector->testConnection();
    }
    catch (\Exception $exception) {
      $this->messenger()->addError($this->t('Cannot connect to Apigee Edge server. Please ensure that <a href=":link">Apigee Edge connection settings</a> are correct.', [
        ':link' => Url::fromRoute('apigee_edge.settings')->toString(),
      ]));
      return $form;
    }

    $form['#attached']['library'][] = 'apigee_edge/apigee_edge.admin';

    $form['sync'] = [
      '#type' => 'details',
      '#title' => $this->t('Synchronize developers'),
      '#open' => TRUE,
    ];
    $form['sync']['sync_submit'] = [
      '#title' => $this->t('Now'),
      '#type' => 'link',
      '#url' => $this->buildUrl('apigee_edge.developer_sync.run'),
      '#attributes' => [
        'class' => [
          'button',
          'button--primary',
        ],
      ],
    ];
    $form['sync']['background_sync_submit'] = [
      '#title' => $this->t('Background'),
      '#type' => 'link',
      '#url' => $this->buildUrl('apigee_edge.developer_sync.schedule'),
      '#attributes' => [
        'class' => [
          'button',
        ],
      ],
    ];
    $form['sync']['sync_info'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => '?',
      '#attributes' => [
        'class' => 'info-circle',
        'title' => $this->t('A background sync is recommended for large numbers of developers.'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Build URL for developer-user sync processes, using CSRF protection.
   *
   * @param string $route_name
   *   The name of the route.
   *
   * @return \Drupal\Core\Url
   *   The URL to redirect to.
   */
  protected function buildUrl(string $route_name): Url {
    $url = Url::fromRoute($route_name);
    $token = \Drupal::csrfToken()->get($url->getInternalPath());
    $url->setOptions(['query' => ['destination' => 'admin/config/apigee-edge/developer-settings/sync', 'token' => $token]]);
    return $url;
  }

}
