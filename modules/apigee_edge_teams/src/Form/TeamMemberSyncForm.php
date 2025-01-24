<?php

/**
 * Copyright 2022 Google Inc.
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

namespace Drupal\apigee_edge_teams\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface;
use Drupal\apigee_edge\SDKConnectorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to start team member synchronization.
 */
class TeamMemberSyncForm extends FormBase {

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  protected $sdkConnector;

  /**
   * The organization controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface
   */
  private $orgController;

  /**
   * Constructs a new TeamMemberSyncForm.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdk_connector
   *   SDK connector service.
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface $org_controller
   *   The organization controller service.
   */
  public function __construct(SDKConnectorInterface $sdk_connector, OrganizationControllerInterface $org_controller) {
    $this->sdkConnector = $sdk_connector;
    $this->orgController = $org_controller;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('apigee_edge.sdk_connector'),
      $container->get('apigee_edge.controller.organization')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_team_member_sync_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    try {
      $this->sdkConnector->testConnection();
    }
    catch (\Exception $exception) {
      $this->messenger()->addError($this->t('Cannot connect to Apigee server. Please ensure that <a href=":link">Apigee connection settings</a> are correct.', [
        ':link' => Url::fromRoute('apigee_edge.settings')->toString(),
      ]));
      return $form;
    }

    $form['#attached']['library'][] = 'apigee_edge/apigee_edge.admin';

    $form['sync'] = [
      '#type' => 'details',
      '#title' => $this->t('Synchronize team members'),
      '#open' => TRUE,
    ];

    $form['sync']['description'] = [
      '#type' => 'container',
      'p1' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Team member synchronization will:'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Caches team members in Drupal'),
        ],
      ],
      'p2' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('The "Run team member sync" button will sync the team members, displaying a progress bar on the screen while running. The "Background team member sync" button will run the team member sync process in batches each time <a href=":cron_url">cron</a> runs and may take multiple cron runs to complete.', [':cron_url' => Url::fromRoute('system.cron_settings')->toString()]),
      ],
      'p3' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('By running the sync, team member detail is stored in members cache table and will have expiry that is set in <a href=":team_caching">team caching</a>. To show more than 100 teams for a member enable permission "View extensive teams list". ', [':team_caching' => Url::fromRoute('apigee_edge_teams.settings.team.cache')->toString()]),
      ],
    ];

    // Displaying this message only for ApigeeX.
    if ($this->orgController->isOrganizationApigeeX()) {
      $form['sync']['description']['list'] = [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Caches team members in Drupal'),
          $this->t('Migrate the members information from Apigee X to Drupal portal database.'),
        ],
      ];
      $form['sync']['description']['p4'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('<strong>Note: </strong>First, execute the <a href=":developer_sync">Developer sync</a> to add all the developers from ApigeeX to Drupal portal, and then proceed to add the required <a href=":developer_roles">member roles</a>. Finally, run the Team member sync. ', [':developer_sync' => Url::fromRoute('apigee_edge.settings.developer.sync')->toString(), ':developer_roles' => Url::fromRoute('entity.team_role.collection')->toString()]),
      ];
    }

    $form['sync']['sync_submit'] = [
      '#title' => $this->t('Run team member sync'),
      '#type' => 'link',
      '#url' => $this->buildUrl('apigee_edge_teams.team_member.run'),
      '#attributes' => [
        'class' => [
          'button',
          'button--primary',
        ],
      ],
    ];
    $form['sync']['background_team_member_sync_submit'] = [
      '#title' => $this->t('Background team member sync'),
      '#type' => 'link',
      '#url' => $this->buildUrl('apigee_edge_teams.team_member.schedule'),
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
   * Build URL for team member sync processes, using CSRF protection.
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
    $url->setOptions(['query' => ['destination' => 'admin/config/apigee-edge/app-settings/team-settings/sync', 'token' => $token]]);
    return $url;
  }

}
