<?php

namespace Drupal\apigee_edge\Plugin\AuthenticationMethod;

use Drupal\apigee_edge\AuthenticationMethodPluginBase;
use Drupal\apigee_edge\CredentialsInterface;
use Http\Message\Authentication as AuthenticationInterface;
use Http\Message\Authentication\BasicAuth;

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
  public function createAuthenticationObject(CredentialsInterface $credentials): AuthenticationInterface {
    return new BasicAuth($credentials->getUsername(), $credentials->getPassword());
  }

}
