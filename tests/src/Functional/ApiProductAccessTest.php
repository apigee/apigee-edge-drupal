<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\Tests\apigee_edge\Functional;

use Apigee\Edge\Api\Management\Entity\App;
use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\apigee_edge\Entity\ApiProductInterface;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Validates built-in access control on API products.
 *
 * @group apigee_edge
 * @group apigee_edge_access
 * @group apigee_edge_api_product
 * @group apigee_edge_api_product_access
 */
class ApiProductAccessTest extends ApigeeEdgeFunctionalTestBase {

  protected const USER_WITH_BYPASS_PERM = 'user_with_bypass_perm';
  protected const INTERNAL_ROLE = 'internal';
  protected const PUBLIC_VISIBILITY = 'public';
  protected const PRIVATE_VISIBILITY = 'private';
  protected const INTERNAL_VISIBILITY = 'internal';
  protected const VISIBILITIES = [
    self::PUBLIC_VISIBILITY,
    self::PRIVATE_VISIBILITY,
    self::INTERNAL_VISIBILITY,
  ];
  protected const SUPPORTED_OPERATIONS = ['view', 'view label', 'assign'];

  /**
   * Array of created API products.
   *
   * @var \Drupal\apigee_edge\Entity\ApiProductInterface[]
   */
  protected $apiProducts = [];

  /**
   * Array of created developer apps.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface[]
   */
  protected $developerApps = [];

  /**
   * Array of created Drupal users.
   *
   * @var \Drupal\user\UserInterface[]
   */
  protected $users = [];

  /**
   * User role storage.
   *
   * @var \Drupal\user\RoleStorageInterface
   */
  protected $roleStorage;

  /**
   * API product access control handler.
   *
   * @var \Drupal\Core\Entity\EntityAccessControlHandlerInterface
   */
  protected $accessControlHandler;

  /**
   * All possible role combinations.
   *
   * @var array
   */
  protected $ridCombinations;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->roleStorage = $this->container->get('entity_type.manager')->getStorage('user_role');
    $this->accessControlHandler = $this->container->get('entity_type.manager')->getAccessControlHandler('api_product');

    $this->users[AccountInterface::ANONYMOUS_ROLE] = User::getAnonymousUser();
    $this->users[AccountInterface::AUTHENTICATED_ROLE] = $this->createAccount();
    // We granted "Administer developer apps" permission to this role as well
    // just to be able to test the UI.
    $this->users[self::USER_WITH_BYPASS_PERM] = $this->createAccount(['bypass api product access control']);
    $this->createRole([], self::INTERNAL_ROLE, self::INTERNAL_ROLE);
    $this->users[self::INTERNAL_ROLE] = $this->createAccount([]);
    $this->users[self::INTERNAL_ROLE]->addRole(self::INTERNAL_ROLE);
    $this->users[self::INTERNAL_ROLE]->save();

    foreach (self::VISIBILITIES as $visibility) {
      /** @var \Drupal\apigee_edge\Entity\ApiProductInterface $api_product */
      $api_product = ApiProduct::create([
        'name' => $this->randomMachineName(),
        'displayName' => $this->randomMachineName() . " ({$visibility})",
        'approvalType' => ApiProduct::APPROVAL_TYPE_AUTO,
      ]);
      $api_product->setAttribute('access', $visibility);
      $api_product->save();
      $this->apiProducts[$visibility] = $api_product;
    }

    $this->ridCombinations = $this->calculateRidCombinations(array_keys($this->roleStorage->loadMultiple()));
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    /** @var \Drupal\Core\Entity\EntityInterface[] $entities */
    $entities = array_merge($this->users, $this->apiProducts);
    foreach ($entities as $entity) {
      try {
        if ($entity !== NULL) {
          $entity->delete();
        }
      }
      catch (\Exception $exception) {
        $this->logException($exception);
      }
    }

    parent::tearDown();
  }

  /**
   * Tests API product entity access.
   */
  public function testApiProductAccess() {
    $this->entityAccessTest();
    $this->developerAppEditFormTest();
  }

  /**
   * Tests "Access by visibility" access control.
   */
  protected function entityAccessTest() {
    $authenticatedRoles = array_filter(array_keys($this->roleStorage->loadMultiple()), function ($rid) {
      return $rid !== AccountInterface::ANONYMOUS_ROLE;
    });
    $visibilityCombinations = $this->calculateTestCombinations();

    // We calculated all possible combinations from roles and visibilities
    // but existence of Authenticated user role introduces redundant tests.
    $testScenarios = [];

    foreach ($visibilityCombinations as $visibilityCombination) {
      foreach ($this->ridCombinations as $ridCombination) {
        $settings = array_combine($visibilityCombination, array_fill(0, count($visibilityCombination), $ridCombination));
        // Ensure we always have these 3 keys.
        $settings += [
          self::PUBLIC_VISIBILITY => [],
          self::PRIVATE_VISIBILITY => [],
          self::INTERNAL_VISIBILITY => [],
        ];
        $this->saveAccessSettings($settings);
        // We have to clear entity access control handler's static cache because
        // otherwise access results comes from there instead of gets
        // recalculated.
        $this->accessControlHandler->resetCache();
        foreach ($this->users as $userRole => $user) {
          foreach ($this->apiProducts as $product) {
            $rolesWithAccess = $this->getRolesWithAccess($product);
            // Saved configuration designedly contains only the authenticated
            // role and not all (authenticated) roles.
            if (in_array(AccountInterface::AUTHENTICATED_ROLE, $rolesWithAccess)) {
              $rolesWithAccess = array_merge($rolesWithAccess, $authenticatedRoles);
            }
            // Eliminate redundant test scenarios caused by auth.user role.
            sort($rolesWithAccess);
            $testId = md5(sprintf('test-%s-%s-%s', $product->id(), $user->id(), implode('-', $rolesWithAccess) ?? 'empty'));
            if (array_key_exists($testId, $testScenarios)) {
              continue;
            }
            $testScenarios[$testId] = $rolesWithAccess;
            foreach (self::SUPPORTED_OPERATIONS as $operation) {
              $accessGranted = $product->access($operation, $user);
              if (in_array($userRole, $rolesWithAccess)) {
                $this->assertTrue($accessGranted, $this->messageIfUserShouldHaveAccessByRole($operation, $user, $userRole, $rolesWithAccess, $product));
              }
              elseif ($this->users[self::USER_WITH_BYPASS_PERM]->id() === $user->id()) {
                $this->assertTrue($accessGranted, $this->messageIfUserShouldHaveAccessWithBypassPerm($operation, $user));
              }
              else {
                $this->assertFalse($accessGranted, $this->messageIfUserShouldNotHaveAccess($operation, $user, $userRole, $rolesWithAccess, $product));
              }
            }
          }
        }
      }
    }
  }

  /**
   * Test for developer app/edit form.
   *
   * The testEntityAccess() has already ensured that "Access by visibility"
   * access control is working properly on API products. We just have to
   * confirm that developer app/edit forms as leveraging it properly.
   */
  protected function developerAppEditFormTest() {
    // Some utility functions that we are going to use here.
    $onlyPublicProductVisible = function () {
      $this->checkProductVisibility(
        [
          self::PUBLIC_VISIBILITY,
        ],
        [
          self::PRIVATE_VISIBILITY,
          self::INTERNAL_VISIBILITY,
        ]
      );
    };
    $allProductsVisible = function () {
      $this->checkProductVisibility(
        [
          self::PUBLIC_VISIBILITY,
          self::PRIVATE_VISIBILITY,
          self::INTERNAL_VISIBILITY,
        ]
      );
    };
    $justPublicAndPrivateVisible = function () {
      $this->checkProductVisibility(
        [
          self::PUBLIC_VISIBILITY,
          self::PRIVATE_VISIBILITY,
        ],
        [
          self::INTERNAL_VISIBILITY,
        ]
      );
    };

    // Enforce this "Access by visibility" configuration.
    $this->saveAccessSettings([
      self::PUBLIC_VISIBILITY => [AccountInterface::AUTHENTICATED_ROLE],
      self::PRIVATE_VISIBILITY => [],
      self::INTERNAL_VISIBILITY => [],
    ]);

    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $auth_user_app */
    $auth_user_app = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->users[AccountInterface::AUTHENTICATED_ROLE]->get('apigee_edge_developer_id')->value,
    ]);
    $auth_user_app->setOwner($this->users[AccountInterface::AUTHENTICATED_ROLE]);
    $auth_user_app->save();

    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $bypass_user_app */
    $bypass_user_app = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->users[self::USER_WITH_BYPASS_PERM]->get('apigee_edge_developer_id')->value,
    ]);
    $bypass_user_app->setOwner($this->users[self::USER_WITH_BYPASS_PERM]);
    $bypass_user_app->save();

    // >> Authenticated user.
    $this->drupalLogin($this->users[AccountInterface::AUTHENTICATED_ROLE]);

    // Only public API products should be visible by default on the add/edit
    // app forms for authenticated user.
    $this->drupalGet(Url::fromRoute('entity.developer_app.add_form_for_developer', [
      'user' => $this->users[AccountInterface::AUTHENTICATED_ROLE]->id(),
    ]));
    $onlyPublicProductVisible();
    $this->drupalGet(Url::fromRoute('entity.developer_app.edit_form_for_developer', [
      'user' => $this->users[AccountInterface::AUTHENTICATED_ROLE]->id(),
      'app' => $auth_user_app->getName(),
    ]));
    $onlyPublicProductVisible();
    $this->drupalLogout();

    // << Authenticated user.
    // Ensure that user can access to other's developer app add/edit form.
    /** @var \Drupal\user\RoleStorageInterface $roleStorage */
    $role = $this->createRole(['administer developer_app']);
    $this->users[self::USER_WITH_BYPASS_PERM]->addRole($role);
    $this->users[self::USER_WITH_BYPASS_PERM]->save();

    // >> Bypass user.
    $this->drupalLogin($this->users[self::USER_WITH_BYPASS_PERM]);
    // Even if a user has bypass permission it should see only those API
    // Products on an other user's add/edit form that the other user has
    // access.
    $this->drupalGet(Url::fromRoute('entity.developer_app.add_form_for_developer', [
      'user' => $this->users[AccountInterface::AUTHENTICATED_ROLE]->id(),
    ]));
    $onlyPublicProductVisible();
    $this->drupalGet(Url::fromRoute('entity.developer_app.edit_form_for_developer', [
      'user' => $this->users[AccountInterface::AUTHENTICATED_ROLE]->id(),
      'app' => $auth_user_app->getName(),
    ]));
    $onlyPublicProductVisible();

    // But on the its own add/edit app forms it should see all API products.
    $this->drupalGet(Url::fromRoute('entity.developer_app.add_form_for_developer', [
      'user' => $this->users[self::USER_WITH_BYPASS_PERM]->id(),
    ]));
    $allProductsVisible();
    $this->drupalGet(Url::fromRoute('entity.developer_app.edit_form_for_developer', [
      'user' => $this->users[self::USER_WITH_BYPASS_PERM]->id(),
      'app' => $bypass_user_app->getName(),
    ]));
    $allProductsVisible();
    $this->drupalLogout();

    // Remove extra role from the user.
    $this->users[self::USER_WITH_BYPASS_PERM]->removeRole($role);
    $this->users[self::USER_WITH_BYPASS_PERM]->save();
    // << Bypass user.
    // Add a private API Product to auth. user's app.
    /** @var \Drupal\apigee_edge\SDKConnectorInterface $connector */
    $dacc = $this->container->get('apigee_edge.controller.developer_app_credential_factory')->developerAppCredentialController($this->users[AccountInterface::AUTHENTICATED_ROLE]->get('apigee_edge_developer_id')->value, $auth_user_app->getName());
    /** @var \Apigee\Edge\Api\Management\Entity\AppCredentialInterface $credential */
    $credentials = $auth_user_app->getCredentials();
    $credential = reset($credentials);
    $dacc->addProducts($credential->getConsumerKey(), [$this->apiProducts[self::PRIVATE_VISIBILITY]->id()]);

    // >> Auth. user.
    $this->drupalLogin($this->users[AccountInterface::AUTHENTICATED_ROLE]);
    // On the add app form still only public API products should be
    // visible.
    $this->drupalGet(Url::fromRoute('entity.developer_app.add_form_for_developer', [
      'user' => $this->users[AccountInterface::AUTHENTICATED_ROLE]->id(),
    ]));
    $onlyPublicProductVisible();
    // But on the app's edit form that contains the private API product that
    // should be visible as well.
    $this->drupalGet(Url::fromRoute('entity.developer_app.edit_form_for_developer', [
      'user' => $this->users[AccountInterface::AUTHENTICATED_ROLE]->id(),
      'app' => $auth_user_app->getName(),
    ]));
    $justPublicAndPrivateVisible();
    $this->drupalLogout();
    // << Auth. user.
  }

  /**
   * Calculates all possible combinations from role ids.
   *
   * @param array $rids
   *   Array of role ids.
   *
   * @return array
   *   All possible combinations calculated from rids.
   */
  protected function calculateRidCombinations(array $rids): array {
    $ridCombinations = [[]];
    foreach ($rids as $rid) {
      foreach ($ridCombinations as $ridCombination) {
        array_push($ridCombinations, array_merge([$rid], $ridCombination));
      }
    }
    return $ridCombinations;
  }

  /**
   * Calculates test combination from roles and product visibility options.
   *
   * @return array
   *   Multidimensional array with all possible combinations.
   */
  private function calculateTestCombinations(): array {
    $ridCombinations = $this->ridCombinations;

    $visibilityCombinations = [[]];
    foreach (self::VISIBILITIES as $visibility) {
      foreach ($visibilityCombinations as $visibilityCombination) {
        array_push($visibilityCombinations, array_merge([$visibility], $visibilityCombination));
      }
    }

    // Do not test the empty matrix (roles * visibility) times.
    array_shift($ridCombinations);
    array_shift($visibilityCombinations);
    // Only test it once.
    $visibilityCombinations[] = [];

    return $visibilityCombinations;
  }

  /**
   * Saves access settings to its appreciated place.
   *
   * @param array $settings
   *   Associate array where keys are public, private, internal and values are
   *   role ids.
   */
  protected function saveAccessSettings(array $settings) {
    $this->config('apigee_edge.api_product_settings')
      ->set('access', $settings)
      ->save();
  }

  /**
   * Returns roles (role ids) with access to an API product.
   *
   * @param \Drupal\apigee_edge\Entity\ApiProductInterface $product
   *   API product.
   *
   * @return array
   *   Array of role ids.
   */
  protected function getRolesWithAccess(ApiProductInterface $product): array {
    $prodVisibility = $product->getAttributeValue('access');
    return $this->config('apigee_edge.api_product_settings')
      ->get('access')[$prodVisibility] ?? [];
  }

  /**
   * Error message, when a user should have access to an API product by role.
   *
   * @param string $operation
   *   Operation on API product.
   * @param \Drupal\user\UserInterface $user
   *   User object.
   * @param string $user_rid
   *   Currently tested role of the user.
   * @param array $rids_with_access
   *   Roles with access to the API product.
   * @param \Drupal\apigee_edge\Entity\ApiProductInterface $product
   *   API Product.
   *
   * @return string
   *   Error message.
   */
  protected function messageIfUserShouldHaveAccessByRole(string $operation, UserInterface $user, string $user_rid, array $rids_with_access, ApiProductInterface $product): string {
    return sprintf('User with "%s" role should have "%s" access to an API Product with "%s" visibility. Roles with access granted: %s.', $user_rid, $operation, ($product->getAttributeValue('access') ?? 'public'), empty($rids_with_access) ? 'none' : implode(', ', $rids_with_access));
  }

  /**
   * Error message, when a user should have access because it has bypass perm.
   *
   * @param string $operation
   *   Operation on API product.
   * @param \Drupal\user\UserInterface $user
   *   User object.
   *
   * @return string
   *   Error message.
   */
  protected function messageIfUserShouldHaveAccessWithBypassPerm(string $operation, UserInterface $user): string {
    return "User with \"Bypass API Product access control\" permission should have \"{$operation}\" access to the API product.";
  }

  /**
   * Error message, when a user should not have access to an API product.
   *
   * @param string $operation
   *   Operation on API product.
   * @param \Drupal\user\UserInterface $user
   *   User object.
   * @param string $user_rid
   *   Currently tested role of the user.
   * @param array $rids_with_access
   *   Roles with access to the API product.
   * @param \Drupal\apigee_edge\Entity\ApiProductInterface $product
   *   API Product.
   *
   * @return string
   *   Error message.
   */
  protected function messageIfUserShouldNotHaveAccess(string $operation, UserInterface $user, string $user_rid, array $rids_with_access, ApiProductInterface $product): string {
    return sprintf('"%s" user without "Bypass API Product access control" permission should not have "%s" access to an API Product with "%s" visibility. Roles with access granted: %s.', $user_rid, $operation, ($product->getAttributeValue('access') ?? 'public'), empty($rids_with_access) ? 'none' : implode(', ', $rids_with_access));
  }

  /**
   * Validates visible and hidden API products on a page.
   *
   * @param array $visible
   *   Array of API product visibilities that should be on the page.
   * @param array $hidden
   *   Array of API product visibilities that should not be on the page.
   */
  protected function checkProductVisibility(array $visible = [], array $hidden = []) {
    foreach ($visible as $visibility) {
      $this->assertSession()->pageTextContains($this->apiProducts[$visibility]->label());
    }

    foreach ($hidden as $visibility) {
      $this->assertSession()->pageTextNotContains($this->apiProducts[$visibility]->label());
    }
  }

}
