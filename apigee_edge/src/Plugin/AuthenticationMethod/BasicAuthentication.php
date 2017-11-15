<?php

namespace Drupal\apigee_edge\Plugin\AuthenticationMethod;

use Drupal\apigee_edge\AuthenticationMethodPluginBase;
use Drupal\apigee_edge\CredentialsInterface;
use Http\Message\Authentication;

/**
 * Creates BasicAuth object.
 *
 * @AuthenticationMethod(
 *   id = "authentication_method_basic_auth",
 *   name = @Translation("Basic authentication"),
 * )
 */
class BasicAuthentication extends AuthenticationMethodPluginBase {

  /**
   * {@inheritdoc}
   */
  public function createAuthenticationObject(CredentialsInterface $credentials): Authentication {
    return new Authentication\BasicAuth($credentials->getUsername(), $credentials->getPassword());
  }
}
