<?php

namespace Drupal\apigee_edge;

use Drupal\apigee_edge\Annotation\AuthenticationMethod;
use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides an authentication method plugin manager.
 *
 * @see \Drupal\apigee_edge\Annotation\AuthetnicationMethod
 * @see \Drupal\apigee_edge\AuthetnicationMethodPluginInterface
 * @see plugin_api
 */
class AuthenticationMethodManager extends DefaultPluginManager {

  /**
   * Constructs a AuthenticationMethodManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/AuthenticationMethod',
      $namespaces,
      $module_handler,
      AuthenticationMethodPluginInterface::class,
      AuthenticationMethod::class
    );

    $this->alterInfo('authetnication_method_info');
    $this->setCacheBackend($cache_backend, 'authentication_method');
    $this->factory = new DefaultFactory($this->getDiscovery());
  }

}
