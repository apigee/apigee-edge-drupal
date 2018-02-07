<?php

namespace Drupal\Tests\apigee_edge\Functional;

use Apigee\Edge\Api\Management\Entity\App;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\field_ui\Tests\FieldUiTestTrait;

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

  protected function setUp() {
    $this->profile = 'standard';
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

}
