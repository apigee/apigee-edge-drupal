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

namespace Drupal\apigee_edge_teams\Form;

use Drupal\apigee_edge_teams\TeamPermissionHandlerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for configuring team-level permissions for all teams.
 *
 * Based on UserPermissionsForm.
 *
 * @internal
 *
 * @see \Drupal\user\Form\UserPermissionsForm
 */
class TeamPermissionsForm extends FormBase {

  /**
   * The team permission handler.
   *
   * @var \Drupal\apigee_edge_teams\TeamPermissionHandlerInterface
   */
  protected $teamPermissionHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * TeamPermissionsForm constructor.
   *
   * @param \Drupal\apigee_edge_teams\TeamPermissionHandlerInterface $team_permissions
   *   The team permission handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(TeamPermissionHandlerInterface $team_permissions, EntityTypeManagerInterface $entity_type_manager) {

    $this->teamPermissionHandler = $team_permissions;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('apigee_edge_teams.team_permissions'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_teams_permissions_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['non_member_team_apps_visible_api_products'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Visible API products on team app add/edit forms for users who are not member of a team'),
      '#description' => $this->t("This configuration allows to limit the visible API products on team app add/edit forms for users who are not a member of the team but still has access to these forms. For example, if a user is not member a team, but it has \"Manage team apps\" site-wide permission then it can create team apps for the team and edit any team apps owned by the team.<br>Suggestion: keep this configuration in sync with the team administrator's API product access settings."),
      '#options' => [
        'public' => $this->t('Public'),
        'private' => $this->t('Private'),
        'internal' => $this->t('Internal'),
      ],
      '#default_value' => $this->config('apigee_edge_teams.team_settings')->get('non_member_team_apps_visible_api_products'),
    ];

    $role_names = [];
    $role_permissions = [];
    $roles = $this->getTeamRoles();

    // The member role should be in the first column.
    $member = $roles['member'];
    unset($roles['member']);
    $roles = ['member' => $member] + $roles;
    // The admin role should be in the last one.
    if (isset($roles['admin'])) {
      $admin = $roles['admin'];
      unset($roles['admin']);
      $roles['admin'] = $admin;
    }

    foreach ($roles as $role_name => $role) {
      // Retrieve role names for columns.
      $role_names[$role_name] = $role->label();
      // Fetch team permission ids for the roles.
      $role_permissions[$role_name] = $role->getPermissions();
    }

    // Store $role_names for use when saving the data.
    $form['role_names'] = [
      '#type' => 'value',
      '#value' => $role_names,
    ];
    // Render role/permission overview:
    $hide_descriptions = system_admin_compact_mode();

    $form['system_compact_link'] = [
      '#id' => FALSE,
      '#type' => 'system_compact_link',
    ];

    $form['permissions'] = [
      '#type' => 'table',
      '#header' => [$this->t('Permission')],
      '#id' => 'permissions',
      '#attributes' => ['class' => ['permissions', 'js-permissions']],
      '#sticky' => TRUE,
    ];
    foreach ($role_names as $name) {
      $form['permissions']['#header'][] = [
        'data' => $name,
        'class' => ['checkbox'],
      ];
    }

    foreach ($this->teamPermissionHandler->getPermissions() as $permission) {
      // Team permission group name.
      $category_id = preg_replace('/[^A-Za-z0-9_]+/', '_', $permission->getCategory()->getUntranslatedString());
      $form['permissions'][$category_id] = [
        [
          '#wrapper_attributes' => [
            'colspan' => count($role_names) + 1,
            'class' => ['group'],
            'id' => Html::getId($category_id),
          ],
          '#markup' => $permission->getCategory(),
        ],
      ];
      $form['permissions'][$permission->getName()]['description'] = [
        '#type' => 'inline_template',
        '#template' => '<div class="permission"><span class="title">{{ title }}</span>{% if description %}<div class="description">{{ description }}</div>{% endif %}</div>',
        '#context' => [
          'title' => $permission->getLabel(),
        ],
      ];
      // Show the permission description.
      if (!$hide_descriptions) {
        $form['permissions'][$permission->getName()]['description']['#context']['description'] = $permission->getDescription() ?? '';
      }
      foreach ($role_names as $rid => $name) {
        $form['permissions'][$permission->getName()][$rid] = [
          '#title' => $permission->getName() . ': ' . $permission->getLabel(),
          '#title_display' => 'invisible',
          '#wrapper_attributes' => [
            'class' => ['checkbox'],
          ],
          '#type' => 'checkbox',
          '#default_value' => in_array($permission->getName(), $role_permissions[$rid]) ? 1 : 0,
          '#attributes' => ['class' => ['rid-' . $rid, 'js-rid-' . $rid]],
          '#parents' => [$rid, $permission->getName()],
        ];
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save permissions'),
      '#button_type' => 'primary',
    ];

    $form['#attached']['library'][] = 'apigee_edge_teams/permissions';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory()->getEditable('apigee_edge_teams.team_settings')->set('non_member_team_apps_visible_api_products', array_keys(array_filter($form_state->getValue('non_member_team_apps_visible_api_products', []))))->save();

    /** @var \Drupal\apigee_edge_teams\Entity\Storage\TeamRoleStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('team_role');
    foreach ($form_state->getValue('role_names') as $role_name => $name) {
      $storage->changePermissions($role_name, (array) $form_state->getValue($role_name));
    }

    $this->messenger()->addStatus($this->t('The changes have been saved.'));
  }

  /**
   * Gets the team roles to display in this form.
   *
   * @return \Drupal\apigee_edge_teams\Entity\TeamRoleInterface[]
   *   Array of team roles.
   */
  protected function getTeamRoles(): array {
    return $this->entityTypeManager->getStorage('team_role')->loadMultiple();
  }

}
