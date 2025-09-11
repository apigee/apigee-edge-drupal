<?php

namespace Drupal\apigee_edge_teams\EventSubscriber;

use Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface;
use Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface;
use Drupal\apigee_edge\Event\AppCredentialAddApiProductEvent;
use Drupal\apigee_edge\SDKConnectorInterface;
use Apigee\Edge\Api\ApigeeX\Controller\AppGroupAppCredentialController;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles AppGroup scopes after API products have been added to a credential.
 */
class AppGroupScopeHandler implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The SDK connector.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  protected $sdkConnector;

  /**
   * The app cache factory.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface
   */
  protected $appCacheFactory;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The organization controller.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface
   */
  protected $organizationController;

  /**
   * AppGroupScopeHandler constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdkConnector
   *   The SDK connector.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface $appCacheFactory
   *   The app cache factory.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface $organizationController
   *   The organization controller.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, SDKConnectorInterface $sdkConnector, AppCacheByOwnerFactoryInterface $appCacheFactory, EventDispatcherInterface $eventDispatcher, OrganizationControllerInterface $organizationController) {
    $this->entityTypeManager = $entityTypeManager;
    $this->sdkConnector = $sdkConnector;
    $this->appCacheFactory = $appCacheFactory;
    $this->eventDispatcher = $eventDispatcher;
    $this->organizationController = $organizationController;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      AppCredentialAddApiProductEvent::EVENT_NAME => ['handleAppProductAdd'],
    ];
  }

  /**
   * Overrides AppGroup scopes if necessary.
   *
   * @param \Drupal\apigee_edge\Event\AppCredentialAddApiProductEvent $event
   *   The event.
   */
  public function handleAppProductAdd(AppCredentialAddApiProductEvent $event): void {

    if ($event->getAppType() !== 'team') {
      return;
    }

    // If the organization is not an Apigee X organization, do nothing.
    if (!$this->organizationController->isOrganizationApigeeX()) {
      return;
    }

    // Add scope only if it exist.
    $originalScopes = $event->getOriginalScopes();
    if ($originalScopes === []) {
      return;
    }

    $credential = $event->getCredential();
    $client = $this->sdkConnector->getClient();
    $organization = $this->sdkConnector->getOrganization();
    $controller = new AppGroupAppCredentialController($organization, $event->getOwnerId(), $event->getAppName(), $client);
    $controller->overrideAppGroupScopes($credential->getConsumerKey(), $originalScopes);
  }

}