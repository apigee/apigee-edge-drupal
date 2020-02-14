Apigee Edge Mock API Client Testing Module
---

This module allows you to test apigee_edge functionality without making external API calls, by providing response templates
that can be queued up or matched in a mock Guzzle client stack handler.

**Note:** This module should not be installed on a standard Drupal site, it should only be enabled during testing.

### Requirements

* This module depends on the `apigee_edge` module.

### Writing tests with the Apigee Edge Mock API Client

First declare the `APIGEE_INTEGRATION_ENABLE` environment variable. It will be evaluated as a boolean:
  - If true, the (refactored) tests will run against the real API.
  - If false or undefined, they will use the mock responses.

To refactor a test and use the mock API client, or write a new test that uses it:

* Functional tests will most likely use `ApigeeEdgeFunctionalTestTrait`, which already has the logic to use the mock
client. All that is needed is overriding and setting the following property to "true" in the test class:

```
  protected static $mock_api_client_ready = TRUE;
```

* Kernel tests:
  -  During test initialization, enable the `apigee_mock_api_client` module. The easiest way is by overriding the
  property `$modules` array to include it.
  - Use `Drupal\Tests\apigee_mock_api_client\Traits\ApigeeMockApiClientHelperTrait` in the test class. It provides
  helper methods to initialize and use the mock API client.
  - Call `$this->apigeeTestHelperSetup()` to set the client up.

### Mock responses

Three types of mock responses are available:

- [Stacked responses with only an HTTP code (no body).](#stacked-responses-with-only-an-http-code-no-body)
- [Stacked responses created from twig templates.](#stacked-responses-created-from-twig-templates)
- [Matched responses by path, hostname, methods, or schemes.](#matched-responses-by-path-hostname-methods-or-schemes)

When the mock client receives a request, it first checks if there is a matched response, and if not, it returns the next
stacked response (stacked responses are queued and returned in a FIFO way). If no response is available, it will return
an error.

#### Stacked responses with only an HTTP code (no body)

There is a catalog of response codes for when only an HTTP code needs to be returned. A full list of the response
catalog can be found under [`response_catalog.yml`](response_catalog.yml).
A response can be added to the stack like:

```
// Stack a 204 (no content) response.
$this->stack->queueMockResponse('no_content');

// Stack a 404 (not found) response.
$this->stack->queueMockResponse('get_not_found');
```

#### Stacked responses created from twig templates

Responses can also be created from twig templates. This module provides some base responses - see [`tests/response-templates/`](tests/response-templates/).
Other modules can add additional templates by storing them in their `[MODULE_NAME]/tests/response-templates` directory.

Note that the  `queueMockResponse()` method expects the template name without file extensions, and any `_` characters
will be replaced by `-` (eg. `$template = 'api_product';` would load a template named `api-product.json.twig`).

Example:

```
// Stack a company response from the "company.json.twig" template.
$context['company'] = $company; // An \Apigee\Edge\Api\Management\Entity\Company object.
$context['org_name'] = 'test-org';
$context['status_code'] = 201; // Defaults to 200 if undefined.
$template_name = 'company'; // Will load the template company.json.twig
$this->stack->queueMockResponse([$template_name => $context]);
```

#### Matched responses by path, hostname, methods, or schemes

Last, responses can also be added to be "matched" by path, hostname, methods, or schemes. Note that path and hostname
parameters are regular expressions. Example:

```
$organization = new \Apigee\Edge\Api\Management\Entity\Organization(['name' => $organizationName]);
$host = NULL; // Match any host, as the mock client is only used for Apigee Edge API calls.
$methods = ['GET', 'PUT', 'DELETE'];
$entitySource = new \Apigee\MockClient\Generator\ApigeeSdkEntitySource\ApigeeSdkEntitySource($organization);
$this->stack->on(
  new RequestMatcher("/v1/organizations/{$organization->id()}$", $host, $methods),
  $this->mockResponseFactory->generateResponse($entitySource)
);
```

Note: The regular expression for path should include the full path whenever possible, including the prefix `/v1`.

#### Other methods

To completely clear all stacked, matched responses and logs from the mock client, use:

```
$this->stack->reset();
```

For convenience, helper methods have been added to `ApigeeMockApiClientHelperTrait`, such as:

- `addOrganizationMatchedResponse($organizationName = '')`
- `queueDeveloperResponse(UserInterface $developer, $response_code = NULL, array $context = [])`
- `queueCompanyResponse(Company $company, $response_code = NULL)`

See [ApigeeMockApiClientHelperTrait](tests/src/Traits/ApigeeMockApiClientHelperTrait.php) for a full list of helper
methods and documentation on usage.
