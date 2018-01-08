<?php

namespace Drupal\apigee_edge\Entity\Storage;

interface DeveloperAppStorageInterface extends EdgeEntityStorageInterface {

  /**
   * Loads developer apps by developer.
   *
   * @param string $developerId
   *   Developer id (uuid) or email address of a developer.
   *
   * @return \Drupal\apigee_edge\Entity\DeveloperApp[]
   */
  public function loadByDeveloper(string $developerId): array;
}
