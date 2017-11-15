<?php

namespace Drupal\apigee_edge;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Http\Message\Authentication;

/**
 * Defines an interface for authentication method plugins.
 */
interface AuthenticationMethodPluginInterface extends PluginInspectionInterface {

  /**
   * Returns the ID of the authentication method plugin.
   *
   * @return string
   *  The ID of the authentication method plugin.
   */
  public function getId() : string;

  /**
   * Returns the name of the authentication method plugin.
   *
   * @return string
   *  The name of the authentication method plugin.
   */
  public function getName() : string;

  /**
   * Creates an authentication object.
   *
   * @param \Drupal\apigee_edge\CredentialsInterface $credentials
   *  An object that implements \Drupal\apigee_edge\CredentialsInterface
   *  which contains the API credentials.
   *
   * @return Authentication
   *  An object that implements \Http\Message\Authentication\Authentication.
   */
  public function createAuthenticationObject(CredentialsInterface $credentials) : Authentication;
}
