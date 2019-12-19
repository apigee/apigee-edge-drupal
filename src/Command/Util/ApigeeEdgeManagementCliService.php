<?php

/**
 * Copyright 2019 Google Inc.
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

namespace Drupal\apigee_edge\Command\Util;

use Drupal\Component\Utility\UrlHelper;
use GuzzleHttp\ClientInterface;
use Apigee\Edge\ClientInterface as ApigeeClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\TransferException;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * Defines an interface for Edge connection classes.
 */
class ApigeeEdgeManagementCliService implements ApigeeEdgeManagementCliServiceInterface {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Constructs a ApigeeEdgeManagementCliService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public function createEdgeRoleForDrupal(StyleInterface $io,
                                          callable $t,
                                          string $org,
                                          string $email,
                                          string $password,
                                          ?string $base_url,
                                          ?string $role_name,
                                          bool $force) {

    // Set default base URL if var is null or empty string.
    if (empty($base_url)) {
      $base_url = ApigeeClientInterface::DEFAULT_ENDPOINT;
    }
    else {
      // Validate it is a valid URL.
      if (!UrlHelper::isValid($base_url, TRUE)) {
        $io->error($t('Base URL is not valid.'));
        return;
      }
    }

    // Set default if null or empty string.
    $role_name = $role_name ?: self::DEFAULT_ROLE_NAME;

    if (!$this->isValidEdgeCredentials($io, $t, $org, $email, $password, $base_url)) {
      return;
    }

    $does_role_exist = $this->doesRoleExist($org, $email, $password, $base_url, $role_name);

    // If role does not exist and force flag is not used, throw error.
    if ($does_role_exist && !$force) {
      $io->error('Role ' . $role_name . ' already exists.');
      $io->note('Run with --force option to set default permissions on this role.');
      return;
    }

    // Create the role if it does not exist.
    if (!$does_role_exist) {
      $io->text($t('Role :role does not exist. Creating role.', [':role' => $role_name]));

      $url = "{$base_url}/o/{$org}/userroles";
      try {
        $this->httpClient->post($url, [
          'body' => json_encode([
            'role' => [$role_name],
          ]),
          'auth' => [$email, $password],
          'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
          ],
        ]);
      }
      catch (TransferException $exception) {
        $this->handleHttpClientExceptions($exception, $io, $t, $url, $org, $email);
        return;
      }
    }

    $this->setDefaultPermissions($io, $t, $org, $email, $password, $base_url, $role_name);

    $io->success($t('Role :role is configured. Log into apigee.com to assign a user to this role.', [':role' => $role_name]));
  }

  /**
   * Set default permissions for a role used for Drupal portal connections.
   *
   * @param \Symfony\Component\Console\Style\StyleInterface $io
   *   The IO interface of the CLI tool calling the method.
   * @param callable $t
   *   The translation function akin to t().
   * @param string $org
   *   The Edge org to create the permissions in.
   * @param string $email
   *   The email of an Edge user with org admin role to make Edge API calls.
   * @param string $password
   *   The password of an Edge user email to make Edge API calls.
   * @param string $base_url
   *   The base url of the Edge API.
   * @param string $role_name
   *   The role name to add the permissions to.
   */
  protected function setDefaultPermissions(StyleInterface $io, callable $t, string $org, string $email, string $password, string $base_url, string $role_name) {
    $io->text('Setting permissions on role ' . $role_name . '.');

    $permissions = [
      // GET access by default for all resources.
      '/' => ['get'],
      // Read only access to environments for analytics.
      '/environments/' => ['get'],
      '/environments/*/stats/*' => ['get'],
      // We do not need to update/edit roles, just read them.
      '/userroles' => ['get'],
      // No need to create API products, only read and edit.
      '/apiproducts' => ['get', 'put'],
      // Full CRUD for developers.
      '/developers' => ['get', 'put', 'delete'],
      // Full CRUD for developer's apps.
      '/developers/*/apps' => ['get', 'put', 'delete'],
      '/developers/*/apps/*' => ['get', 'put', 'delete'],
      // Full CRUD for companies.
      '/companies' => ['get', 'put'],
      '/companies/*' => ['get', 'put', 'delete'],
      // Full CRUD for company apps.
      '/companies/*/apps' => ['get', 'put'],
      '/companies/*/apps/*' => ['get', 'put', 'delete'],
    ];

    // Resource URL for modifying permissions.
    $url = $base_url . '/o/' . $org . '/userroles/' . $role_name . '/permissions';
    try {
      foreach ($permissions as $path => $permission_verbs) {
        $body = json_encode([
          'path' => $path,
          'permissions' => $permission_verbs,
        ]);
        $io->text($path . ' -> ' . implode(',', $permission_verbs));
        $this->httpClient->post($url, [
          'body' => $body,
          'auth' => [$email, $password],
          'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
          ],
        ]);
      }
    }
    catch (TransferException $exception) {
      $this->handleHttpClientExceptions($exception, $io, $t, $url, $org, $email);
      return;
    }
  }

  /**
   * Check to see if role exists.
   *
   * @param string $org
   *   The Edge org to create the permissions in.
   * @param string $email
   *   The email of an Edge user with org admin role to make Edge API calls.
   * @param string $password
   *   The password of an Edge user with org admin role to make Edge API calls.
   * @param string $base_url
   *   The base url of the Edge API.
   * @param string $role_name
   *   The role name to add the permissions to.
   *
   * @return bool
   *   Returns true if the role exists, or false if it doesn't.
   *
   * @throws \GuzzleHttp\Exception\TransferException
   */
  public function doesRoleExist(string $org, string $email, string $password, string $base_url, string $role_name) {
    $url = $base_url . '/o/' . $org . '/userroles/' . $role_name;
    try {
      $response = $this->httpClient->get($url, [
        'auth' => [$email, $password],
        'headers' => ['Accept' => 'application/json'],
      ]);
    }
    catch (ClientException $exception) {
      if ($exception->getCode() == 404) {
        // Role does not exist.
        return FALSE;
      }
      // Any other response was an exception.
      throw $exception;
    }

    // Make sure role exists.
    $body = json_decode((string) $response->getBody());
    if (isset($body->name) && $body->name == $role_name) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Validate the Apigee Edge org connection settings.
   *
   * @param \Symfony\Component\Console\Style\StyleInterface $io
   *   The IO interface of the CLI tool calling the method.
   * @param callable $t
   *   The translation function akin to t().
   * @param string $org
   *   The Edge org to connect to.
   * @param string $email
   *   The email of an Edge user with org admin role to make Edge API calls.
   * @param string $password
   *   The password of an Edge user email to make Edge API calls.
   * @param string $base_url
   *   The base url of the Edge API.
   *
   * @return bool
   *   Return true if the Edge API can be called, false if it cannot.
   */
  public function isValidEdgeCredentials(StyleInterface $io, callable $t, string $org, string $email, string $password, string $base_url) {
    $url = $base_url . '/o/' . $org;
    try {
      $response = $this->httpClient->get($url, [
        'auth' => [$email, $password],
        'headers' => ['Accept' => 'application/json'],
      ]);
    }
    catch (TransferException $exception) {
      $this->handleHttpClientExceptions($exception, $io, $t, $url, $org, $email);
      return FALSE;
    }

    // Make sure a response is returned.
    $raw_body = (string) $response->getBody();
    if (empty($raw_body)) {
      $io->error($t('Response to :url returned empty. HTTP !response_code !response_reason', [
        ':url' => $url,
        '!response_code' => $response->getStatusCode(),
        '!response_reason' => json_last_error_msg(),
      ]));
      return FALSE;
    }
    $body = json_decode($raw_body);

    if (JSON_ERROR_NONE !== json_last_error()) {
      $io->error($t('Unable to parse response from GET :url into JSON: !error ', [
        ':url' => $url,
        '!error' => json_last_error_msg(),
      ]));
      return FALSE;
    }
    if (!isset($body->name)) {
      $io->error($t('The response from GET :url did not contain valid org data.', [':url' => $url]));
      return FALSE;
    }
    else {
      $io->success($t('Connected to Edge org :org.', [':org' => $body->name]));
    }
    return TRUE;
  }

  /**
   * Print out helpful information to user running command when error happens.
   *
   * @param \GuzzleHttp\Exception\TransferException $exception
   *   The exception thrown.
   * @param \Symfony\Component\Console\Style\StyleInterface $io
   *   The IO interface of the CLI tool calling the method.
   * @param callable $t
   *   The translation function akin to t().
   * @param string $url
   *   The url being connected to.
   * @param string $org
   *   The organization to connect to.
   * @param string $email
   *   The email of an Edge user with org admin role to make Edge API calls.
   */
  public function handleHttpClientExceptions(TransferException $exception, StyleInterface $io, callable $t, string $url, string $org, string $email): void {
    // Display error message.
    $io->error($t('Error connecting to Apigee Edge. :exception_message', [':exception_message' => $exception->getMessage()]));

    // Add a note to common situations on what could be wrong.
    switch ($exception->getCode()) {
      case 0:
        $io->note($t('Your system may not be able to connect to :url.', [
          ':url' => $url,
        ]));
        return;

      case 401:
        $io->note($t('Your username or password is invalid.'));
        return;

      case 403:
        $io->note($t('User :email may not have the orgadmin role for Apigee Edge org :org.', [
          ':email' => $email,
          ':org' => $org,
        ]));
        return;

      case 302:
        $io->note($t('Edge endpoint gives a redirect response, is the url :url does not seem to be a valid Apigee Edge endpoint.', [':url' => $url]));
        return;
    }
  }

}
