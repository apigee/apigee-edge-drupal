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

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form to start synchronization.
 */
class DeveloperSyncForm extends FormBase {

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
    $form['#attached']['library'][] = 'apigee_edge/apigee_edge.admin';

    $form['sync'] = [
      '#type' => 'details',
      '#title' => $this->t('Sync developers'),
      '#open' => TRUE,
    ];

    $form['sync']['description'] = [
      '#markup' => '<p>' . $this->t('Developer synchronization will run through all users in this portal, adding them as developers in the Apigee Edge org, and making sure all developers on the Apigee Edge org are added to this portal. The "Run Developer Sync" button will sync the developers, displaying a progress bar. The "Background Developer Sync" button will run the developer sync process in batches each time <a href=":cron_url">cron</a> runs.', [
        ':cron_url' => Url::fromRoute('system.cron_settings')->toString()
        ]) . '</p>',
    ];

    $form['sync']['sync_submit'] = [
      '#title' => $this->t('Run Developer Sync'),
      '#type' => 'link',
      '#url' => $this->buildUrl('apigee_edge.user_sync.run'),
      '#attributes' => [
        'class' => [
          'button',
          'button--primary',
        ],
      ],
    ];
    $form['sync']['background_sync_submit'] = [
      '#title' => $this->t('Background Developer Sync'),
      '#type' => 'link',
      '#url' => $this->buildUrl('apigee_edge.user_sync.schedule'),
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
   * Build URL for user synchronization processes, using CSRF protection.
   *
   * @param string $route_name
   *   The name of the route.
   *
   * @return \Drupal\Core\Url
   *   The URL to redirect to.
   */
  protected function buildUrl(string $route_name) {
    $url = Url::fromRoute($route_name);
    $token = \Drupal::csrfToken()->get($url->getInternalPath());
    $url->setOptions(['query' => ['destination' => 'admin/config/apigee-edge/developer-settings/sync', 'token' => $token]]);
    return $url;
  }

}
