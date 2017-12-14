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
   * The name of the organization.
   *
   * @var string
   */
  protected $organization;

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
  public function getBaseUrl(): string {
    return $this->baseURL;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganization(): string {
    return $this->organization;
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
  public function setBaseUrl(string $baseURL) {
    $this->baseURL = $baseURL;
  }

  /**
   * {@inheritdoc}
   */
  public function setOrganization(string $organization) {
    $this->organization = $organization;
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

  /**
   * {@inheritdoc}
   */
  public function empty(): bool {
    return !$this->baseURL || !$this->organization || !$this->username || !$this->password;
  }

}
