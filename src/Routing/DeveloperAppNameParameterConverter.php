<?php


namespace Drupal\apigee_edge\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Symfony\Component\Routing\Route;

/**
 * Resolves "developer_app_by_name" type parameters in path.
 */
class DeveloperAppNameParameterConverter implements ParamConverterInterface {

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
  protected $entityTypeManager;

  /**
   * DeveloperAppNameParameterConverter constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->entityTypeManager->getStorage('user')->load($defaults['user']);
    if ($user) {
      $storedDeveloperId = $user->get('apigee_edge_developer_id')->target_id;
      if ($storedDeveloperId) {
        $ids = $this->entityTypeManager->getStorage('developer_app')->getQuery()
          ->condition('developerId', $storedDeveloperId)
          ->condition('name', $value)
          ->execute();
        if (!empty($ids)) {
          $id = reset($ids);
          $entity = $this->entityTypeManager->getStorage('developer_app')
            ->load($id);
          return $entity;
        }
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return (!empty($definition['type']) && $definition['type'] == 'developer_app_by_name') && isset($route->getOptions()['parameters']['user']);
  }

}
