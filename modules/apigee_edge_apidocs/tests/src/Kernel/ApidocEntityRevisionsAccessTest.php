<?php

/**
 * Copyright 2019 Google Inc.
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

namespace Drupal\Tests\apigee_edge_apidocs\Kernel;

use Drupal\apigee_edge_apidocs\Entity\ApiDoc;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the ApiDoc entity access permissions.
 *
 * @group apigee_edge
 * @group apigee_edge_apidocs
 */
class ApidocEntityRevisionsAccessTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * A published API Doc.
   *
   * @var \Drupal\apigee_edge_apidocs\Entity\ApiDoc
   */
  protected $apidoc;

  /**
   * An Api Doc revision id.
   *
   * @var int
   */
  protected $apidocV1Id;

  /**
   * An Api Doc revision id.
   *
   * @var int
   */
  protected $apidocV2Id;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type storage instance.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityTypeStorage;

  protected static $modules = [
    'system',
    'user',
    'text',
    'file',
    'apigee_edge',
    'key',
    'apigee_edge_apidocs',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('apidoc');
    $this->installSchema('system', ['sequences']);

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->entityTypeStorage = $this->entityTypeManager->getStorage('apidoc');

    // Create a published apidoc.
    $apidoc = ApiDoc::create([
      'name' => 'API 1',
      'description' => 'Test API v1',
      'spec' => NULL,
      'api_product' => NULL,
      'status' => 1,
    ]);
    $apidoc->save();
    $this->apidocV1Id = $apidoc->getRevisionId();

    // Create a new revision.
    $apidoc->setDescription('Test API v2');
    $apidoc->setRevisionLogMessage('v2');
    $apidoc->setNewRevision();
    $apidoc->save();
    $this->apidocV2Id = $apidoc->getRevisionId();

    $this->apidoc = $apidoc;

    // Discard user 1, we will not need it because it bypasses access control.
    $this->createUser();
  }

  /**
   * Test ApiDocs revision access as anonymous.
   */
  public function testApiDocRevisionsAccessAnon() {
    $entity_v1 = $this->entityTypeStorage->loadRevision($this->apidocV1Id);

    $tests = [
      'view' => 'Anonymous should not be able to view an unpublished revision.',
      'update' => 'Anonymous should not be able to update a revision.',
    ];

    foreach ($tests as $op => $message) {
      $this->assertFalse($entity_v1->access($op), $message);
    }
  }

  /**
   * Test ApiDocs revision access a logged in user.
   */
  public function testApiDocRevisionsAccessLoggedIn() {
    $user = $this->createUser([]);
    \Drupal::currentUser()->setAccount($user);

    $entity_v1 = $this->entityTypeStorage->loadRevision($this->apidocV1Id);

    $tests = [
      'view' => 'LoggedIn should not be able to view an unpublished revision.',
      'update' => 'LoggedIn should not be able to update a revision.',
    ];

    foreach ($tests as $op => $message) {
      $this->assertFalse($entity_v1->access($op, $user), $message);
    }
  }

  /**
   * Test ApiDocs revision access as a logged in user with some permissions.
   */
  public function testApiDocRevisionsAccessPermissions() {
    $user = $this->createUser([
      'view published apidoc entities',
      'view unpublished apidoc entities',
      'view all apidoc revisions',
      'edit apidoc entities',
      'revert all apidoc revisions',
    ]);
    \Drupal::currentUser()->setAccount($user);

    $entity_v1 = $this->entityTypeStorage->loadRevision($this->apidocV1Id);

    $tests = [
      'view' => 'User should be able to view an unpublished revision.',
      'update' => 'User should be able to update a revision.',
    ];

    foreach ($tests as $op => $message) {
      $this->assertTrue($entity_v1->access($op, $user), $message);
    }
  }

  /**
   * Test ApiDocs revision access as a logged in user with admin permissions.
   */
  public function testApiDocRevisionsAccessAdmin() {
    $user = $this->createUser([
      'administer apidoc entities',
    ]);
    \Drupal::currentUser()->setAccount($user);

    $entity_v1 = $this->entityTypeStorage->loadRevision($this->apidocV1Id);

    $tests = [
      'view' => 'User should be able to view an unpublished revision.',
      'update' => 'User should be able to update a revision.',
    ];

    foreach ($tests as $op => $message) {
      $this->assertTrue($entity_v1->access($op, $user), $message);
    }
  }

}
