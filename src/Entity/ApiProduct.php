<?php

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Api\Management\Entity\ApiProduct as EdgeApiProduct;

/**
 * Defines the API product entity class.
 *
 * @\Drupal\apigee_edge\Annotation\EdgeEntityType(
 *   id = "api_product",
 *   label = @Translation("API"),
 *   label_singular = @Translation("API"),
 *   label_plural = @Translation("APIs"),
 *   label_count = @PluralTranslation(
 *     singular = "@count API",
 *     plural = "@count APIs",
 *   ),
 *   handlers = {
 *     "storage" = "\Drupal\apigee_edge\Entity\Storage\ApiProductStorage",
 *   },
 * )
 */
class ApiProduct extends EdgeApiProduct implements ApiProductInterface {

  use EdgeEntityBaseTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values = []) {
    parent::__construct($values);
    $this->entityTypeId = 'api_product';
  }

}
