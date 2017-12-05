<?php

namespace Drupal\Tests\apigee_edge\Functional;

use Apigee\Edge\Api\Management\Entity\Developer;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * User synchronization tests
 *
 * @group ApigeeEdge
 */
class UserSyncTest extends BrowserTestBase {

  /**
   * @var \Apigee\Edge\Api\Management\Controller\DeveloperControllerInterface
   */
  protected $developerController;

  public static $modules = [
    'block',
    'apigee_edge',
  ];

  protected $edgeDevelopers = [
    ['email' => 'test00@example.com', 'userName' => 'test00', 'firstName' => 'Test00', 'lastName' => 'User00'],
    ['email' => 'test01@example.com', 'userName' => 'test01', 'firstName' => 'Test01', 'lastName' => 'User01'],
    ['email' => 'test02@example.com', 'userName' => 'test02', 'firstName' => 'Test02', 'lastName' => 'User02'],
    ['email' => 'test03@example.com', 'userName' => 'test03', 'firstName' => 'Test03', 'lastName' => 'User03'],
    ['email' => 'test04@example.com', 'userName' => 'test04', 'firstName' => 'Test04', 'lastName' => 'User04'],
    ['email' => 'test05@example.com', 'userName' => 'test05', 'firstName' => 'Test05', 'lastName' => 'User05'],
    ['email' => 'test06@example.com', 'userName' => 'test06', 'firstName' => 'Test06', 'lastName' => 'User06'],
    ['email' => 'test07@example.com', 'userName' => 'test07', 'firstName' => 'Test07', 'lastName' => 'User07'],
    ['email' => 'test08@example.com', 'userName' => 'test08', 'firstName' => 'Test08', 'lastName' => 'User08'],
    ['email' => 'test09@example.com', 'userName' => 'test09', 'firstName' => 'Test09', 'lastName' => 'User09'],
    ['email' => 'test10@example.com', 'userName' => 'test10', 'firstName' => 'Test10', 'lastName' => 'User10'],
    ['email' => 'test11@example.com', 'userName' => 'test11', 'firstName' => 'Test11', 'lastName' => 'User11'],
    ['email' => 'test12@example.com', 'userName' => 'test12', 'firstName' => 'Test12', 'lastName' => 'User12'],
    ['email' => 'test13@example.com', 'userName' => 'test13', 'firstName' => 'Test13', 'lastName' => 'User13'],
    ['email' => 'test14@example.com', 'userName' => 'test14', 'firstName' => 'Test14', 'lastName' => 'User14'],
    ['email' => 'test15@example.com', 'userName' => 'test15', 'firstName' => 'Test15', 'lastName' => 'User15'],
    ['email' => 'test16@example.com', 'userName' => 'test16', 'firstName' => 'Test16', 'lastName' => 'User16'],
    ['email' => 'test17@example.com', 'userName' => 'test17', 'firstName' => 'Test17', 'lastName' => 'User17'],
    ['email' => 'test18@example.com', 'userName' => 'test18', 'firstName' => 'Test18', 'lastName' => 'User18'],
    ['email' => 'test19@example.com', 'userName' => 'test19', 'firstName' => 'Test19', 'lastName' => 'User19'],
    ['email' => 'test20@example.com', 'userName' => 'test20', 'firstName' => 'Test20', 'lastName' => 'User20'],
    ['email' => 'test21@example.com', 'userName' => 'test21', 'firstName' => 'Test21', 'lastName' => 'User21'],
    ['email' => 'test22@example.com', 'userName' => 'test22', 'firstName' => 'Test22', 'lastName' => 'User22'],
    ['email' => 'test23@example.com', 'userName' => 'test23', 'firstName' => 'Test23', 'lastName' => 'User23'],
    ['email' => 'test24@example.com', 'userName' => 'test24', 'firstName' => 'Test24', 'lastName' => 'User24'],
  ];

  /**
   * @var User[]
   */
  protected $drupalUsers = [];

  protected function setUp() {
    parent::setUp();

    $this->drupalPlaceBlock('local_actions_block');

    $this->developerController = \Drupal::service('apigee_edge.sdk_connector')->getDeveloperController();

    foreach ($this->edgeDevelopers as $edgeDeveloper) {
      $this->developerController->create(new Developer($edgeDeveloper));
    }

    for ($i = 0; $i < 20; $i++) {
      $this->drupalUsers[] = $this->createUserWithFields();
    }

    $this->drupalLogin($this->rootUser);
  }

  protected function createUserWithFields($status = TRUE) : ?User {
    $edit = [
      'first_name' => $this->randomMachineName(4),
      'last_name' => $this->randomMachineName(5),
      'name' => $this->randomMachineName(),
      'pass' => user_password(),
      'status' => $status,
      'roles' => [Role::AUTHENTICATED_ID],
    ];
    $edit['mail'] = "{$edit['name']}@example.com";

    $account = User::create($edit);
    $account->save();

    $this->assertTrue($account->id(), 'User created.');
    if (!$account->id()) {
      return NULL;
    }

    return $account;
  }

  protected function tearDown() {
    $ids = $this->developerController->getEntityIds();
    foreach ($ids as $id) {
      $this->developerController->delete($id);
    }
    parent::tearDown();
  }

  public function testUserSync() {
    $this->drupalGet('/admin/config/apigee_edge');
    $this->clickLinkProperly(t('Run user sync'));
    $this->assertSession()->pageTextContains(t('Users are in sync with Edge.'));

    foreach ($this->edgeDevelopers as $edgeDeveloper) {
      /** @var User $account */
      $account = user_load_by_mail($edgeDeveloper['email']);
      $this->assertNotEmpty($account, 'Account found');
      $this->assertEquals($edgeDeveloper['userName'], $account->getAccountName());
      $this->assertEquals($edgeDeveloper['firstName'], $account->get('first_name')->value);
      $this->assertEquals($edgeDeveloper['lastName'], $account->get('last_name')->value);
    }

    foreach ($this->drupalUsers as $drupalUser) {
      /** @var Developer $dev */
      $dev = $this->developerController->load($drupalUser->getEmail());
      $this->assertNotEmpty($dev, 'Developer found on edge.');
      $this->assertEquals($drupalUser->getAccountName(), $dev->getUserName());
      $this->assertEquals($drupalUser->get('first_name')->value, $dev->getFirstName());
      $this->assertEquals($drupalUser->get('last_name')->value, $dev->getLastName());
    }
  }

  /**
   * Implements link clicking properly.
   *
   * The clickLink() function uses Mink, not drupalGet(). This means that
   * certain features (like chekcing for meta refresh) are not working at all.
   * This is a problem, because batch api works with meta refresh when JS is not
   * available.
   *
   * @param string $name
   */
  protected function clickLinkProperly($name) {
    /** @var \Behat\Mink\Element\NodeElement[] $links */
    $links = $this->getSession()->getPage()->findAll('named', ['link', $name]);
    $href = $links[0]->getAttribute('href');
    $parts = parse_url($href);
    $query = [];
    parse_str($parts['query'], $query);

    $this->drupalGet($parts['path'], [
      'query' => $query,
    ]);
  }

}
