<?php

namespace Drupal\apigee_edge\Annotation;

use Drupal\Core\Entity\Annotation\EntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\apigee_edge\Entity\Type\EdgeEntityType as EntityEdgeEntityType;

/**
 * Defines an Edge entity type annotation object.
 *
 * The annotation properties of entity types are found on
 * \Drupal\apigee_edge\Entity\Type\EdgeEntityType and are accessed using
 * get/set methods defined in \Drupal\Core\Entity\EntityTypeInterface.
 *
 * @Annotation
 */
class EdgeEntityType extends EntityType {

  /**
   * {@inheritdoc}
   */
  public $entity_type_class = EntityEdgeEntityType::class;

  /**
   * {@inheritdoc}
   */
  public $group = 'edge';

  /**
   * {@inheritdoc}
   */
  public function get() {
    $this->definition['group_label'] = new TranslatableMarkup('Edge', [], ['context' => 'Entity type group']);

    return parent::get();
  }

}
