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
* Register on https://travis-ci.org.
* Open https://travis-ci.org/[YOUR-GITHUB-USERNAME]/apigee-edge-drupal and click
on "Activate repository".
* Open https://travis-ci.org/[YOUR-GITHUB-USERNAME]/apigee-edge-drupal/settings
and setup required environment variables for running tests. (See the list of
required environment variables in the [Testing](#testing) section.)
* Install the module from for your fork instead of Drupal.org on your local. (See below.)

## For daily work
* Create a new branch in your fork repository, ex.: patch-1.
* Add changes to the code. If you implement new features, add new
tests to cover the implemented functionality. If you modify existing features, update related tests.
* Push your changes to your repo's patch-1 branch.
* Wait until all Travis CI test jobs finish and _pass_. (If any of them fails
restart them once or twice. They may have failed due to an API communication error. You can
identify these type of issues from logs.)
* Create [new pull request](https://github.com/apigee/apigee-edge-drupal/pull/new/8.x-1.x)
and do not forget to add a link to Travis CI build that can confirm your code is working.

## Installing module from your fork instead of Drupal.org

Create a new branch on Github.com in your fork for your fix, ex.: patch-1.

Update your composer.json and install the module from your fork:
```bash
cd [DRUPAL_ROOT]
composer config repositories.forked-apigee_edge vcs https://github.com/[YOUR-GITHUB-USERNAME]/apigee-edge-drupal
composer require drupal/apigee_edge:dev-patch-1 # It is important to require a branch/tag here that does not exist in the Drupal.org repo otherwise code gets pulled from there. For example, dev-8.x-1.x condition would pull the code from Drupal.org repo instead of your fork.
```

If you would like to keep your fork always up-to-date with recent changes in
upstream then add Apigee repo as a remote (one time only):

```bash
cd [DRUPAL_ROOT]/modules/contrib/apigee_edge
git remote add upstream https://github.com/apigee/apigee-edge-drupal.git
git fetch upstream
```

For daily bases, rebase your current working branch to get latest changes from
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
git checkout -b patch-2 upstream/8.x-1.x

# Add your awesome changes.
# Do not forget to write additional test cases when it is needed and
# run all tests provided by the module. (See "Running tests" section.)

# Fix code style issues.
# Apply automatic code style fixes with PHPCBF.
vendor/bin/phpcbf --standard=web/modules/contrib/apigee_edge/phpcs.xml.dist web/modules/contrib/apigee_edge -s --colors
# Check remaining code style issues with PHPCS and fix them manually.
# Fix all reported violations with "error" severity.
# (There are some false-positive violations with "warning" severity reported by PHPCS by default.)
vendor/bin/phpcs --standard=web/modules/contrib/apigee_edge/phpcs.xml.dist web/modules/contrib/apigee_edge -s --colors

### Push changes to your repo and create new PR on Github.
git push -u origin patch-2:patch-2
```

## Running tests

Before you could start testing this module some environment variables
needs to be set on your system. These variables are:

* `APIGEE_EDGE_AUTH_TYPE`
* `APIGEE_EDGE_ENDPOINT`
* `APIGEE_EDGE_ORGANIZATION`
* `APIGEE_EDGE_USERNAME`
* `APIGEE_EDGE_PASSWORD`


Value of `APIGEE_EDGE_AUTH_TYPE` should be set to either 'basic' or 'oauth'.  If you select `oauth` and have a SAML enabled org you will also need to set `APIGEE_EDGE_AUTHORIZATION_SERVER`, `APIGEE_EDGE_CLIENT_ID`, `APIGEE_EDGE_CLIENT_SECRET` values.

Value of `APIGEE_EDGE_USERNAME` should be an email address of an Apigee Edge user with **Organization administrator role** if you do not want to bump into permission issues in tests. Tests failed with "Forbidden" could be a sign of the insufficient permissions.

You can set these environment variables multiple ways, either by defining them
with `export` or `set` in the terminal or creating a copy of the `core/phpunit.xml.dist`
file as `core/phpunit.xml` and specifying them in that file.

After you have these environment variables set you can execute tests of this
module with the following command (note the location of the `phpunit` executable
may vary):

```sh
./vendor/bin/phpunit -c core --verbose --color --group apigee_edge
```

If you have Docker and Docker Compose installed on your system you can also run
PHPUnit tests with the following commands:

```bash
cd [DRUPAL_ROOT]/modules/contrib/apigee_edge/.travis
docker-compose up --build -d # Build is important because recent changes on module files have to be copied from the host to the container.
docker-compose run php /opt/drupal-module/.travis/run-test.sh # to run all tests of this module. This command performs some initial setup tasks if test environment has not been configured yet.
docker-compose run php /opt/drupal-module/.travis/run-test.sh --filter testAppSettingsForm AppSettingsFormTest build/modules/contrib/apigee_edge/tests/src/FunctionalJavascript/AppSettingsFormTest.php # to run one specific test. If you pass any arguments to run-test.sh those get passed directly to PHPUnit. See [.travis/run-test.sh](run-test.sh).
docker-compose down --remove-orphans -v # Intermediate data (like module files) must be cleared from the shared volumes otherwise recent changes won't be visible in the container.
```

You can read more about running Drupal 8 PHPUnit tests [here](https://www.drupal.org/docs/8/phpunit/running-phpunit-tests).

### Troubleshooting

**If a test is passing on your local but it is failing on Travis CI.**
1. Try to restart failing job(s) one or two times, failing tests could be caused by communication issues.
2. If 1. did not work try to run the failing test(s) on your local in the above described
Docker based environment because this what Travis CI also uses for running tests.

## Best practices

## Accessing logs and browser outputs created by failed tests on Travis
You can access Drupal logs, browser outputs, and Apigee Edge module debug logs
created by tests if you set the following environment variables:
* LOGS_REPO_USER
* LOGS_REPO_PASSWORD
* LOGS_REPO_HOST
* LOGS_REPO_NAME

By using these environment variables Travis tries to push logs to this
repository URL:
`https://${LOGS_REPO_USER}:${LOGS_REPO_PASSWORD}@${LOGS_REPO_HOST}/${LOGS_REPO_USER}/${LOGS_REPO_NAME}.git`

### If your pull request relies on changes that are not yet available in Apigee Edge Client Library for PHP's latest stable release
You should *temporarily* add required changes as patches to module's composer.json.
This way this module's tests could pass on Travis CI.

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
merged until the related PR(s) have not been released in a new stable version of
the Apigee Client Library for PHP.
