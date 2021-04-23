<?php

/**
 * @file
 * Implements tokens for apigee_edge_teams module.
 */

/**
 * Copyright 2020 Google Inc.
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

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;

/**
 * Implements hook_token_info_alter().
 */
function apigee_edge_teams_token_info_alter(&$info) {
  if (!empty($info['tokens']['team_invitation'])) {
    $info['tokens']['team_invitation']['team_name'] = [
      'name' => t('Team name'),
      'description' => t('The name of the team.'),
      'type' => 'team_invitation',
    ];
    $info['tokens']['team_invitation']['url_accept'] = [
      'name' => t('Accept URL'),
      'description' => t('The invitation accept url.'),
      'type' => 'team_invitation',
    ];
    $info['tokens']['team_invitation']['url_decline'] = [
      'name' => t('Decline URL'),
      'description' => t('The invitation decline url.'),
      'type' => 'team_invitation',
    ];
    $info['tokens']['team_invitation']['url_register'] = [
      'name' => t('Registration URL'),
      'description' => t('A url to register an account with a redirect to accept the team invitation.'),
      'type' => 'team_invitation',
    ];
    $info['tokens']['team_invitation']['expiry_days'] = [
      'name' => t('Expiry days'),
      'description' => t('The expiry days for the team invitation.'),
      'type' => 'team_invitation',
    ];
  }
}

/**
 * Implements hook_tokens().
 */
function apigee_edge_teams_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  if (!isset($data['team_invitation'])) {
    return [];
  }

  /** @var \Drupal\apigee_edge_teams\Entity\TeamInvitationInterface $team_invitation */
  $team_invitation = $data['team_invitation'];
  $team = $team_invitation->getTeam();
  $replacements = [];

  foreach ($tokens as $field_name => $original) {
    switch ($field_name) {
      case "team_name":
        $replacements[$original] = $team->label();
        break;

      case "url_accept":
        $query = ['destination' => $team_invitation->toUrl('accept-form')->setRouteParameter('team', $team->id())->toString()];
        $replacements[$original] = Url::fromRoute('user.login', [], ['query' => $query, 'absolute' => TRUE])->toString();
        break;

      case "url_decline":
        $query = ['destination' => $team_invitation->toUrl('decline-form')->setRouteParameter('team', $team->id())->toString()];
        $replacements[$original] = Url::fromRoute('user.login', [], ['query' => $query, 'absolute' => TRUE])->toString();
        break;

      case "url_register":
        $query = ['destination' => $team_invitation->toUrl('accept-form')->setRouteParameter('team', $team->id())->toString()];
        $replacements[$original] = Url::fromRoute('user.register', [], ['query' => $query, 'absolute' => TRUE])->toString();
        break;

      case "expiry_days":
        $replacements[$original] = Drupal::config('apigee_edge_teams.team_settings')->get('team_invitation_expiry_days');
        break;
    }
  }

  return $replacements;
}
