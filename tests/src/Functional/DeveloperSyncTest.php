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

use Apigee\Edge\Api\Management\Controller\DeveloperController;
use Drupal\Core\Url;
use Drupal\Tests\field_ui\Traits\FieldUiTestTrait;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Plugin\ApigeeFieldStorageFormat\CSV;
use Drupal\apigee_edge\Plugin\ApigeeFieldStorageFormat\JSON;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\user\Entity\User;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Developer-user synchronization test.
 *
 * @group apigee_edge
 * @group apigee_edge_developer
 * @group apigee_edge_field
 */
class DeveloperSyncTest extends ApigeeEdgeFunctionalTestBase {

  use FieldUiTestTrait;

  /**
   * Number of developers to create from each type.
   *
   * Exists only in Drupal, exists only on Apigee Edge, most recent in Drupal,
   * most recent on Apigee Edge.
   */
  const DEVELOPER_TO_CREATE_PER_TYPE = 1;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'block',
    'field_ui',
  ];

  /**
   * Random property prefix.
   *
   * @var string
   */
  protected $prefix;

  /**
   * Email filter.
   *
   * @var string
   */
  protected $filter;

  /**
   * Array of Apigee Edge developers.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface[]
   */
  protected $edgeDevelopers = [];

  /**
   * Array of Drupal users.
   *
   * @var \Drupal\user\UserInterface[]
   */
  protected $drupalUsers = [];

  /**
   * Array of modified Apigee Edge developers.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface[]
   */
  protected $modifiedEdgeDevelopers = [];

  /**
   * Array of modified Drupal users.
   *
   * @var \Drupal\user\UserInterface[]
   */
  protected $modifiedDrupalUsers = [];

  /**
   * Inactive Apigee Edge developer assigned to an active Drupal user.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface
   */
  protected $inactiveDeveloper;

  /**
   * Array of Drupal user fields.
   *
   * @var array
   */
  protected $fields = [];

  /**
   * Array of Drupal user list fields.
   *
   * @var array
   */
  protected $listFields = [];

  /**
   * Field name prefix.
   *
   * @var string
   */
  protected $fieldNamePrefix;

  /**
   * The field storage format manager service.
   *
   * @var \Drupal\apigee_edge\Plugin\FieldStorageFormatManager
   */
  protected $formatManager;

  /**
   * Name of the option field.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * Admin path to manage field storage settings.
   *
   * @var string
   */
  protected $adminPath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('system_breadcrumb_block');
    $this->fieldNamePrefix = $this->config('field_ui.settings')->get('field_prefix');
    $this->formatManager = $this->container->get('plugin.manager.apigee_field_storage_format');

    $this->prefix = $this->randomMachineName();
    $escaped_prefix = preg_quote($this->prefix);
    $this->filter = "/^{$escaped_prefix}\.[a-zA-Z0-9]*@example\.com$/";
    $this->container->get('config.factory')->getEditable('apigee_edge.sync')->set('filter', $this->filter)->save();

    $this->drupalLogin($this->rootUser);
    $this->setUpUserFields();

    // Create developers on Apigee Edge.
    for ($i = 0; $i < self::DEVELOPER_TO_CREATE_PER_TYPE; $i++) {
      $mail = "{$this->prefix}.{$this->randomMachineName()}@example.com";
      $this->edgeDevelopers[$mail] = Developer::create([
        'email' => $mail,
        'userName' => $this->randomMachineName(),
        'firstName' => $this->randomMachineName(64),
        'lastName' => $this->randomMachineName(64),
      ]);
      foreach ($this->fields as $field_type => $data) {
        $formatter = $this->formatManager->lookupPluginForFieldType($field_type);
        $this->edgeDevelopers[$mail]->setAttribute($data['name'], $formatter->encode($data['data']));
      }
      foreach ($this->listFields as $field_type => $data) {
        $formatter = $this->formatManager->lookupPluginForFieldType($field_type);
        $this->edgeDevelopers[$mail]->setAttribute($data['name'], $formatter->encode($data['odata']));
      }
      $this->edgeDevelopers[$mail]->setAttribute('invalid_email', 'invalid_email_address');
      $this->edgeDevelopers[$mail]->save();
    }

    // Create users in Drupal. Do not let run apigee_edge_user_presave(), so
    // the corresponding developer won't be created.
    $this->disableUserPresave();
    for ($i = 0; $i < self::DEVELOPER_TO_CREATE_PER_TYPE; $i++) {
      $user = $this->createAccount([], TRUE, $this->prefix);
      foreach ($this->fields as $field_type => $data) {
        $user->set($this->fieldNamePrefix . $data['name'], $data['data']);
      }
      foreach ($this->listFields as $field_type => $data) {
        $user->set($this->fieldNamePrefix . $data['name'], $data['odata']);
      }
      $user->save();
      $this->drupalUsers[$user->getEmail()] = $user;
    }
    $this->enableUserPresave();

    // Create synchronized users and change attribute values only on Apigee
    // Edge.
    for ($i = 0; $i < self::DEVELOPER_TO_CREATE_PER_TYPE; $i++) {
      $user = $this->createAccount([], TRUE, $this->prefix);
      foreach ($this->fields as $field_type => $data) {
        $user->set($this->fieldNamePrefix . $data['name'], $data['data']);
      }
      foreach ($this->listFields as $field_type => $data) {
        $user->set($this->fieldNamePrefix . $data['name'], $data['odata']);
      }
      // Set unlinked field on the user.
      $user->set($this->fieldNamePrefix . 'invalid_email', 'valid.email@example.com');
      // Set valid email field on the user.
      $user->set($this->fieldNamePrefix . 'one_track_field', 'user');
      $user->setChangedTime($this->container->get('datetime.time')->getCurrentTime() - 100);
      $user->save();
      $this->modifiedEdgeDevelopers[$user->getEmail()] = Developer::load($user->getEmail());

      foreach ($this->fields as $field_type => $data) {
        $formatter = $this->formatManager->lookupPluginForFieldType($field_type);
        $this->modifiedEdgeDevelopers[$user->getEmail()]->setAttribute($data['name'], $formatter->encode($data['data_changed']));
      }
      foreach ($this->listFields as $field_type => $data) {
        $formatter = $this->formatManager->lookupPluginForFieldType($field_type);
        $this->modifiedEdgeDevelopers[$user->getEmail()]->setAttribute($data['name'], $formatter->encode($data['data_changed']));
      }

      // Change first name and last name.
      $this->modifiedEdgeDevelopers[$user->getEmail()]->setFirstName($this->randomGenerator->word(8));
      $this->modifiedEdgeDevelopers[$user->getEmail()]->setLastName($this->randomGenerator->word(8));

      // Set unlinked attribute on the developer.
      $this->modifiedEdgeDevelopers[$user->getEmail()]->setAttribute('one_track_field', 'developer');
      // Set invalid email attribute value on the developer.
      $this->modifiedEdgeDevelopers[$user->getEmail()]->setAttribute('invalid_email', 'invalid_email_address');
      $this->modifiedEdgeDevelopers[$user->getEmail()]->save();
    }

    // Create synchronized users and change field values only in Drupal.
    for ($i = 0; $i < self::DEVELOPER_TO_CREATE_PER_TYPE; $i++) {
      $user = $this->createAccount([], TRUE, $this->prefix);
      foreach ($this->fields as $field_type => $data) {
        $user->set($this->fieldNamePrefix . $data['name'], $data['data']);
      }
      foreach ($this->listFields as $field_type => $data) {
        $user->set($this->fieldNamePrefix . $data['name'], $data['odata']);
      }
      $user->save();
      $this->modifiedDrupalUsers[$user->getEmail()] = $user;

      // Set unlinked field on Apigee Edge.
      /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $developer */
      $developer = Developer::load($user->getEmail());
      $developer->setAttribute('one_track_field', 'developer');
      $developer->save();

      // Do not let run apigee_edge_user_presave(), so the corresponding
      // developer won't be updated.
      $this->disableUserPresave();
      foreach ($this->fields as $field_type => $data) {
        $this->modifiedDrupalUsers[$user->getEmail()]->set($this->fieldNamePrefix . $data['name'], $data['data_changed']);
      }
      foreach ($this->listFields as $field_type => $data) {
        $this->modifiedDrupalUsers[$user->getEmail()]->set($this->fieldNamePrefix . $data['name'], $data['data_changed']);
      }

      // Change first name, last name and username.
      $this->modifiedDrupalUsers[$user->getEmail()]->set('first_name', $this->randomGenerator->word(8));
      $this->modifiedDrupalUsers[$user->getEmail()]->set('last_name', $this->randomGenerator->word(8));
      $this->modifiedDrupalUsers[$user->getEmail()]->set('name', $this->randomGenerator->word(8));

      // Set unlinked field in Drupal.
      $this->modifiedDrupalUsers[$user->getEmail()]->set($this->fieldNamePrefix . 'one_track_field', 'user');
      // It's necessary because changed time is automatically updated on the UI
      // only.
      $this->modifiedDrupalUsers[$user->getEmail()]->setChangedTime($this->container->get('datetime.time')->getCurrentTime() + 100);
      $this->modifiedDrupalUsers[$user->getEmail()]->save();
      $this->enableUserPresave();
    }

    // Developer's username already exists. Should not be copied into Drupal.
    Developer::create([
      'email' => "{$this->prefix}.reserved@example.com",
      'userName' => reset($this->drupalUsers)->getAccountName(),
      'firstName' => $this->randomMachineName(),
      'lastName' => $this->randomMachineName(),
    ])->save();

    // Do not block Drupal user if the corresponding developer's status is
    // inactive.
    $active_user = $this->createAccount([], TRUE, $this->prefix);
    $dc = new DeveloperController($this->container->get('apigee_edge.sdk_connector')->getOrganization(), $this->container->get('apigee_edge.sdk_connector')->getClient());
    $dc->setStatus($active_user->getEmail(), Developer::STATUS_INACTIVE);
    $this->inactiveDeveloper = Developer::load($active_user->getEmail());
    $this->assertEquals($this->inactiveDeveloper->getStatus(), Developer::STATUS_INACTIVE);
  }

  /**
   * Creates fields for Drupal users.
   */
  protected function setUpUserFields() {
    $text = $this->getRandomGenerator()->sentences(5);
    $link = [
      [
        'title' => 'Example',
        'options' => [],
        'uri' => 'http://example.com',
      ],
    ];
    $link_changed = [
      [
        'title' => 'Example_Changed',
        'options' => [],
        'uri' => 'http://example.com/changed',
      ],
    ];

    $this->fields = [
      'boolean' => [
        'name' => strtolower($this->randomMachineName()),
        'data' => [
          ['value' => 1],
        ],
        'data_changed' => [
          ['value' => 0],
        ],
      ],
      'email' => [
        'name' => strtolower($this->randomMachineName()),
        'data' => [
          ['value' => 'test@example.com'],
        ],
        'data_changed' => [
          ['value' => 'test.changed@example.com'],
        ],
      ],
      'timestamp' => [
        'name' => strtolower($this->randomMachineName()),
        'data' => [
          ['value' => 1531212177],
        ],
        'data_changed' => [
          ['value' => 1531000000],
        ],
      ],
      'integer' => [
        'name' => strtolower($this->randomMachineName()),
        'data' => [
          ['value' => 4],
          ['value' => 9],
        ],
        'data_changed' => [
          ['value' => 2],
          ['value' => 8],
          ['value' => 1],
        ],
      ],
      'string' => [
        'name' => strtolower($this->randomMachineName()),
        'data' => [
          ['value' => $text],
        ],
        'data_changed' => [
          ['value' => strrev($text)],
        ],
      ],
      'string_long' => [
        'name' => strtolower($this->randomMachineName()),
        'data' => [
          ['value' => $text],
        ],
        'data_changed' => [
          ['value' => strrev($text)],
        ],
      ],
      'link' => [
        'name' => strtolower($this->randomMachineName()),
        'data' => $link,
        'data_changed' => $link_changed,
      ],
    ];

    $this->listFields = [
      'list_integer' => [
        'name' => strtolower($this->randomMachineName()),
        'settings' => [
          'field_storage[subform][settings][allowed_values][table][0][item][key]' => 0,
          'field_storage[subform][settings][allowed_values][table][0][item][label]' => 'Zero',
          'field_storage[subform][settings][allowed_values][table][1][item][key]' => 1,
          'field_storage[subform][settings][allowed_values][table][1][item][label]' => 'One',
        ],
        'data' => [
          0 => 'Zero',
          1 => 'One',
        ],
        'odata' => [
          ['value' => 0],
          ['value' => 1],
        ],
        'data_changed' => [
          ['value' => 2],
        ],
      ],
      'list_string' => [
        'name' => strtolower($this->randomMachineName()),
        'settings' => [
          'field_storage[subform][settings][allowed_values][table][0][item][key]' => 'zero',
          'field_storage[subform][settings][allowed_values][table][0][item][label]' => 'Zero',
          'field_storage[subform][settings][allowed_values][table][1][item][key]' => 'one',
          'field_storage[subform][settings][allowed_values][table][1][item][label]' => 'One',
        ],
        'data' => [
          'zero' => 'Zero',
          'one' => 'One',
        ],
        'odata' => [
          ['value' => 'zero'],
          ['value' => 'one'],
        ],
        'data_changed' => [
          ['value' => 'two'],
        ],
      ],
      'list_float' => [
        'name' => strtolower($this->randomMachineName()),
        'settings' => [
          'field_storage[subform][settings][allowed_values][table][0][item][key]' => 0,
          'field_storage[subform][settings][allowed_values][table][0][item][label]' => 'Zero',
          'field_storage[subform][settings][allowed_values][table][1][item][key]' => .5,
          'field_storage[subform][settings][allowed_values][table][1][item][label]' => 'Point five',
          'field_storage[subform][settings][allowed_values][table][2][item][key]' => 2,
          'field_storage[subform][settings][allowed_values][table][2][item][label]' => 'Two',
        ],
        'data' => [
          '0' => 'Zero',
          '0.5' => 'Point five',
          '2' => 'Two',
        ],
        'odata' => [
          ['value' => 0.5],
        ],
        'data_changed' => [
          ['value' => 0.8],
        ],
      ],
    ];

    // Changes for field of types 'list' fields
    // Using field configs to save the fields as issue is faced by FieldUiTestTrait.
    foreach ($this->listFields as $list_type => $listData) {
      $this->fieldName = 'field_' . $listData['name'];
      $this->createOptionsField($list_type);
      $this->assertAllowedValuesInput($listData['settings'], $listData['data'], '');
    }

    foreach ($this->fields as $field_type => $data) {
      $this->fieldUIAddNewField(
        Url::fromRoute('entity.user.admin_form')->toString(),
        $data['name'],
        mb_strtoupper($data['name']),
        $field_type,
        ($data['settings'] ?? []) + [
          'cardinality' => -1,
        ],
        []
      );
    }

    // Create a Drupal user field that is not linked to any Apigee Edge
    // developer attribute. It should be unchanged after sync on both sides.
    $this->fieldUIAddNewField(
      Url::fromRoute('entity.user.admin_form')->toString(),
      'one_track_field',
      strtoupper('one_track_field'),
      'string',
      [
        'cardinality' => -1,
      ],
      []
    );

    // Create a Drupal user email field that has an invalid value on Apigee Edge
    // (invalid email address). The invalid value should not be copied into the
    // Drupal user's field.
    $this->fieldUIAddNewField(
      Url::fromRoute('entity.user.admin_form')->toString(),
      'invalid_email',
      strtoupper('invalid_email'),
      'email',
      [
        'cardinality' => -1,
      ],
      []
    );

    drupal_flush_all_caches();

    // Set the fields to be synchronized.
    $this->drupalGet(Url::fromRoute('apigee_edge.settings.developer.attributes'));
    $full_field_names = [];
    foreach ($this->fields as $field_type => $data) {
      $full_field_name = "{$this->fieldNamePrefix}{$data['name']}";
      $this->getSession()->getPage()->checkField("attributes[{$full_field_name}]");
      $full_field_names[] = $full_field_name;
    }
    $this->getSession()->getPage()->checkField("attributes[{$this->fieldNamePrefix}invalid_email]");
    $full_field_names[] = "{$this->fieldNamePrefix}invalid_email";
    $this->getSession()->getPage()->pressButton('Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $user_fields_to_sync = $this->config('apigee_edge.sync')->get('user_fields_to_sync');
    $this->assertEquals(asort($user_fields_to_sync), asort($full_field_names));
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $developers_to_delete = array_merge($this->edgeDevelopers, $this->drupalUsers, $this->modifiedEdgeDevelopers, $this->modifiedDrupalUsers);
    foreach ($developers_to_delete as $email => $entity) {
      try {
        /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $developer */
        if (($developer = Developer::load($email)) !== NULL) {
          $developer->delete();
        }
      }
      catch (\Exception $exception) {
        $this->logException($exception);
      }
    }
    try {
      /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $developer */
      if (($developer = Developer::load("{$this->prefix}.reserved@example.com")) !== NULL) {
        $developer->delete();
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }
    try {
      if ($this->inactiveDeveloper !== NULL) {
        $this->inactiveDeveloper->delete();
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }
    parent::tearDown();
  }

  /**
   * Verifies that the Drupal users and the Edge developers are synchronized.
   */
  protected function verify() {
    $developers_to_verify = array_merge($this->edgeDevelopers, $this->drupalUsers, $this->modifiedEdgeDevelopers, $this->modifiedDrupalUsers);
    foreach ($developers_to_verify as $email => $entity) {
      /** @var \Drupal\user\UserInterface $user */
      $user = user_load_by_mail($email);
      /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $developer */
      $developer = Developer::load($email);

      $this->assertNotEmpty($user, 'User found: ' . $email);
      $this->assertNotEmpty($developer, 'Developer found: ' . $email);
      $this->assertEquals($developer->getUserName(), $user->getAccountName());
      $this->assertEquals($developer->getFirstName(), $user->get('first_name')->value);
      $this->assertEquals($developer->getLastName(), $user->get('last_name')->value);

      if (array_key_exists($email, $this->modifiedDrupalUsers) || array_key_exists($email, $this->modifiedEdgeDevelopers)) {
        // Unlinked field/attribute should be unchanged on both sides.
        $this->assertEquals($developer->getAttributeValue('one_track_field'), 'developer');
        $this->assertEquals($user->get($this->fieldNamePrefix . 'one_track_field')->value, 'user');

        foreach ($this->fields as $field_type => $data) {
          $formatter = $this->formatManager->lookupPluginForFieldType($field_type);
          if ($formatter instanceof JSON) {
            $this->assertJsonStringEqualsJsonString($developer->getAttributeValue($data['name']), $formatter->encode($user->get($this->fieldNamePrefix . $data['name'])->getValue()));
            $this->assertJsonStringEqualsJsonString($developer->getAttributeValue($data['name']), $formatter->encode($data['data_changed']));
          }
          elseif ($formatter instanceof CSV) {
            $this->assertEquals($developer->getAttributeValue($data['name']), $formatter->encode($user->get($this->fieldNamePrefix . $data['name'])->getValue()));
            $this->assertEquals($developer->getAttributeValue($data['name']), $formatter->encode($data['data_changed']));
          }
        }
      }
      else {
        foreach ($this->fields as $field_type => $data) {
          $formatter = $this->formatManager->lookupPluginForFieldType($field_type);
          if ($formatter instanceof JSON) {
            $this->assertJsonStringEqualsJsonString($developer->getAttributeValue($data['name']), $formatter->encode($user->get($this->fieldNamePrefix . $data['name'])->getValue()));
            $this->assertJsonStringEqualsJsonString($developer->getAttributeValue($data['name']), $formatter->encode($data['data']));
          }
          elseif ($formatter instanceof CSV) {
            $this->assertEquals($developer->getAttributeValue($data['name']), $formatter->encode($user->get($this->fieldNamePrefix . $data['name'])->getValue()));
            $this->assertEquals($developer->getAttributeValue($data['name']), $formatter->encode($data['data']));
          }
        }
      }

      // Invalid email address should not be copied into the corresponding
      // Drupal user field.
      if ($developer->hasAttribute('invalid_email')) {
        if (array_key_exists($email, $this->edgeDevelopers)) {
          $this->assertNull($user->get("{$this->fieldNamePrefix}invalid_email")->value);
        }
        elseif (array_key_exists($email, $this->modifiedEdgeDevelopers)) {
          $this->assertEquals($user->get("{$this->fieldNamePrefix}invalid_email")->value, 'valid.email@example.com');
        }
      }
    }

    // Developer with existing username is not copied into Drupal.
    $this->assertFalse(user_load_by_mail("{$this->prefix}.reserved@example.com"));

    // Drupal user's status is active.
    /** @var \Drupal\user\UserInterface $active_user */
    $active_user = user_load_by_mail($this->inactiveDeveloper->getEmail());
    $this->assertTrue($active_user->isActive());

    // Only the necessary test users were created in Drupal besides the
    // inactive developer's, anonymous and admin users.
    $this->assertEquals(count(User::loadMultiple()), count($developers_to_verify) + 3);
  }

  /**
   * Tests developer synchronization.
   */
  public function testDeveloperSync() {
    $this->drupalGet(Url::fromRoute('apigee_edge.settings.developer.sync'));
    $this->clickLinkProperly('Run developer sync');
    $this->assertSession()->pageTextContains('Apigee Edge developers are in sync with Drupal users.');
    // Fix cache invalidation issue that makes this test fail.
    // It seems clearing user storage's cache with the line below does not
    // clear the _real_ user storage cache which is used by user_load_by_mail().
    // $this->container->get('entity_type.manager')->getStorage('user')->resetCache();
    // On the other hand, when a user gets updated entity cache should be
    // invalidated automatically.
    // @see https://www.drupal.org/project/drupal/issues/3015002
    \Drupal::service('entity_type.manager')->getStorage('user')->resetCache();
    $this->verify();
  }

  /**
   * Tests scheduled developer synchronization.
   */
  public function testDeveloperAsync() {
    $this->drupalGet(Url::fromRoute('apigee_edge.settings.developer.sync'));
    $this->clickLinkProperly('Background');
    $this->assertSession()->pageTextContains('Developer synchronization is scheduled.');
    /** @var \Drupal\Core\Queue\QueueFactory $queue_service */
    $queue_service = $this->container->get('queue');
    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = $queue_service->get('apigee_edge_job');
    /** @var \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_worker_manager */
    $queue_worker_manager = $this->container->get('plugin.manager.queue_worker');
    /** @var \Drupal\Core\Queue\QueueWorkerInterface $worker */
    $worker = $queue_worker_manager->createInstance('apigee_edge_job');
    while (($item = $queue->claimItem())) {
      $worker->processItem($item->data);
      $queue->deleteItem($item);
    }
    $this->verify();
  }

  /**
   * Tests the developer synchronization started from the CLI.
   */
  public function testCliDeveloperSync() {
    $cli_service = $this->container->get('apigee_edge.cli');
    $input = new ArgvInput();
    $output = new BufferedOutput();
    $cli_service->sync(new SymfonyStyle($input, $output), 't');
    $printed_output = $output->fetch();

    foreach ($this->edgeDevelopers as $email => $developer) {
      $this->assertStringContainsString("Copying developer ({$email}) from Apigee Edge.", $printed_output);
    }
    foreach ($this->drupalUsers as $email => $user) {
      $this->assertStringContainsString("Copying user ({$email}) to Apigee Edge.", $printed_output);
    }
    foreach ($this->modifiedEdgeDevelopers as $email => $developer) {
      $this->assertStringContainsString("Updating user ({$email}) from Apigee Edge.", $printed_output);
    }
    foreach ($this->modifiedDrupalUsers as $email => $user) {
      $this->assertStringContainsString("Updating developer ({$email}) in Apigee Edge.", $printed_output);
    }

    $this->verify();
  }

  /**
   * Helper function to create list field of a given type.
   *
   * @param string $type
   *   One of 'list_integer', 'list_float' or 'list_string'.
   */
  protected function createOptionsField($type) {
    // Create a field.
    FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'user',
      'type' => $type,
    ])->save();
    FieldConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'user',
      'bundle' => 'user',
    ])->save();

    \Drupal::service('entity_display.repository')
      ->getFormDisplay('user', 'user')
      ->setComponent($this->fieldName)
      ->save();

    $this->adminPath = 'admin/config/people/accounts/fields/user.user.' . $this->fieldName;
  }

  /**
   * Tests an input array for the 'allowed values' form element.
   *
   * @param array $input
   *   The input array.
   * @param array|string $result
   *   Either an expected resulting array in
   *   $field->getSetting('allowed_values'), or an expected error message.
   * @param string $message
   *   Message to display.
   *
   * @internal
   */
  public function assertAllowedValuesInput(array $input, $result, string $message): void {
    $this->drupalGet($this->adminPath);
    $page = $this->getSession()->getPage();
    $add_button = $page->findButton('Add another item');
    $add_button->click();
    $add_button->click();

    $this->submitForm($input, 'Save');
    // Verify that the page does not have double escaped HTML tags.
    $this->assertSession()->responseNotContains('&amp;lt;');

    if (is_string($result)) {
      $this->assertSession()->pageTextContains($result);
    }
    else {
      $field_storage = FieldStorageConfig::loadByName('user', $this->fieldName);
      $this->assertSame($field_storage->getSetting('allowed_values'), $result, $message);
    }
  }

}
