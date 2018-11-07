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

    $form['sync']['description'] = [
      '#type' => 'container',
      'p1' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Developer synchronization will:'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Create Drupal users for any Apigee Edge developers that are in this Drupal system'),
          $this->t('Create developers in Apigee Edge for all users in this Drupal system that are not already in Apigee Edge'),
        ],
      ],
      'p2' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Note that any Drupal users that are created will have a random password generated and will need to reset their password to log in. The "Run developer sync" button will sync the developers, displaying a progress bar on the screen while running. The "Background developer sync" button will run the developer sync process in batches each time <a href=":cron_url">cron</a> runs and may take multiple cron runs to complete.', [':cron_url' => Url::fromRoute('system.cron_settings')->toString()]),
      ],
    ];

    $form['sync']['sync_submit'] = [
      '#title' => $this->t('Run developer sync'),
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
      '#title' => $this->t('Background developer sync'),
      '#type' => 'link',
      '#url' => $this->buildUrl('apigee_edge.developer_sync.schedule'),
      '#attributes' => [
        'class' => [
          'button',
        ],
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
