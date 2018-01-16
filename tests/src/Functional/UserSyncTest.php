<?php

namespace Drupal\Tests\apigee_edge\Functional;

use Drupal\apigee_edge\Entity\Developer;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * User synchronization tests
 *
 * @group ApigeeEdge
 */
class UserSyncTest extends BrowserTestBase {

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
  ];

  /**
   * @var User[]
   */
  protected $drupalUsers = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalPlaceBlock('local_actions_block');

    foreach ($this->edgeDevelopers as $edgeDeveloper) {
      Developer::create($edgeDeveloper)->save();
    }

    for ($i = 0; $i < 5; $i++) {
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

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    $remote_ids = array_map(function($record): string {
      return $record['email'];
    }, $this->edgeDevelopers);
    $drupal_emails = array_map(function(UserInterface $user): string {
      return $user->getEmail();
    }, $this->drupalUsers);
    $ids = array_merge($remote_ids, $drupal_emails);
    foreach ($ids as $id) {
      Developer::load($id)->delete();
    }
    parent::tearDown();
  }

  public function testUserSync() {
    $this->drupalGet('/admin/config/apigee-edge');
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
      $dev = Developer::load($drupalUser->getEmail());
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
