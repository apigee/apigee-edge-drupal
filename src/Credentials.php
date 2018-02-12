<?php
/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge;

/**
 * The API credentials.
 */
class Credentials implements CredentialsInterface {

  public const ENTERPRISE_ENDPOINT = 'https://api.enterprise.apigee.com/v1';

  /**
   * The Edge API endpoint.
   *
   * @var string
   */
  protected $endpoint;

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
   * Credentials constructor.
   */
  public function __construct() {
    $this->endpoint = self::ENTERPRISE_ENDPOINT;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoint(): string {
    return $this->endpoint;
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
  public function setEndpoint(string $endpoint) {
    // Automatically fall-back to the enterprise endpoint if empty endpoint is
    // passed.
    $this->endpoint = $endpoint ?: self::ENTERPRISE_ENDPOINT;
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
    return !$this->endpoint || !$this->organization || !$this->username || !$this->password;
  }

}
