<?php

namespace Drupal\apigee_edge\Controller;
use Drupal\Core\Entity\EntityBase;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RedirectController extends ControllerBase {

  private $entity_id;
  private $entity_type_id;

  private function setEntityID($entity = null) {
    if (is_string($entity)) {
      $this->entity_id = $entity;
    } elseif ($entity instanceof EntityBase ) {
      $this->entity_id = $entity->id();
    }
  }

  private function setEntityTypeID($entity = null) {
    if ($entity instanceof EntityBase ) {
      $this->entity_type_id = $entity->getEntityTypeId();
    }
  }

  public function manageRedirect($entity = null, $route = 'system.404') {

    if ($entity !=null) {
      $this->setEntityID($entity);
      $this->setEntityTypeId($entity);
      $redirect = $this->redirect($route, [$this->entity_type_id => $this->entity_id]);
    } else {
      $redirect = $this->redirect($route);
    }

    return $redirect;

  }
}
