<?php

namespace Drupal\Tests\apigee_edge\Functional;

use Apigee\Edge\Api\Management\Entity\Developer;
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
   * The DeveloperController object.
   *
   * @var \Apigee\Edge\Api\Management\Controller\DeveloperController
   */
  protected $developerController;

  public static $modules = [
    'apigee_edge',
  ];

  /**
   * Initializes the credentials property.
   *
   * @return bool
   *   True if the credentials are successfully initialized.
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

    $this->developerController = $this->container->get('apigee_edge.sdk_connector')->getControllerByEntity('developer');
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Tests environment credentials storage.
   */
  public function testCredentialsStorages() {
    // Test private file storage.
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

    $developer_data = [
      'userName' => 'UserByAdmin',
      'email' => 'edge.functional.test@pronovix.com',
      'firstName' => 'Functional',
      'lastName' => "Test",
    ];

    $developer = new Developer($developer_data);
    $this->developerController->create($developer);

    /** @var Developer $developer */
    $developer = $this->developerController->load($developer_data['email']);
    $this->assertEquals($developer->getEmail(), $developer_data['email']);

    // Test env storage.
    $this->drupalGet('/admin/config/apigee_edge');

    $formdata = [
      'credentials_storage_type' => 'credentials_storage_env',
    ];

    $this->submitForm($formdata, t('Send request'));
    $this->assertSession()->pageTextContains(t('Connection successful'));

    $this->submitForm($formdata, t('Save configuration'));
    $this->assertSession()->pageTextContains(t('The configuration options have been saved'));

    $developer = $this->developerController->load($developer_data['email']);
    $this->assertEquals($developer->getEmail(), $developer_data['email']);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    parent::tearDown();
    try {
      $this->developerController->delete('edge.functional.test@pronovix.com');
    }
    catch (\Exception $ex) {

    }
  }

}
