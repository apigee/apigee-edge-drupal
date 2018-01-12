<?php

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Api\Management\Entity\ApiProduct as EdgeApiProduct;

/**
 * @\Drupal\apigee_edge\Annotation\EdgeEntityType(
 *   id = "api_product",
 *   label = @Translation("API Product"),
 *   label_singular = @Translation("API Product"),
 *   label_plural = @Translation("API Products"),
 *   label_count = @PluralTranslation(
 *     singular = "@count API Product",
 *     plural = "@count API Products",
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
