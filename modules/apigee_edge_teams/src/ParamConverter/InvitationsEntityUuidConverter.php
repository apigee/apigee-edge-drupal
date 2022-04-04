<?php

namespace Drupal\apigee_edge_teams\ParamConverter;

use Drupal\jsonapi\ParamConverter\EntityUuidConverter;

use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\jsonapi\Routing\Routes;
use Drupal\Core\Routing\RouteObjectInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Routing\Route;

/**
 * Tweak to the EntityUuidConverter in the JSON Api to make work with team invitations.
 */
class InvitationsEntityUuidConverter extends EntityUuidConverter {

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $entity_type_id = $this->getEntityTypeFromDefaults($definition, $name, $defaults);
    // This line is the key difference between this and EntityUuidConverter.
    // It is here because the uuid is stored as an id does not exist for Invitation Entities.
    $uuid_key = $this->entityTypeManager->getDefinition($entity_type_id)
      ->getKey('id');

    if ($storage = $this->entityTypeManager->getStorage($entity_type_id)) {
      if (!$entities = $storage->loadByProperties([$uuid_key => $value])) {
        return NULL;
      }
      $entity = reset($entities);
      // If the entity type is translatable, ensure we return the proper
      // translation object for the current context.
      if ($entity instanceof TranslatableInterface && $entity->isTranslatable()) {
        // @see https://www.drupal.org/project/drupal/issues/2624770
        $entity = $this->entityRepository->getTranslationFromContext($entity, NULL, ['operation' => 'entity_upcast']);
        // JSON:API always has only one method per route.
        $method = $defaults[RouteObjectInterface::ROUTE_OBJECT]->getMethods()[0];
        if (in_array($method, ['PATCH', 'DELETE'], TRUE)) {
          $current_content_language = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
          if ($method === 'DELETE' && (!$entity->isDefaultTranslation() || $entity->language()->getId() !== $current_content_language)) {
            throw new MethodNotAllowedHttpException(['GET'], 'Deleting a resource object translation is not yet supported. See https://www.drupal.org/docs/8/modules/jsonapi/translations.');
          }
          if ($method === 'PATCH' && $entity->language()->getId() !== $current_content_language) {
            $available_translations = implode(', ', array_keys($entity->getTranslationLanguages()));
            throw new MethodNotAllowedHttpException(['GET'], sprintf('The requested translation of the resource object does not exist, instead modify one of the translations that do exist: %s.', $available_translations));
          }
        }
      }
      return $entity;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return (
      (bool) Routes::getResourceTypeNameFromParameters($route->getDefaults()) &&
      $definition['type'] === 'entity:team_invitation'
    );
  }

}
