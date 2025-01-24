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

namespace Drupal\Tests\apigee_edge\Traits;

use Apigee\Edge\Api\Management\Controller\DeveloperAppCredentialController as EdgeDeveloperAppCredentialController;
use Drupal\Core\Utility\Error;
use Drupal\Tests\apigee_mock_api_client\Traits\ApigeeMockApiClientHelperTrait;
use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Provides common functionality for the Apigee Edge test classes.
 *
 * @todo Move methods from this trait that can be also used in kernel tests to a
 * new trait, ex.: getRandomUniqueId(), logException(), etc.
 */
trait ApigeeEdgeFunctionalTestTrait {

  use ApigeeMockApiClientHelperTrait;

  /**
   * Use the mock_api_client or not.
   *
   * Meant to be overriden by extending classes when they switch to use the new
   * apigee_mock_api_client.
   *
   * @var bool
   */
  protected static $mock_api_client_ready = FALSE;

  /**
   * Initializes test environment with required configuration.
   */
  protected function initTestEnv(): void {
    if (static::$mock_api_client_ready) {
      $this->installExtraModules(['apigee_mock_api_client']);
      $this->apigeeTestHelperSetup();
    }
    else {
      $this->installExtraModules(['apigee_edge_test']);
      $this->createTestKey();
      $this->restoreKey();
    }
  }

  /**
   * The corresponding developer will be created if a Drupal user is saved.
   */
  protected function enableUserPresave() {
    _apigee_edge_set_sync_in_progress(FALSE);
  }

  /**
   * The corresponding developer will not be created if a Drupal user is saved.
   */
  protected function disableUserPresave() {
    _apigee_edge_set_sync_in_progress(TRUE);
  }

  /**
   * Creates a Drupal account.
   *
   * @param array $permissions
   *   Permissions to add.
   * @param bool $status
   *   Status of the Drupal account.
   * @param string $prefix
   *   Prefix of the Drupal account's email.
   *
   * @return \Drupal\user\UserInterface
   *   Drupal user.
   */
  protected function createAccount(array $permissions = [], bool $status = TRUE, string $prefix = ''): ?UserInterface {
    $rid = NULL;
    if ($permissions) {
      $rid = $this->createRole($permissions);
      static::assertNotFalse($rid, 'Role created');
    }

    $edit = [
      'first_name' => $this->randomMachineName(),
      'last_name' => $this->randomMachineName(),
      'name' => strtolower($this->randomMachineName()),
      'pass' => \Drupal::service('password_generator')->generate(),
      'status' => $status,
    ];
    if ($rid) {
      $edit['roles'][] = $rid;
    }
    if ($prefix) {
      $edit['mail'] = "{$prefix}.{$edit['name']}@example.com";
    }
    else {
      $edit['mail'] = "{$edit['name']}@example.com";
    }

    $account = User::create($edit);
    $account->save();

    static::assertNotFalse($account->id(), 'User created.');
    if (!$account->id()) {
      return NULL;
    }

    // This is here to make drupalLogin() work.
    $account->passRaw = $edit['pass'];

    return $account;
  }

  /**
   * Creates a product.
   *
   * @return \Drupal\apigee_edge\Entity\ApiProduct
   *   (SDK) API product object.
   */
  protected function createProduct(): ApiProduct {
    /** @var \Drupal\apigee_edge\Entity\ApiProduct $product */
    $product = ApiProduct::create([
      'name' => $this->randomMachineName(),
      'displayName' => $this->getRandomGenerator()->word(16),
      'approvalType' => ApiProduct::APPROVAL_TYPE_AUTO,
    ]);
    $product->save();

    return $product;
  }

  /**
   * Creates an app for a user.
   *
   * @param array $data
   *   App data. (developerId gets overridden by $owner's developerId.)
   * @param \Drupal\user\UserInterface $owner
   *   Owner of the app.
   * @param array $products
   *   List of associated API products.
   *
   * @return \Drupal\apigee_edge\Entity\DeveloperAppInterface
   *   The created developer app entity.
   */
  protected function createDeveloperApp(array $data, UserInterface $owner, array $products = []) {
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $app */
    $app = DeveloperApp::create($data);
    $app->setOwner($owner);
    $app->save();

    if (!empty($products)) {
      /** @var \Drupal\apigee_edge\SDKConnectorInterface $connector */
      $connector = \Drupal::service('apigee_edge.sdk_connector');
      $credentials = $app->getCredentials();
      /** @var \Apigee\Edge\Api\Management\Entity\AppCredentialInterface $credential */
      $credential = reset($credentials);
      // We use the non-cached, original SDK controller for this not its
      // Drupal version.
      $dacc = new EdgeDeveloperAppCredentialController($connector->getOrganization(), $app->getDeveloperId(), $app->getName(), $connector->getClient());
      $dacc->addProducts($credential->getConsumerKey(), $products);
    }

    return $app;
  }

  /**
   * Loads all apps for a given user.
   *
   * @param string $email
   *   Email address of a user.
   *
   * @return \Drupal\apigee_edge\Entity\DeveloperAppInterface[]|null
   *   Array of developer apps of the user or if user does not exist as
   *   developer on Apigee Edge.
   */
  protected function getApps(string $email): ?array {
    $developer = Developer::load($email);
    if ($developer) {
      /** @var \Drupal\apigee_edge\Entity\Storage\DeveloperAppStorageInterface $storage */
      $storage = \Drupal::entityTypeManager()->getStorage('developer_app');
      return $storage->loadByDeveloper($developer->uuid());
    }

    return NULL;
  }

  /**
   * The same as drupalGet(), but ignores the meta refresh.
   *
   * @param string $path
   *   Url path.
   * @param array $options
   *   Url options.
   * @param array $headers
   *   Additional http headers.
   *
   * @return string
   *   The retrieved HTML string, also available as $this->getRawContent()
   */
  protected function drupalGetNoMetaRefresh(string $path, array $options = [], array $headers = []) {
    $options['absolute'] = TRUE;
    $url = $this->buildUrl($path, $options);

    $session = $this->getSession();

    $this->prepareRequest();
    foreach ($headers as $header_name => $header_value) {
      $session->setRequestHeader($header_name, $header_value);
    }

    $session->visit($url);
    $out = $session->getPage()->getContent();

    $this->refreshVariables();

    return $out;
  }

  /**
   * Implements link clicking properly.
   *
   * The clickLink() function uses Mink, not drupalGet(). This means that
   * certain features (like checking for meta refresh) are not working at all.
   * This is a problem, because batch api works with meta refresh when JS is not
   * available.
   *
   * @param string $name
   *   Name of the link.
   */
  protected function clickLinkProperly(string $name) {
    [$path, $query] = $this->findLink($name);
    $this->drupalGet(static::fixUrl($path), [
      'query' => $query,
    ]);
  }

  /**
   * Finds a link on the current page.
   *
   * @param string $name
   *   Name of the link.
   *
   * @return array
   *   An array with two items. The first one is the path, the second one is
   *   an associative array of the query parameters.
   */
  protected function findLink(string $name): array {
    /** @var \Behat\Mink\Element\NodeElement[] $links */
    $links = $this->getSession()->getPage()->findAll('named', ['link', $name]);
    $this->assertnotEmpty($links, "Link \"{$name}\" found.");

    $href = $links[0]->getAttribute('href');
    $parts = parse_url($href);
    $query = [];
    parse_str($parts['query'], $query);

    return [$parts['path'], $query];
  }

  /**
   * Returns absolute URL starts with a slash.
   *
   * @param string $url
   *   The URL.
   *
   * @return string
   *   URL starts with a slash, if the URL is absolute.
   */
  protected static function fixUrl(string $url): string {
    if (mb_strpos($url, 'http:') === 0 || mb_strpos($url, 'https:') === 0) {
      return $url;
    }
    return (mb_strpos($url, '/') === 0) ? $url : "/{$url}";
  }

  /**
   * Log the given exception using the class short name as type.
   *
   * @param \Exception $exception
   *   Exception to log.
   * @param string $suffix
   *   Suffix for type string.
   */
  protected function logException(\Exception $exception, string $suffix = '') {
    $ro = new \ReflectionObject($this);
    $logger = \Drupal::logger("{$ro->getShortName()}{$suffix}");
    Error::logException($logger, $exception);
  }

  /**
   * Gets a random unique ID.
   */
  protected function getRandomUniqueId(): string {
    return $this->container->get('uuid')->generate();
  }

}
