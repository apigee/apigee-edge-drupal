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

namespace Drupal\Tests\apigee_edge\Functional;

use Drupal\apigee_edge\Entity\Developer;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * User synchronization test.
 *
 * @group apigee_edge
 */
class UserSyncTest extends ApigeeEdgeFunctionalTestBase {

  public static $modules = [
    'block',
    'apigee_edge',
  ];

  protected $edgeDevelopers = [
    [
      'email' => 'test00@example.com',
      'userName' => 'test00',
      'firstName' => 'Test00',
      'lastName' => 'User00',
    ],
    [
      'email' => 'test01@example.com',
      'userName' => 'test01',
      'firstName' => 'Test01',
      'lastName' => 'User01',
    ],
    [
      'email' => 'test02@example.com',
      'userName' => 'test02',
      'firstName' => 'Test02',
      'lastName' => 'User02',
    ],
    [
      'email' => 'test03@example.com',
      'userName' => 'test03',
      'firstName' => 'Test03',
      'lastName' => 'User03',
    ],
    [
      'email' => 'test04@example.com',
      'userName' => 'test04',
      'firstName' => 'Test04',
      'lastName' => 'User04',
    ],
  ];

  /**
   * Random property prefix.
   *
   * @var string
   */
  protected $prefix;

  /**
   * Array of Drupal users.
   *
   * @var \Drupal\user\Entity\UserInterface[]
   */
  protected $drupalUsers = [];

  /**
   * Email filter.
   *
   * @var string
   */
  protected $filter;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->prefix = $this->randomMachineName();

    $config = \Drupal::configFactory()->getEditable('apigee_edge.sync');
    $escaped_prefix = preg_quote($this->prefix);
    $this->filter = "/^{$escaped_prefix}\.[a-zA-Z0-9]*@example\.com$/";
    $config->set('filter', $this->filter);
    $config->save();

    foreach ($this->edgeDevelopers as &$edgeDeveloper) {
      $edgeDeveloper['email'] = "{$this->prefix}.{$edgeDeveloper['email']}";
      Developer::create($edgeDeveloper)->save();
    }

    for ($i = 0; $i < 5; $i++) {
      $this->drupalUsers[] = $this->createAccount([], TRUE, $this->prefix);
    }

    $this->drupalLogin($this->rootUser);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    $remote_ids = array_map(function ($record): string {
      return $record['email'];
    }, $this->edgeDevelopers);
    $drupal_emails = array_map(function (UserInterface $user): string {
      return $user->getEmail();
    }, $this->drupalUsers);
    $ids = array_merge($remote_ids, $drupal_emails);
    foreach ($ids as $id) {
      Developer::load($id)->delete();
    }
    parent::tearDown();
  }

  /**
   * Verifies that the Drupal users and the Edge developers are synchronized.
   */
  protected function verify() {
    $all_users = [];
    /** @var \Drupal\user\Entity\UserInterface $account */
    foreach (User::loadMultiple() as $account) {
      $email = $account->getEmail();
      if ($email && $email !== 'admin@example.com') {
        $this->assertTrue($this->filter ? (bool) preg_match($this->filter, $email) : TRUE, "Email ({$email}) is filtered properly.");
        $all_users[$email] = $email;
      }
    }

    unset($all_users[$this->rootUser->getEmail()]);

    foreach ($this->edgeDevelopers as $edgeDeveloper) {
      /** @var \Drupal\user\Entity\User $account */
      $account = user_load_by_mail($edgeDeveloper['email']);
      $this->assertNotEmpty($account, 'Account found: ' . $edgeDeveloper['email']);
      $this->assertEquals($edgeDeveloper['userName'], $account->getAccountName());
      $this->assertEquals($edgeDeveloper['firstName'], $account->get('first_name')->value);
      $this->assertEquals($edgeDeveloper['lastName'], $account->get('last_name')->value);

      unset($all_users[$edgeDeveloper['email']]);
    }

    foreach ($this->drupalUsers as $drupalUser) {
      /** @var \Drupal\apigee_edge\Entity\Developer $dev */
      $dev = Developer::load($drupalUser->getEmail());
      $this->assertNotEmpty($dev, 'Developer found on edge.');
      $this->assertEquals($drupalUser->getAccountName(), $dev->getUserName());
      $this->assertEquals($drupalUser->get('first_name')->value, $dev->getFirstName());
      $this->assertEquals($drupalUser->get('last_name')->value, $dev->getLastName());

      unset($all_users[$drupalUser->getEmail()]);
    }

    $this->assertEquals([], $all_users, 'Only the necessary users were synced. ' . implode(', ', $all_users));
  }

  /**
   * Tests Drupal user synchronization.
   */
  public function testUserSync() {
    $this->drupalGet('/admin/config/apigee-edge/settings');
    $this->clickLinkProperly(t('Now'));
    $this->assertSession()->pageTextContains(t('Users are in sync with Edge.'));
    $this->verify();
  }

  /**
   * Tests scheduled Drupal user synchronization.
   */
  public function testUserAsync() {
    $this->drupalGet('/admin/config/apigee-edge/settings');
    $this->clickLinkProperly(t('Background...'));
    $this->assertSession()->pageTextContains(t('User synchronization is scheduled.'));
    /** @var \Drupal\Core\Queue\QueueFactory $queue_service */
    $queue_service = \Drupal::service('queue');
    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = $queue_service->get('apigee_edge_job');
    /** @var \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_worker_manager */
    $queue_worker_manager = \Drupal::service('plugin.manager.queue_worker');
    /** @var \Drupal\Core\Queue\QueueWorkerInterface $worker */
    $worker = $queue_worker_manager->createInstance('apigee_edge_job');
    while (($item = $queue->claimItem())) {
      $worker->processItem($item->data);
      $queue->deleteItem($item);
    }
    $this->verify();
  }

  /**
   * Tests the Drupal user synchronization started from the CLI.
   */
  public function testCliUserSync() {
    $cli_service = $this->container->get('apigee_edge.cli');
    $input = new ArgvInput();
    $output = new BufferedOutput();

    $cli_service->sync(new SymfonyStyle($input, $output), 't');

    $printed_output = $output->fetch();

    foreach ($this->edgeDevelopers as $edge_developer) {
      $this->assertContains($edge_developer['email'], $printed_output);
    }

    $this->verify();
  }

}
