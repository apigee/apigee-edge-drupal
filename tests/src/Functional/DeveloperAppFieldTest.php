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

use Apigee\Edge\Api\Management\Entity\App;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\field_ui\Tests\FieldUiTestTrait;

/**
 * @group apigee_edge
 * @group apigee_edge_developer_app
 * @group apigee_edge_field
 */
class DeveloperAppFieldTest extends ApigeeEdgeFunctionalTestBase {

  use FieldUiTestTrait;

  /**
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * @var \Drupal\apigee_edge\Entity\Developer
   */
  protected $developer;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->account = $this->createAccount([
      'administer apigee edge',
      'administer developer_app',
      'administer display modes',
      'administer developer_app fields',
      'administer developer_app form display',
      'administer developer_app display',
    ]);
    $this->developer = Developer::load($this->account->getEmail());
    $this->drupalLogin($this->account);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    $this->drupalLogout();
    $this->account->delete();
    parent::tearDown();
  }

  /**
   * @dataProvider fieldDataProvider
   */
  public function testField(string $field_type, array $storage_edit, array $field_edit, array $field_data) {
    $field_name = strtolower($this->randomMachineName());
    $this->fieldUIAddNewField(
      '/admin/config/apigee-edge/app-settings',
      $field_name, strtoupper($field_name),
      $field_type,
      $storage_edit,
      $field_edit
    );
    drupal_flush_all_caches();

    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $app */
    $app = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);
    $app->setOwner($this->account);
    $app->set($field_name, $field_data);
    $app->save();

    drupal_flush_all_caches();

    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $loadedApp */
    $loadedApp = DeveloperApp::load($app->id());

    $this->assertEquals($field_data, $loadedApp->get($field_name)->getValue());

    $app->delete();
  }

  public function fieldDataProvider() {
    return [
      'link' => [
        'link',
        [],
        [],
        [
          [
            'uri' => 'http://example.com',
            'title' => 'Example',
            'options' => [],
          ],
        ],
      ],
      'long text' => [
        'text_long',
        [
          'cardinality' => -1,
        ],
        [],
        [
          [
            'value' => $this->getRandomGenerator()->paragraphs(),
          ],
          [
            'value' => $this->getRandomGenerator()->paragraphs(),
          ],
          [
            'value' => $this->getRandomGenerator()->paragraphs(),
          ],
          [
            'value' => $this->getRandomGenerator()->paragraphs(),
          ],
          [
            'value' => $this->getRandomGenerator()->paragraphs(),
          ],
        ],
      ],
    ];
  }

  public function testTypes() {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $app */
    $app = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);

    $field_values = [
      'scopes' => ['a', 'b', 'c'],
      'displayName' => $this->getRandomGenerator()->word(16),
      'createdAt' => time(),
    ];

    foreach ($field_values as $field_name => $field_value) {
      $app->set($field_name, $field_value);
    }

    $app->preSave(new class() implements EntityStorageInterface {

      public function resetCache(array $ids = NULL) {}

      public function loadMultiple(array $ids = NULL) {}

      public function load($id) {}

      public function loadUnchanged($id) {}

      public function loadRevision($revision_id) {}

      public function deleteRevision($revision_id) {}

      public function loadByProperties(array $values = []) {}

      public function create(array $values = []) {}

      public function delete(array $entities) {}

      public function save(EntityInterface $entity) {}

      public function hasData() {}

      public function getQuery($conjunction = 'AND') {}

      public function getAggregateQuery($conjunction = 'AND') {}

      public function getEntityTypeId() {}

      public function getEntityType() {}

    });

    foreach ($field_values as $field_name => $field_value) {
      $getter = 'get' . ucfirst($field_name);
      $value = call_user_func([$app, $getter]);
      if ($value instanceof \DateTimeImmutable) {
        $value = $value->getTimestamp();
      }

      $this->assertEquals($field_value, $value);
    }
  }

  /**
   * Saves the developer app's base field config form.
   *
   * @param bool $description_required
   *   Whether the description must be required.
   * @param bool $callback_url_required
   *   Whether the callback url must be required.
   * @param bool $expect_success
   *   Whether to expect success or a validation error.
   */
  protected function submitBaseFieldConfigForm(bool $description_required = FALSE, bool $callback_url_required = FALSE, bool $expect_success = TRUE) {
    $this->drupalPostForm('/admin/config/apigee-edge/app-settings/fields', [
      'table[description][required]' => $description_required,
      'table[callbackUrl][required]' => $callback_url_required,
    ], 'Save');

    if ($expect_success) {
      $this->assertSession()->pageTextContains('Field settings have been saved successfully.');
    }
    else {
      $this->assertSession()->pageTextContains('is hidden on the default form display.');
    }
  }

  /**
   * Saves the developer app's form display settings.
   *
   * @param array $region_overrides
   *   Which field's regions should be changed. Key is the field name, value is
   *   the region.
   * @param bool $expect_success
   *   Whether to expect success or a validation error.
   */
  protected function submitFormDisplay(array $region_overrides = [], bool $expect_success = TRUE) {
    $edit = [];

    foreach ($region_overrides as $field => $region) {
      $edit["fields[{$field}][region]"] = $region;
    }

    $this->drupalPostForm('/admin/config/apigee-edge/app-settings/form-display', $edit, 'Save');

    if ($expect_success) {
      $this->assertSession()->pageTextContains('Your settings have been saved.');
    }
    else {
      $this->assertSession()->pageTextContains('is required.');
    }
  }

  /**
   * Saves the developer app's view display settings.
   *
   * @param array $region_overrides
   *   Which field's regions should be changed. Key is the field name, value is
   *   the region.
   */
  protected function submitViewDisplay(array $region_overrides = []) {
    $edit = [];

    foreach ($region_overrides as $field => $region) {
      $edit["fields[{$field}][region]"] = $region;
    }

    $this->drupalPostForm('/admin/config/apigee-edge/app-settings/display', $edit, 'Save');

    $this->assertSession()->pageTextContains('Your settings have been saved.');
  }

  /**
   * Tests settings base fields required.
   */
  public function testRequired() {
    // The form can be saved with default settings.
    $this->submitBaseFieldConfigForm();
    // Move the callbackUrl to hidden.
    $this->submitFormDisplay(['callbackUrl' => 'hidden']);
    // The callbackUrl can't be required.
    $this->submitBaseFieldConfigForm(TRUE, TRUE, FALSE);
    // Move back callbackUrl to visible.
    $this->submitFormDisplay(['callbackUrl' => 'content']);
    // callbackUrl can be required.
    $this->submitBaseFieldConfigForm(TRUE, TRUE);
    // callbackUrl can't be hidden.
    $this->submitFormDisplay(['callbackUrl' => 'hidden'], FALSE);
  }

  /**
   * Asserts whether a field is visible on the entity form.
   *
   * @param string $field_label
   *   Label of the field.
   * @param bool $visible
   *   Whether it should be visible or not.
   */
  protected function assertFieldVisibleOnEntityForm(string $field_label, bool $visible = TRUE) {
    $this->drupalGet("/user/{$this->account->id()}/apps/create");
    if ($visible) {
      $this->assertSession()->pageTextContains($field_label);
    }
    else {
      $this->assertSession()->pageTextNotContains($field_label);
    }
  }

  /**
   * Asserts whether a field is visible on the entity view.
   *
   * @param string $app_name
   *   Name of the app.
   * @param string $field_label
   *   Label of the field.
   * @param string $field_value
   *   Value of the field to assert.
   * @param bool $visible
   *   Whether it should be visible or not.
   */
  protected function assertFieldVisibleOnEntityDisplay(string $app_name, string $field_label, string $field_value, bool $visible = TRUE) {
    $this->drupalGet("/user/{$this->account->id()}/apps/{$app_name}");
    if ($visible) {
      $this->assertSession()->pageTextContains($field_label);
      $this->assertSession()->pageTextContains($field_value);
    }
    else {
      $this->assertSession()->pageTextNotContains($field_label);
      $this->assertSession()->pageTextNotContains($field_value);
    }
  }

  /**
   * Creates an app with the UI.
   *
   * @param array $extra_values
   *   Extra value for the form, besides name and displayName.
   *
   * @return string
   *   The machine name of the app.
   */
  protected function createApp(array $extra_values = []): string {
    $name = strtolower($this->randomMachineName());

    $this->drupalPostForm("/user/{$this->account->id()}/apps/create", $extra_values + [
      'displayName[0][value]' => $name,
      'name' => $name,
    ], 'Add developer app');
    $this->assertSession()->pageTextContains($name);

    return $name;
  }

  /**
   * Tests form regions.
   */
  public function testFormRegion() {
    $this->assertFieldVisibleOnEntityForm('Callback URL');
    $this->submitFormDisplay(['callbackUrl' => 'hidden']);
    $this->assertFieldVisibleOnEntityForm('Callback URL', FALSE);
    $this->submitFormDisplay(['callbackUrl' => 'content']);
    $this->assertFieldVisibleOnEntityForm('Callback URL');
  }

  /**
   * Tests the view regions.
   */
  public function testViewRegion() {
    $callbackUrl = 'https://' . strtolower($this->randomMachineName()) . '.example.com';
    $name = $this->createApp([
      'callbackUrl[0][value]' => $callbackUrl,
    ]);

    $assert = function (bool $visible = TRUE) use ($name, $callbackUrl) {
      $this->assertFieldVisibleOnEntityDisplay($name, 'Callback URL', $callbackUrl, $visible);
    };

    $this->submitViewDisplay(['callbackUrl' => 'content']);
    $assert(TRUE);
    $this->submitViewDisplay(['callbackUrl' => 'hidden']);
    $assert(FALSE);
  }

  /**
   * Tests showing and hiding credentials on the developer app view.
   */
  public function testCredentialsView() {
    $name = $this->createApp();
    $assert = function (bool $visible = TRUE) use ($name) {
      $this->assertFieldVisibleOnEntityDisplay($name, 'Credential', 'Key Status', $visible);
    };

    $this->submitViewDisplay(['credentials' => 'hidden']);
    $assert(FALSE);
    $this->submitViewDisplay(['credentials' => 'content']);
    $assert(TRUE);
  }

}
