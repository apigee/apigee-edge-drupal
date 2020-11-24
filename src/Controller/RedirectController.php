<?php

namespace Drupal\apigee_edge\Controller;

use Drupal\Core\Entity\EntityBase;
use Drupal\Core\Controller\ControllerBase;

/**
 * Controller to redirect changed paths.
 */
class RedirectController extends ControllerBase {

  /**
   * The entity id from path input.
   *
   * @var string
   */
  private $entity_id;

  /**
   * Entity type from path input.
   *
   * @var string
   */
  private $entity_type_id;

  /**
   * Identity the entity id.
   *
   * @param mixed $entity_slug
   *   Convert entity slug from path to id.
   */
  private function setEntityId($entity_slug) {
    if (is_string($entity_slug)) {
      $this->entity_id = $entity_slug;
    }

    elseif ($entity_slug instanceof EntityBase) {
      $this->entity_id = $entity_slug->id();
    }

  }

  /**
   * Identity the type of entity.
   *
   * @param mixed $entity_slug
   *   Entity slug from input.
   */
  private function setEntityTypeId($entity_slug) {
    if ($entity_slug instanceof EntityBase) {
      $this->entity_type_id = $entity_slug->getEntityTypeId();
    }
  }

  /**
   * Redirect the given path to the correct route.
   *
   * @param mixed $entity_slug
   *   Entity slug from input.
   * @param string $redirect_route
   *   Route parameter defined as defined in routing.yml.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Return a 404.
   */
  public function manageRedirect($entity_slug = NULL, $redirect_route = 'system.404') {

    if ($entity_slug != NULL) {
      $this->setEntityId($entity_slug);
      $this->setEntityTypeId($entity_slug);
      $redirect = $this->redirect($redirect_route, [$this->entity_type_id => $this->entity_id]);
    }

    else {
      $redirect = $this->redirect($redirect_route);
    }

    return $redirect;

  }

}
