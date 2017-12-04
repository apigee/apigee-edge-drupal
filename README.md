# apigee-drupal-module
A Drupal 8 module that turns a site into a developer portal for Apigee's API management product

# Testing

To run the tests, some environment variables are needed both for the script and the server. Creating a simple script in the `docroot` folder is recommended:

```
#!/bin/bash

export APIGEE_EDGE_USERNAME=""
export APIGEE_EDGE_PASSWORD=""
export APIGEE_EDGE_ORGANIZATION=""
export APIGEE_EDGE_BASE_URL=""

./../bin/phpunit --verbose --color --group ApigeeEdge
```

Create the `phpunit.xml` file in the `docroot` folder and fill in `SIMPLETEST_DB, SIMPLETEST_BASE_URL, BROWSERTEST_OUTPUT_DIRECTORY`:
```
<?xml version="1.0" encoding="UTF-8"?>   
<phpunit bootstrap="./core/tests/bootstrap.php" colors="true"
         beStrictAboutTestsThatDoNotTestAnything="true"
         beStrictAboutOutputDuringTests="true"
         beStrictAboutChangesToGlobalState="true"
         checkForUnintentionallyCoveredCode="false"
         printerClass="\Drupal\Tests\Listeners\HtmlOutputPrinter">
   
  <php>
    <ini name="error_reporting" value="32767"/>
    <ini name="memory_limit" value="-1"/>
    <env name="SIMPLETEST_BASE_URL" value="http://edge.test"/>
    <env name="SIMPLETEST_DB" value="mysql://drupal@localhost/drupal"/>
    <env name="BROWSERTEST_OUTPUT_DIRECTORY" value="./sites/default/files/test"/>
  </php>
  <testsuites>
    <testsuite name="unit">
      <file>./core/tests/TestSuites/UnitTestSuite.php</file>
    </testsuite>
    <testsuite name="kernel">
      <file>./core/tests/TestSuites/KernelTestSuite.php</file>
    </testsuite>
    <testsuite name="functional">
      <file>./core/tests/TestSuites/FunctionalTestSuite.php</file>
    </testsuite>
    <testsuite name="functional-javascript">
      <file>./core/tests/TestSuites/FunctionalJavascriptTestSuite.php</file>
    </testsuite>
  </testsuites>
  <listeners>
    <listener class="Symfony\Bridge\PhpUnit\SymfonyTestsListener">
    </listener>
    <listener class="\Drupal\Tests\Listeners\DrupalStandardsListener">
    </listener>
    <listener class="\Drupal\Tests\Listeners\DrupalComponentTestListener">
    </listener>
  </listeners>
  <filter>
    <whitelist>
      <directory>./core/includes</directory>
      <directory>./core/lib</directory>
      <directory>./core/modules</directory>
      <directory>./modules</directory>
      <directory>./sites</directory>
      <exclude>
        <directory suffix="Test.php">./</directory>
        <directory suffix="TestBase.php">./</directory>
      </exclude>
    </whitelist>
  </filter>
  </phpunit>
```
