<?php

namespace Drupal\apigee_edge\Entity\Storage;

/**
 * Defines an interface for developer app entity storage classes.
 */
interface DeveloperAppStorageInterface extends FieldableEdgeEntityStorageInterface {

  /**
   * Loads developer apps by developer.
   *
   * @param string $developerId
   *   Developer id (uuid) or email address of a developer.
   *
   * @return \Drupal\apigee_edge\Entity\DeveloperApp[]
   *   The array of the developer apps of the given developer.
   */
  public function loadByDeveloper(string $developerId): array;

}
