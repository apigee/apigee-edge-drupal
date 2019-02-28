<?php
// @codingStandardsIgnoreFile

/**
 * This file was generated via php core/scripts/generate-proxy-class.php 'Drupal\apigee_edge_teams\TeamPermissionHandler' "web/modules/contrib/apigee_edge/modules/apigee_edge_teams/src/".
 */

namespace Drupal\apigee_edge_teams\ProxyClass {

  use Drupal\apigee_edge_teams\Entity\TeamInterface;
  use Drupal\apigee_edge_teams\TeamPermissionHandlerInterface;
  use Drupal\Core\Session\AccountInterface;
  use Drupal\Core\StringTranslation\TranslationInterface;
  use Symfony\Component\DependencyInjection\ContainerInterface;

  /**
     * Provides a proxy class for \Drupal\apigee_edge_teams\TeamPermissionHandler.
     *
     * @see  \Drupal\Core\ProxyBuilder\ProxyBuilder
     */
    class TeamPermissionHandler implements TeamPermissionHandlerInterface
    {

        use \Drupal\Core\DependencyInjection\DependencySerializationTrait;

        /**
         * The id of the original proxied service.
         *
         * @var string
         */
        protected $drupalProxyOriginalServiceId;

        /**
         * The real proxied service, after it was lazy loaded.
         *
         * @var \Drupal\apigee_edge_teams\TeamPermissionHandler
         */
        protected $service;

        /**
         * The service container.
         *
         * @var \Symfony\Component\DependencyInjection\ContainerInterface
         */
        protected $container;

        /**
         * Constructs a ProxyClass Drupal proxy object.
         *
         * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
         *   The container.
         * @param string $drupal_proxy_original_service_id
         *   The service ID of the original service.
         */
        public function __construct(ContainerInterface $container, $drupal_proxy_original_service_id)
        {
            $this->container = $container;
            $this->drupalProxyOriginalServiceId = $drupal_proxy_original_service_id;
        }

        /**
         * Lazy loads the real service from the container.
         *
         * @return object
         *   Returns the constructed real service.
         */
        protected function lazyLoadItself()
        {
            if (!isset($this->service)) {
                $this->service = $this->container->get($this->drupalProxyOriginalServiceId);
            }

            return $this->service;
        }

        /**
         * {@inheritdoc}
         */
      public function getPermissions(): array {
            return $this->lazyLoadItself()->getPermissions();
        }

        /**
         * {@inheritdoc}
         */
        public function getDeveloperPermissionsByTeam(TeamInterface $team, AccountInterface $account): array
        {
            return $this->lazyLoadItself()->getDeveloperPermissionsByTeam($team, $account);
        }

        /**
         * {@inheritdoc}
         */
        public function setStringTranslation(TranslationInterface $translation)
        {
            return $this->lazyLoadItself()->setStringTranslation($translation);
        }

    }

}
