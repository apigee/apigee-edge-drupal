<?php

// @codingStandardsIgnoreStart

/**
 * Base tasks for setting up a module to test within a full Drupal environment.
 *
 * This file expects to be called from within the module directory, and for a
 * Drupal installation to be located in the parent directory. In other words,
 * this module should be in /modules and not /sites/all/modules or other
 * locations.
 *
 * @class RoboFile
 * @codeCoverageIgnore
 */
class RoboFile extends \Robo\Tasks
{

  /**
   * RoboFile constructor.
   */
  public function __construct()
  {
    // Treat this command like bash -e and exit as soon as there's a failure.
    $this->stopOnFail();
  }

  /**
   * Files which we don't want to copy into the module directory.
   */
  static $excludeFiles = [
    '.',
    '..',
    'vendor',
    'RoboFile.php',
    '.git',
    '.idea',
  ];

  /**
   * Set up the Drupal skeleton.
   */
  public function setupSkeleton()
  {
    // composer config doesn't allow us to set arrays, so we have to do this by
    // hand.
    $config = json_decode(file_get_contents('composer.json'));
    $config->extra->{"enable-patching"} = 'true';
    $config->extra->{"patches"} = new \stdClass();
    unset($config->require->{"wikimedia/composer-merge-plugin"});
    $config->extra->{"drupal-scaffold"} = new \stdClass();
    $config->extra->{"drupal-scaffold"}->{"locations"} = (object) [
      'web-root' => '.',
    ];

    file_put_contents('composer.json', json_encode($config));

    // Create a directory for our artifacts.
    $this->taskFilesystemStack()
      ->mkdir('artifacts')
      ->mkdir('artifacts/phpcs')
      ->mkdir('artifacts/phpmd')
      ->mkdir('artifacts/phpmetrics')
      ->mkdir('artifacts/d9')
      ->mkdir('/tmp/artifacts/phpunit')
      ->mkdir('/tmp/artifacts/phpmd')
      ->run();

    $this->taskFilesystemStack()
      ->chown('/tmp/artifacts', 'www-data', TRUE)
      ->copy('modules/apigee_edge/.circleci/d9.sh', '/var/www/html/d9.sh')
      ->chmod('/var/www/html/d9.sh', 0777)
      ->run();
  }

  /**
   * Adds coding standard dependencies.
   */
  public function addCodingStandardsDeps()
  {
    $config = json_decode(file_get_contents('composer.json'));
    $config->require->{"drupal/coder"} = "^2.0|^8.2";
    file_put_contents('composer.json', json_encode($config));
  }

  /**
   * Adds Behat dependencies.
   */
  public function addBehatDeps()
  {
    $config = json_decode(file_get_contents('composer.json'));
    // Package behat/mink-selenium2-driver is included in  drupal/core-dev.
    $config->require->{"drupal/drupal-extension"} = "master-dev";
    $config->require->{"guzzlehttp/guzzle"} = "^6.0@dev";
    file_put_contents('composer.json', json_encode($config));
  }

  /**
   * Adds modules to the merge section.
   *
   * @param array $modules
   *   The list of modules.
   */
  public function addModules(array $modules)
  {
    $config = json_decode(file_get_contents('composer.json'));
    $config->extra->{"merge-plugin"}->{"ignore-duplicates"} = TRUE;

    foreach ($modules as $module) {
      list($module,) = explode(':', $module);
      $config->extra->{"merge-plugin"}->include[] = "modules/$module/composer.json";
      $base = isset($config->extra->{"patches"}) ?  (array)$config->extra->{"patches"} : [];
      $config->extra->{"patches"} = (object)array_merge($base,
        (array)$this->getPatches($module));
    }

    file_put_contents('composer.json', json_encode($config));
  }

  /**
   * Adds another composer.json requires and requires-dev to this project.
   *
   * @param string $composerFilePath
   *   Path to the composer.json file to merge.
   */
  public function addDependenciesFrom(string $composerFilePath)
  {
    $config = json_decode(file_get_contents('composer.json'));
    $additional = json_decode(file_get_contents($composerFilePath));

    if (!empty($additional->require)) {
      foreach ($additional->require as $key => $value) {
        if (!isset($config->require->{$key})) {
          $config->require->{$key} = $value;
        }
      }
    }
    if (!empty($additional->{"require-dev"})) {
      foreach ($additional->{"require-dev"} as $key => $value) {
        if (!isset($config->{"require-dev"}->{$key})) {
          if (!isset($config->{"require-dev"})) {
            $config->{"require-dev"} = new \stdClass();
          }
          $config->{"require-dev"}->{$key} = $value;
        }
      }
    }

    file_put_contents('composer.json', json_encode($config));
  }

  /**
   * Adds contrib modules to the require section.
   *
   * @param array $modules
   *   The list of modules.
   */
  public function addContribModules(array $modules)
  {
    $config = json_decode(file_get_contents('composer.json'));

    foreach ($modules as $module) {
      list($module, $version) = explode(':', $module);
      $config->require->{"drupal/" . $module} = $version;
    }

    file_put_contents('composer.json', json_encode($config));
  }

  /**
   * Updates modules.
   *
   * @param array $modules
   *   The list of modules.
   */
  public function updateModules(array $modules)
  {
    $config = json_decode(file_get_contents('composer.json'));

    // Rebuild the patches array.
    $config->extra->{"patches"} = new \stdClass();
    foreach ($modules as $module) {
      list($module,) = explode(':', $module);
      $config->extra->{"patches"} = (object)array_merge((array)$config->extra->{"patches"},
        (array)$this->getPatches($module));
    }

    file_put_contents('composer.json', json_encode($config));
  }

  /**
   * Updates contrib modules.
   *
   * @param array $modules
   *   The list of modules.
   */
  public function updateContribModules(array $modules)
  {
    // The implementation is the same as adding modules but for
    // readability we have this alias when updating sites.
    $this->addContribModules($modules);
  }

  /**
   * Updates composer dependencies.
   */
  public function updateDependencies()
  {
    // Disable xdebug.
    $this->taskExec('sed -i \'s/^zend_extension/;zend_extension/g\' /usr/local/etc/php/conf.d/xdebug.ini')
      ->run();

    // The git checkout includes a composer.lock, and running composer update
    // on it fails for the first time.
    $this->taskFilesystemStack()->remove('composer.lock')->run();

    // Remove all core files and vendor.
    $this->taskFilesystemStack()
      ->taskDeleteDir('core')
      ->taskDeleteDir('vendor')
      ->run();

    // Composer often runs out of memory when installing drupal.
    $this->taskComposerInstall('php -d memory_limit=-1 /usr/local/bin/composer')
      ->optimizeAutoloader()
      ->run();

    // Preserve composer.lock as an artifact for future debugging.
    $this->taskFilesystemStack()
      ->copy('composer.json', '/tmp/artifacts/composer.json')
      ->copy('composer.lock', '/tmp/artifacts/composer.lock')
      ->run();

    // Write drush status results to an artifact file.
    $this->taskExec('vendor/bin/drush status > /tmp/artifacts/core-stats.txt')
      ->run();
    $this->taskExec('cat /tmp/artifacts/core-stats.txt')
      ->run();

    // Add php info to an artifact file.
    $this->taskExec('php -i > /tmp/artifacts/phpinfo.txt')
      ->run();

    // Add composer version info to an artifact file.
    $this->taskExec('composer show')
      ->run();
    $this->taskExec('composer show > /tmp/artifacts/composer-show.txt')
      ->run();
  }

  /**
   * Returns an array of patches to apply for a given module.
   *
   * @param string $module
   *   The name of the module.
   *
   * @return \stdClass
   *   An object containing a list of patches to apply via Composer Patches.
   */
  protected function getPatches($module)
  {
    $path = 'modules/' . $module . '/patches.json';
    if (file_exists($path)) {
      return json_decode(file_get_contents($path));
    } else {
      return new stdClass();
    }
  }

  /**
   * Install Drupal.
   *
   * @param array $opts
   *   (optional) The array of options.
   */
  public function setupDrupal($opts = [
    'admin-user' => null,
    'admin-password' => null,
    'site-name' => null,
    'db-url' => 'mysql://root@127.0.0.1/drupal8',
  ]
  ) {
    $task = $this->drush()
      ->args('site-install')
      ->option('yes')
      ->option('db-url', $opts['db-url'], '=');

    if ($opts['admin-user']) {
      $task->option('account-name', $admin_user, '=');
    }

    if ($opts['admin-password']) {
      $task->option('account-pass', $admin_password, '=');
    }

    if ($opts['site-name']) {
      $task->option('site-name', $site_name, '=');
    }

    // Sending email will fail, so we need to allow this to always pass.
    $this->stopOnFail(false);
    $task->run();
    $this->stopOnFail();
  }

  /**
   * Return drush with default arguments.
   *
   * @return \Robo\Task\Base\Exec
   *   A drush exec command.
   */
  protected function drush()
  {
    // Drush needs an absolute path to the docroot.
    $docroot = $this->getDocroot();
    return $this->taskExec('vendor/bin/drush')
      ->option('root', $docroot, '=');
  }

  /**
   * Get the absolute path to the docroot.
   *
   * @return string
   */
  protected function getDocroot()
  {
    $docroot = (getcwd());
    return $docroot;
  }

  /**
   * Overrides phpunit's configuration with module specific one.
   *
   * @param string $module
   *   The module name where phpunit config files may be located.
   */
  public function overridePhpunitConfig($module)
  {
    $module_path = "modules/$module";
    // Copy in our custom phpunit.xml.core.dist file.
    if (file_exists("$module_path/phpunit.core.xml")) {
      $this->taskFilesystemStack()
        ->copy("$module_path/phpunit.core.xml", 'core/phpunit.xml')
        ->run();
    } elseif (file_exists("$module_path/phpunit.core.xml.dist")) {
      $this->taskFilesystemStack()
        ->copy("$module_path/phpunit.core.xml.dist", 'core/phpunit.xml')
        ->run();
    }
  }

  /**
   * Run PHPUnit and simpletests for the module.
   *
   * @param string $module
   *   The module name.
   */
  public function test($module)
  {
    $this->phpUnit($module)
      ->run();
  }

  /**
   * Run tests with code coverage reports.
   *
   * @param string $module
   *   The module name.
   * @param string $report_output_path
   *   The full path of the report to generate.
   */
  public function testCoverage($module, $report_output_path)
  {
    $this->phpUnit($module)
      ->option('coverage-xml', $report_output_path . '/coverage-xml')
      ->option('coverage-html', $report_output_path . '/coverage-html')
      ->option('testsuite', 'nonfunctional')
      ->run();
  }

  /**
   * Return a configured phpunit task.
   *
   * This will check for PHPUnit configuration first in the module directory.
   * If no configuration is found, it will fall back to Drupal's core
   * directory.
   *
   * @param string $module
   *   The module name.
   *
   * @return \Robo\Task\Testing\PHPUnit
   */
  private function phpUnit($module)
  {
    return $this->taskPhpUnit('vendor/bin/phpunit')
      ->option('verbose')
      ->option('debug')
      ->option('log-junit', '/tmp/artifacts/phpunit/phpunit.xml')
      ->configFile('core')
      ->group($module);
  }

  /**
   * Gathers coding standard statistics from a module.
   *
   * @param string $path
   *   Path were cs.json and cs-practice.json files have been stored
   *   by the container where phpcs was executed.
   *
   * @return string
   *   A short string with the total violations.
   */
  public function extractCodingStandardsStats($path)
  {
    $errors = 0;
    $warnings = 0;

    if (file_exists($path . '/cs.json')) {
      $stats = json_decode(file_get_contents($path . '/cs.json'));
      $errors += $stats->totals->errors;
      $warnings += $stats->totals->warnings;
    }

    return $errors . ' errors and ' . $warnings . ' warnings.';
  }

  /**
   * Gathers code coverage stats from a module.
   *
   * @param string $path
   *   Path to a Clover report file.
   *
   * @return string
   *   A short string with the coverage percentage.
   */
  public function extractCoverageStats($path)
  {
    if (file_exists($path . '/index.xml')) {
      $data = file_get_contents($path . '/index.xml');
      $xml = simplexml_load_string($data);
      $totals = $xml->project->directory->totals;
      $lines = (string)$totals->lines['percent'];
      $methods = (string)$totals->methods['percent'];
      $classes = (string)$totals->classes['percent'];
      return 'Lines ' . $lines . ' Methods ' . $methods . ' Classes ' . $classes;
    } else {
      return 'Clover report was not found at ' . $path;
    }
  }

  /**
   * Set the Drupal core version.
   *
   * @param int $drupalCoreVersion
   *   The major version of Drupal required.
   */
  public function drupalVersion($drupalCoreVersion)
  {
    $config = json_decode(file_get_contents('composer.json'));

    unset($config->require->{"drupal/core"});

    switch ($drupalCoreVersion) {
      case '9':
        $config->require->{"drupal/core-composer-scaffold"} = '^9.1@stable';
        $config->require->{"drupal/core-recommended"} = '^9.1@stable';
        $config->require->{"drupal/core-dev"} = '^9.1';
        $config->require->{"phpspec/prophecy-phpunit"} = '^2';

        break;

      case '8':
        $config->require->{"drupal/core-composer-scaffold"} = '^8.9@stable';
        $config->require->{"drupal/core-recommended"} = '^8.9@stable';
        $config->require->{"drupal/core-dev"} = '^8.9';

        // Add rules for testing apigee_edge_actions (only for D8).
        $config->require->{"drupal/rules"} = "3.0.0-alpha6";

        // We require Drupal drush and console for some tests.
        $config->require->{"drupal/console"} = "~1.0";

      default:
        break;
    }

    file_put_contents('composer.json', json_encode($config, JSON_PRETTY_PRINT));
  }

  /**
   * Adds modules to the merge section.
   */
  public function configureModuleDependencies()
  {
    $config = json_decode(file_get_contents('composer.json'));

    // If you require core, you must not replace it.
    unset($config->replace);

    // Unset scripts that delete vendor test directories.
    unset($config->scripts->{"post-package-install"});
    unset($config->scripts->{"post-package-update"});

    // You can't merge from a package that is required.
    foreach ($config->extra->{"merge-plugin"}->include as $index => $merge_entry) {
      if ($merge_entry === 'core/composer.json') {
        unset($config->extra->{"merge-plugin"}->include[$index]);
      }
    }
    $config->extra->{"merge-plugin"}->include = array_values($config->extra->{"merge-plugin"}->include);

    file_put_contents('composer.json', json_encode($config, JSON_PRETTY_PRINT));
  }

  /**
   * Perform extra tasks per Drupal core version.
   *
   * @param int $drupalCoreVersion
   *   The major version of Drupal required.
   */
  public function doExtra($drupalCoreVersion) {
    if ($drupalCoreVersion > 8) {

      // Delete D8 only modules.
      $this->taskFilesystemStack()
        ->taskDeleteDir('modules/apigee_edge/modules/apigee_edge_actions')
        ->run();
    }
  }

}
