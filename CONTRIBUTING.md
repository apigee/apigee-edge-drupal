# How to Contribute

We'd love to accept your patches and contributions to this project. There are
just a few small guidelines you need to follow.

## Contributor License Agreement

Contributions to this project must be accompanied by a Contributor License
Agreement. You (or your employer) retain the copyright to your contribution,
this simply gives us permission to use and redistribute your contributions as
part of the project. Head over to <https://cla.developers.google.com/> to see
your current agreements on file or to sign a new one.

You generally only need to submit a CLA once, so if you've already submitted one
(even if it was for a different project), you probably don't need to do it
again.

## Code reviews

All submissions, including submissions by project members, require review. We
use GitHub pull requests for this purpose. Consult
[GitHub Help](https://help.github.com/articles/about-pull-requests/) for more
information on using pull requests.

## Community Guidelines

This project follows [Google's Open Source Community Guidelines](https://opensource.google.com/conduct/).

# Suggested contributing workflow

## To start
* Fork this project on Github.
* If you do not have an Apigee Edge trial organization, create a new one
[here](https://login.apigee.com/login).
* Register on https://circleci.com using your GitHub account.
* Install the module from for your fork instead of Drupal.org on your local. (See below.)

## For daily work
* Create a new branch in your fork repository. It is best to name your branch something descriptive, ex.: issue-12.
* Add changes to the code. If you implement new features, add new
tests to cover the implemented functionality. If you modify existing features, update related tests.
* Push your changes to your repo's branch.
* Wait until all CircleCI test jobs finish and _pass_. (If any of them fails
restart them once or twice. They may have failed due to an API communication error. You can
identify these type of issues from logs.)
* Create [new pull request](https://github.com/apigee/apigee-edge-drupal/pull/new/8.x-1.x). CircleCI will
automatically report the status of each of the CI jobs directly to the GitHub PR.

## Installing module from your fork instead of Drupal.org

Create a new branch on Github.com in your fork for your fix, ex.: issue-12.

Update your composer.json and install the module from your fork:
```bash
cd [DRUPAL_ROOT]
composer config repositories.forked-apigee_edge vcs https://github.com/[YOUR-GITHUB-USERNAME]/apigee-edge-drupal
composer require drupal/apigee_edge:dev-issue-12 # It is important to require a branch/tag here that does not exist in the Drupal.org repo otherwise code gets pulled from there. For example, dev-8.x-1.x condition would pull the code from Drupal.org repo instead of your fork.
```

If you would like to keep your fork always up-to-date with recent changes in
upstream, then add Apigee repo as a remote (one time only):

```bash
cd [DRUPAL_ROOT]/modules/contrib/apigee_edge
git remote add upstream https://github.com/apigee/apigee-edge-drupal.git
git fetch upstream
```

For daily work, rebase your current working branch to get the latest changes from
upstream:

```bash
cd [DRUPAL_ROOT]/modules/contrib/apigee_edge
git fetch upstream
git rebase upstream/8.x-1.x
```

After you have installed the module from your fork you can easily create new
branches for new fixes on your local:

```bash
cd [DRUPAL_ROOT]/modules/contrib/apigee_edge
git fetch upstream
git checkout -b issue-12 upstream/8.x-1.x

# Add your awesome changes.
# Do not forget to update tests or write additional test cases if needed.
# Run all tests provided by the module. (See "Running tests" section.)

# Fix code style issues.
# Apply automatic code style fixes with PHPCBF.
vendor/bin/phpcbf --standard=web/modules/contrib/apigee_edge/phpcs.xml.dist web/modules/contrib/apigee_edge -s --colors
# Check remaining code style issues with PHPCS and fix them manually.
# Fix all reported violations with "error" severity.
vendor/bin/phpcs --standard=web/modules/contrib/apigee_edge/phpcs.xml.dist web/modules/contrib/apigee_edge -p -s -n --colors

### Push changes to your repo and create new PR on Github.
git push -u origin issue-12:issue-12
```

## Set up environment variables

Before you could start testing this module locally, some environment variables
need to be set on your system. These variables are:

* `APIGEE_EDGE_AUTH_TYPE`
* `APIGEE_EDGE_ENDPOINT`
* `APIGEE_EDGE_ORGANIZATION`
* `APIGEE_EDGE_USERNAME`
* `APIGEE_EDGE_PASSWORD`


Value of `APIGEE_EDGE_AUTH_TYPE` should be set to either 'basic' or 'oauth'.  If you select `oauth` and have a SAML enabled org you will also need to set `APIGEE_EDGE_AUTHORIZATION_SERVER`, `APIGEE_EDGE_CLIENT_ID`, `APIGEE_EDGE_CLIENT_SECRET` values.

Value of `APIGEE_EDGE_USERNAME` should be an email address of an Apigee Edge user with **Organization administrator role** if you do not want to bump into permission issues in tests. Tests failed with "Forbidden" could be a sign of the insufficient permissions.

You can set these environment variables in multiple ways:
- Copy the `phpunit.core.xml.dist` file included with this module as `core/phpunit.xml`. Uncomment the `APIGEE_*`
environment variables and replace with real values.
- Use `export` or `set` in the terminal to define the variables.

### Notes for testing using a Hybrid organization

If testing with a Hybrid organization, only the following three environment variables are required:

* `APIGEE_EDGE_INSTANCE_TYPE`: should be `hybrid`.
* `APIGEE_EDGE_ORGANIZATION`
* `APIGEE_EDGE_ACCOUNT_JSON_KEY`: the JSON encoded GCP service account key.

If you wish to run tests both against a Public and a Hybrid instance:

1. First configure the credentials to the public org as described above.
2. Add the `APIGEE_EDGE_ACCOUNT_JSON_KEY` environment variable.
3. Add a`APIGEE_EDGE_HYBRID_ORGANIZATION` environment variable, which specifies the Hybrid organization to use for tests.

## Install development dependencies

Composer only installs the packages listed as `require-dev` of your master `composer.json` file, so you may
need to copy this module's `require-dev` dependencies to your main `composer.json` file to install all
development requirements to run the tests.

## Running tests

After you have the environment variables and dependencies set up, you can execute tests of this
module with the following command (note the location of the `phpunit` executable
may vary):

```sh
cd [DRUPAL_ROOT]
# Run all tests from apigee_edge.
./vendor/bin/phpunit -c core --verbose --color --group apigee_edge

# Example to run a single test:
./vendor/bin/phpunit -c core --verbose --color modules/contrib/apigee_edge/tests/src/Kernel/EntityControllerCacheTest.php
```

If you have CircleCI CLI and Docker installed on your system you can also run
PHPUnit tests with the following commands:

```bash
cd [DRUPAL_ROOT]/modules/contrib/apigee_edge/
circleci local execute --job [JOB_NAME]
```

- Note: Replace `[JOB_NAME]` with the name of the job that you want to run locally. Examples:
`run-unit-kernel-tests-8`, `run-functional-tests-9`, etc.

You can read more about running Drupal 8 PHPUnit tests [here](https://www.drupal.org/docs/8/phpunit/running-phpunit-tests).

### Troubleshooting

**If a test is passing on your local, but it is failing on CircleCI:**
1. Try to restart failing job(s) one or two times, failing tests could be caused by communication issues.
2. If (1) did not work, try to run the failing test(s) on your local with the above described CircleCI CLI.

### If your pull request relies on changes that are not yet available in Apigee Edge Client Library for PHP's latest stable release
You should *temporarily* add required changes as patches to module's composer.json.
This way this module's tests could pass on CircleCI.

#### Example:

You can easily get a patch file from any Github pull requests by adding `.diff`
to end of the URL.

Pull request: https://github.com/apigee/apigee-client-php/pull/1
Patch file: https://github.com/apigee/apigee-client-php/pull/1.diff

composer.json:

```js
        "patches": {
            "apigee/apigee-client-php": {
                "Fix for a bug": "https://patch-diff.githubusercontent.com/raw/apigee/apigee-client-php/pull/1.diff"
            }
        }
```

**Note:** Apigee Client Library for PHP patches should be removed from the
module's composer.json before the next stable release. Code changes cannot be
merged until the related PR(s) have been released in a new stable version of
the Apigee Client Library for PHP.

#### Tests using the Mock API Client

Tests are being refactored to use a mock API client that uses stacked or matched responses instead of connecting and
querying the real API. Refer to [`tests/modules/apigee_mock_api_client/README.md`](tests/modules/apigee_mock_api_client/README.md)
for documentation.
