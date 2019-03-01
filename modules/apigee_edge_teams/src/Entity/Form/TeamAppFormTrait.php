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

namespace Drupal\apigee_edge_teams\Entity\Form;

use Apigee\Edge\Exception\ApiException;
use Apigee\Edge\Exception\ClientErrorException;
use Drupal\apigee_edge\Entity\ApiProductInterface;
use Drupal\apigee_edge_teams\TeamMemberApiProductAccessHandlerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Error;

/**
 * Helper trait that contains team app (create/edit) form specific tweaks.
 */
trait TeamAppFormTrait {

  /**
   * {@inheritdoc}
   */
  public static function appExists(string $name, array $element, FormStateInterface $form_state): bool {
    // Do not validate if app name is not set.
    if ($name === '') {
      return FALSE;
    }

    // We use the team app controller factory here instead of entity
    // query to reduce the number API calls. (Entity query may load all
    // developers to return whether the given team has an app with
    // the provided name already.)
    /** @var \Drupal\apigee_edge_teams\Entity\Controller\TeamAppControllerFactoryInterface $factory */
    $factory = \Drupal::service('apigee_edge_teams.controller.team_app_controller_factory');
    $app = TRUE;
    try {
      $app = $factory->teamAppController($form_state->getValue('owner'))->load($name);
    }
    catch (ApiException $exception) {
      if ($exception instanceof ClientErrorException && $exception->getEdgeErrorCode() === 'developer.service.AppDoesNotExist') {
        $app = FALSE;
      }
      else {
        // Fail safe, return TRUE in case of an API communication error or an
        // unexpected response.
        $context = [
          '%app_name' => $name,
          '%owner' => $form_state->getValue('owner'),
        ];
        $context += Error::decodeException($exception);
        \Drupal::logger('apigee_edge_teams')->error("Unable to properly validate an app name's uniqueness. App name: %app_name. Owner: %owner. @message %function (line %line of %file). <pre>@backtrace_string</pre>", $context);
      }
    }

    return (bool) $app;
  }

  /**
   * {@inheritdoc}
   */
  protected function appEntityDefinition(): EntityTypeInterface {
    return $this->getEntityTypeManager()->getDefinition('team_app');
  }

  /**
   * {@inheritdoc}
   */
  protected function appOwnerEntityDefinition(): EntityTypeInterface {
    return $this->getEntityTypeManager()->getDefinition('team');
  }

  /**
   * {@inheritdoc}
   */
  protected function appCredentialLifeTime(): int {
    $config_name = 'apigee_edge_teams.team_app_settings';
    $config = method_exists($this, 'config') ? $this->config($config_name) : \Drupal::config($config_name);
    return $config->get('credential_lifetime');
  }

  /**
   * Allows to access to the injected entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  private function getEntityTypeManager(): EntityTypeManagerInterface {
    if (property_exists($this, 'entityTypeManager') && $this->entityTypeManager instanceof EntityTypeManagerInterface) {
      return $this->entityTypeManager;
    }

    return \Drupal::entityTypeManager();
  }

  /**
   * Allows to access to the injected team member API product access handler.
   *
   * @return \Drupal\apigee_edge_teams\TeamMemberApiProductAccessHandlerInterface
   *   The team member API product access handler.
   */
  private function getTeamMemberApiProductAccessHandler(): TeamMemberApiProductAccessHandlerInterface {
    if (property_exists($this, 'teamMemberApiProductAccessHandler') && $this->teamMemberApiProductAccessHandler instanceof TeamMemberApiProductAccessHandlerInterface) {
      return $this->teamMemberApiProductAccessHandler;
    }

    return \Drupal::service('apigee_edge_teams.team_member_api_product_access_handler');
  }

  /**
   * {@inheritdoc}
   */
  protected function apiProductList(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface|null $team */
    $team = $this->getEntityTypeManager()->getStorage('team')->load($team_name);
    // Sanity check, team should always exists with team name in this context.
    if ($team === NULL) {
      return [];
    }

    return array_filter($this->getEntityTypeManager()->getStorage('api_product')->loadMultiple(), function (ApiProductInterface $api_product) use ($team) {
      return $this->getTeamMemberApiProductAccessHandler()->access($api_product, 'assign', $team);
    });
  }

}
