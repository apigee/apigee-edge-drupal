<?php

namespace Drupal\apigee_edge\Tests;

use Drupal\Tests\BrowserTestBase;

/**
 * Edge account related tests.
 *
 * @group ApigeeEdge
 */
class EdgeAccountTest extends BrowserTestBase {

  /**
   * Credential storage.
   *
   * @var array
   */
  protected $credentials = [];

  /**
   * User with administrator permissions for the edge module.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser = NULL;

  public static $modules = [
    'apigee_edge',
  ];

  /**
   * Initializes the credentials property.
   *
   * @return bool
   */
  protected function initCredentials() : bool {
    if (($username = getenv('APIGEE_EDGE_USERNAME'))) {
      $this->credentials['username'] = $username;
    }
    if (($password = getenv('APIGEE_EDGE_PASSWORD'))) {
      $this->credentials['password'] = $password;
    }
    if (($organization = getenv('APIGEE_EDGE_ORGANIZATION'))) {
      $this->credentials['organization'] = $organization;
    }
    if (($base_url = getenv('APIGEE_EDGE_BASE_URL'))) {
      $this->credentials['base_url'] = $base_url;
    }

    return (bool) $this->credentials;
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    if (!$this->initCredentials()) {
      $this->markTestSkipped('credentials not found');
    }
    parent::setUp();

    $this->adminUser = $this->createUser(['administer apigee edge']);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests validating and saving the apigee edge account credentials.
   */
  public function testAccount() {
    $this->drupalGet('/admin/config/apigee_edge');

    $formdata = [
      'credentials_storage_type' => 'credentials_storage_private_file',
      'credentials_api_organization' => $this->credentials['organization'],
      'credentials_api_base_url' => $this->credentials['base_url'],
      'credentials_api_username' => $this->credentials['username'],
      'credentials_api_password' => $this->credentials['password'],
    ];

    $this->submitForm($formdata, t('Send request'));
    $this->assertSession()->pageTextContains(t('Connection successful'));

    $this->submitForm($formdata, t('Save configuration'));
    $this->assertSession()->pageTextContains(t('The configuration options have been saved'));
  }

  public function testEnvStorage() {
    $this->drupalGet('/admin/config/apigee_edge');

    $formdata = [
      'credentials_storage_type' => 'credentials_storage_env',
    ];

    $this->submitForm($formdata, t('Send request'));
    $this->assertSession()->pageTextContains(t('Connection successful'));

    $this->submitForm($formdata, t('Save configuration'));
    $this->assertSession()->pageTextContains(t('The configuration options have been saved'));
  }

}
