<?php

namespace Drupal\apigee_edge_teams\Controller;

use Drupal\apigee_edge_teams\Entity\Team;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TeamRedirectController extends ControllerBase {

  private $team_id;

  private function setTeamID($team = null) {
    if (is_string($team)) {
      $this->team_id = $team;
    } elseif ($team instanceof Team ) {
      $this->team_id = $team->id();
    }
  }

  private function processTeamString($path) {
    return t($path, [ '@team' => $this->team_id])->__toString();
  }

  public function manageRedirect($team = null) {

    if ($team !=null) {
      $this->setTeamID($team);
    }

    $redirect = NULL;

    if ($this->getRedirectRoute() != null ) {

      if ($this->team_id) {
        $redirect = $this->redirect($this->getRedirectRoute(), ['team' => $this->team_id]);
      } else {
        $redirect = $this->redirect($this->getRedirectRoute());
      }
      return $redirect;

    } else {

      throw new NotFoundHttpException;
    }

  }

  private function getRedirectRoute() {
    $paths_to_redirect = [
      'entity.team.add_form' => '/add-team',
      'entity.team_app.collection' => '/team-apps',
      'entity.team_app.add_form' => '/team-apps/add',
      'entity.team.add_members' => $this->processTeamString('/teams/@team/add-members'),
      'entity.team_app.add_form_for_team' => $this->processTeamString('/teams/@team/create-app'),
    ];

    $current_path = \Drupal::service('path.current')->getPath();

    foreach ($paths_to_redirect as $destination_route => $original_path) {

      $match = \Drupal::service('path.matcher')
        ->matchPath($current_path, $original_path);

      if ($match) {
        $redirect_route = $destination_route;
      }
    }

    return isset($redirect_route) ? $redirect_route : null;

  }

}
