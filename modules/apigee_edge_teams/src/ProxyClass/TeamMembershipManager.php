<?php
// @codingStandardsIgnoreFile

/**
 * This file was generated via php core/scripts/generate-proxy-class.php 'Drupal\apigee_edge_teams\TeamMembershipManager' "web/modules/contrib/apigee_edge/modules/apigee_edge_teams/src/".
 */

namespace Drupal\apigee_edge_teams\ProxyClass {

  use Symfony\Component\DependencyInjection\ContainerInterface;

  /**
     * Provides a proxy class for \Drupal\apigee_edge_teams\TeamMembershipManager.
     *
     * @see  \Drupal\Core\ProxyBuilder\ProxyBuilder
     */
    class TeamMembershipManager implements \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
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
         * @var \Drupal\apigee_edge_teams\TeamMembershipManager
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
        public function getMembers(string $team): array
        {
            return $this->lazyLoadItself()->getMembers($team);
        }

        /**
         * {@inheritdoc}
         */
        public function addMembers(string $team, array $developers): void
        {
            $this->lazyLoadItself()->addMembers($team, $developers);
        }

        /**
         * {@inheritdoc}
         */
        public function removeMembers(string $team, array $developers): void
        {
            $this->lazyLoadItself()->removeMembers($team, $developers);
        }

        /**
         * {@inheritdoc}
         */
        public function getTeams(string $developer): array
        {
            return $this->lazyLoadItself()->getTeams($developer);
        }

    }

}
