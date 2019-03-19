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

namespace Drupal\Tests\apigee_edge_apidocs\Functional;

use Drupal\apigee_edge_apidocs\Entity\ApiDoc;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the ApiDoc term access permissions.
 *
 * @group apigee_edge
 * @group apigee_edge_apidocs
 */
class ApiDocsAccessTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['apigee_edge_apidocs', 'block', 'field_ui'];

  /**
   * A published API Doc.
   *
   * @var \Drupal\apigee_edge_apidocs\Entity\ApiDoc
   */
  protected $apidocPublished;

  /**
   * An unpublished API Doc.
   *
   * @var \Drupal\apigee_edge_apidocs\Entity\ApiDoc
   */
  protected $apidocUnpublished;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Add the system menu blocks to appropriate regions.
    $this->setupMenus();

    // Create published apidoc.
    $apidoc_published = ApiDoc::create([
      'name' => 'API 1',
      'description' => 'Test API 1',
      'spec' => NULL,
      'status' => 1,
    ]);
    $apidoc_published->save();
    $this->apidocPublished = $apidoc_published;

    // Create unpublished apidoc.
    $apidoc_unpublished = ApiDoc::create([
      'name' => 'API 2',
      'description' => 'Test API 2',
      'spec' => NULL,
      'status' => 0,
    ]);
    $apidoc_unpublished->save();
    $this->apidocUnpublished = $apidoc_unpublished;
  }

  /**
   * Set up menus and tasks in their regions.
   *
   * Since menus and tasks are now blocks, we're required to explicitly set them
   * to regions.
   */
  protected function setupMenus() {
    $this->drupalPlaceBlock('system_menu_block:tools', ['region' => 'primary_menu']);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'secondary_menu']);
    $this->drupalPlaceBlock('local_actions_block', ['region' => 'content']);
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content']);
  }

  /**
   * Test admin access control functionality for apidocs.
   */
  public function testApiDocAccessAdmin() {
    $assert_session = $this->assertSession();

    // Test the 'administer apidoc entities' permission.
    $this->drupalLogin($this->drupalCreateUser([
      'administer apidoc entities',
      'administer apidoc display',
      'administer apidoc fields',
      'administer apidoc form display',
    ]));

    $this->drupalGet($this->apidocPublished->toUrl());
    $assert_session->statusCodeEquals(200);
    $this->assertApiDocAccess($this->apidocPublished, 'view', TRUE);

    $this->drupalGet($this->apidocUnpublished->toUrl());
    $assert_session->statusCodeEquals(200);
    $this->assertApiDocAccess($this->apidocUnpublished, 'view', TRUE);

    $this->drupalGet($this->apidocPublished->toUrl('edit-form'));
    $assert_session->statusCodeEquals(200);
    $this->assertApiDocAccess($this->apidocPublished, 'update', TRUE);
    $this->drupalGet($this->apidocUnpublished->toUrl('edit-form'));
    $assert_session->statusCodeEquals(200);
    $this->assertApiDocAccess($this->apidocUnpublished, 'update', TRUE);

    $this->drupalGet($this->apidocPublished->toUrl('delete-form'));
    $assert_session->statusCodeEquals(200);
    $this->assertApiDocAccess($this->apidocPublished, 'delete', TRUE);
    $this->drupalGet($this->apidocUnpublished->toUrl('delete-form'));
    $assert_session->statusCodeEquals(200);
    $this->assertApiDocAccess($this->apidocUnpublished, 'delete', TRUE);

    $this->drupalGet(Url::fromRoute('entity.apidoc.collection'));
    $assert_session->statusCodeEquals(200);

    $this->drupalGet(Url::fromRoute('entity.apidoc.add_form'));
    $assert_session->statusCodeEquals(200);

    $this->drupalGet(Url::fromRoute('apigee_edge_apidocs.settings'));
    $assert_session->statusCodeEquals(200);

    // Make sure the field manipulation links are available.
    $assert_session->linkExists('Manage fields');
    $assert_session->linkExists('Manage form display');
    $assert_session->linkExists('Manage display');
  }

  /**
   * Test no permissions for apidocs.
   */
  public function testApiDocAccessNoPermissions() {
    $assert_session = $this->assertSession();

    $this->drupalLogin($this->drupalCreateUser());

    $this->drupalGet($this->apidocPublished->toUrl());
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocPublished, 'view', FALSE);

    $this->drupalGet($this->apidocUnpublished->toUrl());
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocUnpublished, 'view', FALSE);

    $this->drupalGet($this->apidocPublished->toUrl('edit-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocPublished, 'update', FALSE);
    $this->drupalGet($this->apidocUnpublished->toUrl('edit-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocUnpublished, 'update', FALSE);

    $this->drupalGet($this->apidocPublished->toUrl('delete-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocPublished, 'delete', FALSE);
    $this->drupalGet($this->apidocUnpublished->toUrl('delete-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocUnpublished, 'delete', FALSE);

    $this->drupalGet(Url::fromRoute('entity.apidoc.collection'));
    $assert_session->statusCodeEquals(403);

    $this->drupalGet(Url::fromRoute('entity.apidoc.add_form'));
    $assert_session->statusCodeEquals(403);

    // Get admin settings page.
    $this->drupalGet(Url::fromRoute('apigee_edge_apidocs.settings'));
    $assert_session->statusCodeEquals(403);

  }

  /**
   * Test add permissions for apidocs.
   */
  public function testApiDocAccessAdd() {
    $assert_session = $this->assertSession();

    $this->drupalLogin($this->drupalCreateUser(['add apidoc entities']));

    $this->drupalGet($this->apidocPublished->toUrl());
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocPublished, 'view', FALSE);

    $this->drupalGet($this->apidocUnpublished->toUrl());
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocUnpublished, 'view', FALSE);

    $this->drupalGet($this->apidocPublished->toUrl('edit-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocPublished, 'update', FALSE);
    $this->drupalGet($this->apidocUnpublished->toUrl('edit-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocUnpublished, 'update', FALSE);

    $this->drupalGet($this->apidocPublished->toUrl('delete-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocPublished, 'delete', FALSE);
    $this->drupalGet($this->apidocUnpublished->toUrl('delete-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocUnpublished, 'delete', FALSE);

    $this->drupalGet(Url::fromRoute('entity.apidoc.collection'));
    $assert_session->statusCodeEquals(403);

    $this->drupalGet(Url::fromRoute('entity.apidoc.add_form'));
    $assert_session->statusCodeEquals(200);

    $this->drupalGet(Url::fromRoute('apigee_edge_apidocs.settings'));
    $assert_session->statusCodeEquals(403);

  }

  /**
   * Test edit permission for apidocs.
   */
  public function testApiDocAccessEdit() {
    $assert_session = $this->assertSession();

    $this->drupalLogin($this->drupalCreateUser(['edit apidoc entities']));

    $this->drupalGet($this->apidocPublished->toUrl());
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocPublished, 'view', FALSE);

    $this->drupalGet($this->apidocUnpublished->toUrl());
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocUnpublished, 'view', FALSE);

    $this->drupalGet($this->apidocPublished->toUrl('edit-form'));
    $assert_session->statusCodeEquals(200);
    $this->assertApiDocAccess($this->apidocPublished, 'update', TRUE);
    $this->drupalGet($this->apidocUnpublished->toUrl('edit-form'));
    $assert_session->statusCodeEquals(200);
    $this->assertApiDocAccess($this->apidocUnpublished, 'update', TRUE);

    $this->drupalGet($this->apidocPublished->toUrl('delete-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocPublished, 'delete', FALSE);
    $this->drupalGet($this->apidocUnpublished->toUrl('delete-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocUnpublished, 'delete', FALSE);

    $this->drupalGet(Url::fromRoute('entity.apidoc.collection'));
    $assert_session->statusCodeEquals(403);

    $this->drupalGet(Url::fromRoute('entity.apidoc.add_form'));
    $assert_session->statusCodeEquals(403);

    $this->drupalGet(Url::fromRoute('apigee_edge_apidocs.settings'));
    $assert_session->statusCodeEquals(403);

  }

  /**
   * Test delete permission for apidocs.
   */
  public function testApiDocAccessDelete() {
    $assert_session = $this->assertSession();

    $this->drupalLogin($this->drupalCreateUser(['delete apidoc entities']));

    $this->drupalGet($this->apidocPublished->toUrl());
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocPublished, 'view', FALSE);

    $this->drupalGet($this->apidocUnpublished->toUrl());
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocUnpublished, 'view', FALSE);

    $this->drupalGet($this->apidocPublished->toUrl('edit-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocPublished, 'update', FALSE);
    $this->drupalGet($this->apidocUnpublished->toUrl('edit-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocUnpublished, 'update', FALSE);

    $this->drupalGet($this->apidocPublished->toUrl('delete-form'));
    $assert_session->statusCodeEquals(200);
    $this->assertApiDocAccess($this->apidocPublished, 'delete', TRUE);
    $this->drupalGet($this->apidocUnpublished->toUrl('delete-form'));
    $assert_session->statusCodeEquals(200);
    $this->assertApiDocAccess($this->apidocUnpublished, 'delete', TRUE);

    $this->drupalGet(Url::fromRoute('entity.apidoc.collection'));
    $assert_session->statusCodeEquals(403);

    $this->drupalGet(Url::fromRoute('entity.apidoc.add_form'));
    $assert_session->statusCodeEquals(403);

    $this->drupalGet(Url::fromRoute('apigee_edge_apidocs.settings'));
    $assert_session->statusCodeEquals(403);

  }

  /**
   * Test view published permission for apidocs.
   */
  public function testApiDocAccessPublished() {
    $assert_session = $this->assertSession();

    // Test the 'administer apidoc entities' permission.
    $this->drupalLogin($this->drupalCreateUser(['view published apidoc entities']));

    $this->drupalGet($this->apidocPublished->toUrl());
    $assert_session->statusCodeEquals(200);
    $this->assertApiDocAccess($this->apidocPublished, 'view', TRUE);

    $this->drupalGet($this->apidocUnpublished->toUrl());
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocUnpublished, 'view', FALSE);

    $this->drupalGet($this->apidocPublished->toUrl('edit-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocPublished, 'update', FALSE);
    $this->drupalGet($this->apidocUnpublished->toUrl('edit-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocUnpublished, 'update', FALSE);

    $this->drupalGet($this->apidocPublished->toUrl('delete-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocPublished, 'delete', FALSE);
    $this->drupalGet($this->apidocUnpublished->toUrl('delete-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocUnpublished, 'delete', FALSE);

    $this->drupalGet(Url::fromRoute('entity.apidoc.collection'));
    $assert_session->statusCodeEquals(403);

    $this->drupalGet(Url::fromRoute('entity.apidoc.add_form'));
    $assert_session->statusCodeEquals(403);

    $this->drupalGet(Url::fromRoute('apigee_edge_apidocs.settings'));
    $assert_session->statusCodeEquals(403);

  }

  /**
   * Test view unpublished apidocs permissions for apidocs.
   */
  public function testApiDocAccessUnpublished() {
    $assert_session = $this->assertSession();

    // Test the 'administer apidoc entities' permission.
    $this->drupalLogin($this->drupalCreateUser(['view unpublished apidoc entities']));

    $this->drupalGet($this->apidocPublished->toUrl());
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocPublished, 'view', FALSE);

    $this->drupalGet($this->apidocUnpublished->toUrl());
    $assert_session->statusCodeEquals(200);
    $this->assertApiDocAccess($this->apidocUnpublished, 'view', TRUE);

    $this->drupalGet($this->apidocPublished->toUrl('edit-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocPublished, 'update', FALSE);
    $this->drupalGet($this->apidocUnpublished->toUrl('edit-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocUnpublished, 'update', FALSE);

    $this->drupalGet($this->apidocPublished->toUrl('delete-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocPublished, 'delete', FALSE);
    $this->drupalGet($this->apidocUnpublished->toUrl('delete-form'));
    $assert_session->statusCodeEquals(403);
    $this->assertApiDocAccess($this->apidocUnpublished, 'delete', FALSE);

    $this->drupalGet(Url::fromRoute('entity.apidoc.collection'));
    $assert_session->statusCodeEquals(403);

    $this->drupalGet(Url::fromRoute('entity.apidoc.add_form'));
    $assert_session->statusCodeEquals(403);

    $this->drupalGet(Url::fromRoute('apigee_edge_apidocs.settings'));
    $assert_session->statusCodeEquals(403);

  }

  /**
   * Checks access on apidoc.
   *
   * @param \Drupal\apigee_edge_apidocs\Entity\ApiDoc $apidoc
   *   An apidoc entity.
   * @param string $access_operation
   *   The entity operation, e.g. 'view', 'edit', 'delete', etc.
   * @param bool $access_allowed
   *   Whether the current use has access to the given operation or not.
   * @param string $access_reason
   *   (optional) The reason of the access result.
   */
  protected function assertApiDocAccess(ApiDoc $apidoc, $access_operation, $access_allowed, $access_reason = '') {
    $access_result = $apidoc->access($access_operation, NULL, TRUE);
    $this->assertSame($access_allowed, $access_result->isAllowed());

    if ($access_reason) {
      $this->assertSame($access_reason, $access_result->getReason());
    }
  }

}
