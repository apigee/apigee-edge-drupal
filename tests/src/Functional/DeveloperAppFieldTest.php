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

use Apigee\Edge\Api\Management\Controller\DeveloperAppController;
use Apigee\Edge\Api\Management\Entity\App;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Url;
use Drupal\field_ui\Tests\FieldUiTestTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fieldable developer app test.
 *
 * @group apigee_edge
 * @group apigee_edge_developer_app
 * @group apigee_edge_field
 */
class DeveloperAppFieldTest extends ApigeeEdgeFunctionalTestBase {

  use FieldUiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'block',
  ];

  /**
   * The Drupal user that belongs to the developer app's developer.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * The owner of the developer app.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface
   */
  protected $developer;

  /**
   * Developer app to test.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $developerApp;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalPlaceBlock('system_breadcrumb_block');

    $this->account = $this->createAccount([
      'administer apigee edge',
      'administer developer_app',
      'administer display modes',
      'administer developer_app fields',
      'administer developer_app form display',
      'administer developer_app display',
    ]);
    $this->developer = Developer::load($this->account->getEmail());

    $this->developerApp = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);
    $this->developerApp->setOwner($this->account);
    $this->developerApp->save();

    $this->drupalLogin($this->account);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    try {
      if ($this->account !== NULL) {
        $this->account->delete();
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }
    parent::tearDown();
  }

  /**
   * Tests fieldable developer app entity.
   */
  public function testFieldableDeveloperApp() {
    $this->fieldStorageFormattersTest();
    $this->typesTest();
    $this->requiredFieldTest();
    $this->formRegionTest();
    $this->viewRegionTest();
    $this->credentialsViewTest();
  }

  /**
   * Tests field storage formatters (CSV and JSON).
   */
  protected function fieldStorageFormattersTest() {
    $field_name_prefix = (string) $this->config('field_ui.settings')->get('field_prefix');

    $paragraph = trim($this->getRandomGenerator()->paragraphs(1));
    $paragraphs = trim($this->getRandomGenerator()->paragraphs());
    $link = [
      [
        'uri' => 'http://example.com',
        'title' => 'Example',
        'options' => [],
        'attributes' => [],
      ],
    ];

    $fields = [
      strtolower($this->randomMachineName()) => [
        'type' => 'boolean',
        'data' => [
          ['value' => TRUE],
        ],
        'encoded' => '1',
      ],
      strtolower($this->randomMachineName()) => [
        'type' => 'float',
        'data' => [
          ['value' => M_PI],
          ['value' => M_E],
          ['value' => M_EULER],
        ],
        'encoded' => implode(',', [M_PI, M_E, M_EULER]),
      ],
      strtolower($this->randomMachineName()) => [
        'type' => 'integer',
        'data' => [
          ['value' => 4],
          ['value' => 9],
        ],
        'encoded' => '4,9',
      ],
      strtolower($this->randomMachineName()) => [
        'type' => 'decimal',
        'data' => [
          ['value' => '0.1'],
        ],
        'encoded' => '0.1',
      ],
      strtolower($this->randomMachineName()) => [
        'type' => 'list_float',
        'settings' => [
          'settings[allowed_values]' => implode(PHP_EOL, [
            M_PI,
            M_E,
            M_EULER,
          ]),
        ],
        'data' => [
          ['value' => M_PI],
        ],
        'encoded' => (string) M_PI,
      ],
      strtolower($this->randomMachineName()) => [
        'type' => 'list_integer',
        'settings' => [
          'settings[allowed_values]' => implode(PHP_EOL, [1, 2, 3]),
        ],
        'data' => [
          ['value' => 2],
          ['value' => 3],
        ],
        'encoded' => '2,3',
      ],
      strtolower($this->randomMachineName()) => [
        'type' => 'list_string',
        'settings' => [
          'settings[allowed_values]' => implode(PHP_EOL, [
            'qwer',
            'asdf',
            'zxcv',
          ]),
        ],
        'data' => [
          ['value' => 'qwer'],
          ['value' => 'asdf'],
          ['value' => 'zxcv'],
        ],
        'encoded' => 'qwer,asdf,zxcv',
      ],
      strtolower($this->randomMachineName()) => [
        'type' => 'string',
        'data' => [
          ['value' => $paragraph],
        ],
        'encoded' => "\"{$paragraph}\"",
      ],
      strtolower($this->randomMachineName()) => [
        'type' => 'string_long',
        'data' => [
          ['value' => $paragraphs],
        ],
        'encoded' => "\"{$paragraphs}\"",
      ],
      strtolower($this->randomMachineName()) => [
        'type' => 'email',
        'data' => [
          ['value' => 'test@example.com'],
          ['value' => 'test_2@example.com'],
        ],
        'encoded' => 'test@example.com,test_2@example.com',
      ],
      strtolower($this->randomMachineName()) => [
        'type' => 'timestamp',
        'data' => [
          ['value' => 1531212177],
          ['value' => 1531234234],
        ],
        'encoded' => '1531212177,1531234234',
      ],
      strtolower($this->randomMachineName()) => [
        'type' => 'link',
        'data' => $link,
        'encoded' => json_encode($link),
      ],
    ];

    // Add fields to developer app.
    $add_field_path = Url::fromRoute('apigee_edge.settings.developer_app')->toString();
    foreach ($fields as $name => $data) {
      $this->fieldUIAddNewField(
        $add_field_path,
        $name, strtoupper($name),
        $data['type'],
        ($data['settings'] ?? []) + [
          'cardinality' => -1,
        ],
        []
      );
    }

    drupal_flush_all_caches();
    $this->developerApp = DeveloperApp::load($this->developerApp->id());

    // Save field values as developer app entity attributes.
    foreach ($fields as $name => $data) {
      $full_field_name = "{$field_name_prefix}{$name}";
      $this->developerApp->set($full_field_name, $data['data']);
    }
    $this->developerApp->save();

    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $loaded_app */
    $loaded_app = DeveloperApp::load($this->developerApp->id());
    /** @var \Drupal\apigee_edge\SDKConnectorInterface $connector */
    $connector = $this->container->get('apigee_edge.sdk_connector');
    $controller = new DeveloperAppController($connector->getOrganization(), $this->developer->getDeveloperId(), $connector->getClient());
    /** @var \Apigee\Edge\Api\Management\Entity\DeveloperApp $rawLoadedApp */
    $rawLoadedApp = $controller->load($this->developerApp->getName());

    foreach ($fields as $name => $data) {
      $full_field_name = "{$field_name_prefix}{$name}";
      $this->assertEquals($data['data'], $loaded_app->get($full_field_name)->getValue());
      $this->assertEquals($data['encoded'], $rawLoadedApp->getAttributeValue($name));
    }
  }

  /**
   * Tests developer app entity preSave().
   */
  protected function typesTest() {
    $field_values = [
      'scopes' => ['a', 'b', 'c'],
      'displayName' => $this->getRandomGenerator()->word(16),
    ];

    foreach ($field_values as $field_name => $field_value) {
      $this->developerApp->set($field_name, $field_value);
    }

    $this->developerApp->preSave(new class() implements EntityStorageInterface {

      /**
       * {@inheritdoc}
       */
      public function resetCache(array $ids = NULL) {}

      /**
       * {@inheritdoc}
       */
      public function loadMultiple(array $ids = NULL) {}

      /**
       * {@inheritdoc}
       */
      public function load($id) {}

      /**
       * {@inheritdoc}
       */
      public function loadUnchanged($id) {}

      /**
       * {@inheritdoc}
       */
      public function loadRevision($revision_id) {}

      /**
       * {@inheritdoc}
       */
      public function deleteRevision($revision_id) {}

      /**
       * {@inheritdoc}
       */
      public function loadByProperties(array $values = []) {}

      /**
       * {@inheritdoc}
       */
      public function create(array $values = []) {}

      /**
       * {@inheritdoc}
       */
      public function delete(array $entities) {}

      /**
       * {@inheritdoc}
       */
      public function save(EntityInterface $entity) {}

      /**
       * {@inheritdoc}
       */
      public function hasData() {}

      /**
       * {@inheritdoc}
       */
      public function getQuery($conjunction = 'AND') {}

      /**
       * {@inheritdoc}
       */
      public function getAggregateQuery($conjunction = 'AND') {}

      /**
       * {@inheritdoc}
       */
      public function getEntityTypeId() {}

      /**
       * {@inheritdoc}
       */
      public function getEntityType() {}

      /**
       * {@inheritdoc}
       */
      public function restore(EntityInterface $entity) {}

    });

    foreach ($field_values as $field_name => $field_value) {
      $getter = 'get' . ucfirst($field_name);
      $value = call_user_func([$this->developerApp, $getter]);
      if ($value instanceof \DateTimeImmutable) {
        $value = $value->getTimestamp();
      }

      $this->assertEquals($field_value, $value);
    }
  }

  /**
   * Tests settings base fields required.
   */
  protected function requiredFieldTest() {
    // The form can be saved with default settings.
    $this->submitBaseFieldConfigForm();
    // Move the callbackUrl to hidden.
    $this->submitFormDisplay(['callbackUrl' => 'hidden']);
    // The callbackUrl can't be required.
    $this->submitBaseFieldConfigForm(TRUE, TRUE, FALSE);
    // Move back callbackUrl to visible.
    $this->submitFormDisplay(['callbackUrl' => 'content']);
    // The callbackUrl can be required.
    $this->submitBaseFieldConfigForm(TRUE, TRUE);
    // The callbackUrl can't be hidden.
    $this->submitFormDisplay(['callbackUrl' => 'hidden'], FALSE);
    // The callbackUrl is not required.
    $this->submitBaseFieldConfigForm(FALSE, FALSE);
  }

  /**
   * Tests form regions.
   */
  protected function formRegionTest() {
    $this->assertFieldVisibleOnEntityForm('Callback URL');
    $this->submitFormDisplay(['callbackUrl' => 'hidden']);
    $this->assertFieldVisibleOnEntityForm('Callback URL', FALSE);
    $this->submitFormDisplay(['callbackUrl' => 'content']);
    $this->assertFieldVisibleOnEntityForm('Callback URL');
  }

  /**
   * Tests the view regions.
   */
  protected function viewRegionTest() {
    $callbackUrl = 'https://' . strtolower($this->randomMachineName()) . '.example.com';
    $this->developerApp->setCallbackUrl($callbackUrl);
    $this->developerApp->save();

    $assert = function (bool $visible = TRUE) use ($callbackUrl) {
      $this->assertFieldVisibleOnEntityDisplay($this->developerApp->getName(), 'Callback URL', $callbackUrl, $visible);
    };

    $this->submitViewDisplay(['callbackUrl' => 'content']);
    $assert(TRUE);
    $this->submitViewDisplay(['callbackUrl' => 'hidden']);
    $assert(FALSE);
  }

  /**
   * Tests showing and hiding credentials on the developer app view.
   */
  protected function credentialsViewTest() {
    $assert = function (bool $visible = TRUE) {
      $this->assertFieldVisibleOnEntityDisplay($this->developerApp->getName(), 'Credential', 'Key Status', $visible);
    };

    $this->submitViewDisplay(['credentials' => 'hidden']);
    $assert(FALSE);
    $this->submitViewDisplay(['credentials' => 'content']);
    $assert(TRUE);
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
    $this->drupalPostForm(Url::fromRoute('entity.developer_app.field_ui_fields'), [
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
    $this->drupalPostForm(Url::fromRoute('entity.entity_form_display.developer_app.default'), $edit, 'Save');

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
    $this->drupalPostForm(Url::fromRoute('entity.entity_view_display.developer_app.default'), $edit, 'Save');

    $this->assertSession()->pageTextContains('Your settings have been saved.');
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
    $this->drupalGet(Url::fromRoute('entity.developer_app.add_form_for_developer', [
      'user' => $this->account->id(),
    ]));
    $this->assertEquals(Response::HTTP_OK, $this->getSession()->getStatusCode());
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
    $this->drupalGet(Url::fromRoute('entity.developer_app.canonical_by_developer', [
      'user' => $this->account->id(),
      'app' => $app_name,
    ]));
    if ($visible) {
      $this->assertSession()->pageTextContains($field_label);
      $this->assertSession()->pageTextContains($field_value);
    }
    else {
      $this->assertSession()->pageTextNotContains($field_label);
      $this->assertSession()->pageTextNotContains($field_value);
    }
  }

}
