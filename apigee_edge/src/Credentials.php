<?php

namespace Drupal\apigee_edge;

/**
 * The API credentials.
 */
class Credentials implements CredentialsInterface {

  /**
   * The API base URL.
   *
   * @var string
   */
  protected $baseURL;

  /**
   * The API username.
   *
   * @var string
   */
  protected $username;

  /**
   * The API password.
   *
   * @var string
   */
  protected $password;

  /**
   * {@inheritdoc}
   */
  public function getBaseURL(): string {
    return $this->baseURL;
  }

  /**
   * {@inheritdoc}
   */
  public function getUsername(): string {
    return $this->username;
  }

  /**
   * {@inheritdoc}
   */
  public function getPassword(): string {
    return $this->password;
  }

  /**
   * {@inheritdoc}
   */
  public function setBaseURL(string $baseURL) {
    $this->baseURL = $baseURL;
  }

  /**
   * {@inheritdoc}
   */
  public function setUsername(string $username) {
    $this->username = $username;
  }

  /**
   * {@inheritdoc}
   */
  public function setPassword(string $password) {
    $this->password = $password;
  }
}
