<?php

namespace Drupal\apigee_edge\Entity\Type;

use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityType;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider;

/**
 * Provides an implementation of an Edge entity type and its metadata.
 */
class EdgeEntityType extends EntityType implements EdgeEntityTypeInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $definition) {
    parent::__construct($definition);
    $this->handlers += [
      'view_builder' => EntityViewBuilder::class,
      'list_builder' => EntityListBuilder::class,
      'route_provider' => [
        'html' => DefaultHtmlRouteProvider::class,
      ],
    ];

    $this->links += [
      'canonical' => "/{$this->id}/{{$this->id}}",
      'collection' => "/{$this->id}",
    ];
  }

}
