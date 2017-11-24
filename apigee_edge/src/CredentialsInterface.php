<?php

namespace Drupal\apigee_edge;

/**
 * Defines an interface for credentials classes.
 */
interface CredentialsInterface {

  /**
   * Gets the API base URL.
   *
   * @return string
   *   The API base URL.
   */
  public function getBaseUrl(): string;

  /**
   * Gets the API username.
   *
   * @return string
   *   The API username.
   */
  public function getUsername(): string;

  /**
   * Gets the name of the organization.
   *
   * @return string
   *   The name of the organization.
   */
  public function getOrganization(): string;

  /**
   * Gets the API password.
   *
   * @return string
   *   The API password.
   */
  public function getPassword(): string;

  /**
   * Sets the API baseURL.
   *
   * @param string $baseURL
   *   The API base URL.
   */
  public function setBaseUrl(string $baseURL);

  /**
   * Sets the name of the organization.
   *
   * @param string $organization
   *   The name of the organization.
   */
  public function setOrganization(string $organization);

  /**
   * Sets the API username.
   *
   * @param string $username
   *   The API username.
   */
  public function setUsername(string $username);

  /**
   * Sets the API password.
   *
   * @param string $password
   *   The API password.
   */
  public function setPassword(string $password);

  /**
   * Checks whether this object is empty.
   *
   * @return bool
   */
  public function empty() : bool;

}
