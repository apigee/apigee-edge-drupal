<?php

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Api\Management\Entity\DeveloperApp as EdgeDeveloperApp;

/**
 * @\Drupal\apigee_edge\Annotation\EdgeEntityType(
 *   id = "developer_app",
 *   label = @Translation("Developer app"),
 *   handlers = {
 *     "storage" = "\Drupal\apigee_edge\Entity\Storage\DeveloperAppStorage",
 *     "list_builder" = "\Drupal\apigee_edge\Entity\ListBuilder\DeveloperAppListBuilder",
 *   },
 * )
 */
class DeveloperApp extends EdgeDeveloperApp implements DeveloperAppInterface {

  use EdgeEntityBaseTrait;

}
