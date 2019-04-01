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

namespace Drupal\Tests\apigee_edge_apidocs\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Simple test to ensure that main page loads with module enabled.
 *
 * @group apigee_edge
 * @group apigee_edge_apidocs
 */
class ApiDocsAdminTest extends BrowserTestBase {

  use TestFileCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['apigee_edge_apidocs', 'block', 'field_ui'];

  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Set up menus and tasks in their regions.
   *
   * Since menus and tasks are now blocks, we're required to explicitly set them
   * to regions.
   */
  protected function setupMenus() {
    $this->drupalPlaceBlock('local_actions_block', ['region' => 'content']);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Add the system menu blocks to appropriate regions.
    $this->setupMenus();

    $this->adminUser = $this->drupalCreateUser([
      'add apidoc entities',
      'delete apidoc entities',
      'edit apidoc entities',
      'view published apidoc entities',
      'view unpublished apidoc entities',
      'administer apidoc entities',
      'administer apidoc display',
      'administer apidoc fields',
      'administer apidoc form display',
      'access administration pages',
      // Access content is needed to access the referenced files.
      'access content',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests that a user can administer API Doc entities.
   */
  public function testApiDocAdministration() {
    $header_selector = 'table .empty';

    $assert = $this->assertSession();

    // Get the API Doc admin page.
    $this->drupalGet(Url::fromRoute('entity.apidoc.collection'));

    // No API Docs yet.
    $assert->elementTextContains('css', $header_selector, 'There are no API Docs yet.');

    // User can add entity content.
    $assert->linkExists('Add API Doc');
    $this->clickLink('Add API Doc');

    // Fields should have proper defaults.
    $assert->fieldValueEquals('name[0][value]', '');
    $assert->fieldValueEquals('description[0][value]', '');
    $assert->fieldValueEquals('status[value]', '1');

    // Create a new spec in site.
    $file = File::create([
      'uid' => $this->adminUser->id(),
      'filename' => 'specA.yml',
      'uri' => 'public://specA.yml',
      'filemime' => 'application/octet-stream',
      'created' => 1,
      'changed' => 1,
      'status' => FILE_STATUS_PERMANENT,
    ]);
    file_put_contents($file->getFileUri(), "swagger: '2.0'");

    // Save it, inserting a new record.
    $file->save();
    $this->assertTrue($file->id() > 0, 'The file was added to the database.');

    $page = $this->getSession()->getPage();
    $random_name = $this->randomMachineName();
    $random_description = $this->randomGenerator->sentences(5);
    $page->fillField('name[0][value]', $random_name);
    $page->fillField('description[0][value]', $random_description);

    // Can't use drupalPostForm() to set hidden fields.
    $this->getSession()->getPage()->find('css', 'input[name="spec[0][fids]"]')->setValue($file->id());
    $this->getSession()->getPage()->pressButton(t('Save'));

    $assert->statusCodeEquals(200);
    $assert->pageTextContains(new FormattableMarkup('Created the @name API Doc.', ['@name' => $random_name]));

    // Entity listed.
    $assert->linkExists($random_name);
    $assert->linkExists('Edit');
    $assert->linkExists('Delete');

    // Click on API Doc to edit.
    $this->clickLink($random_name);
    $assert->statusCodeEquals(200);

    // Edit form should have proper values.
    $assert->fieldValueEquals('name[0][value]', $random_name);
    $assert->fieldValueEquals('description[0][value]', $random_description);
    $assert->fieldValueEquals('status[value]', '1');
    $assert->linkExists('specA.yml');

    // Delete the entity.
    $this->clickLink('Delete');

    // Confirm deletion.
    $assert->linkExists('Cancel');
    $this->drupalPostForm(NULL, [], 'Delete');

    // Back to list, should not longer have API Doc.
    $assert->pageTextNotContains($random_name);
  }

}
