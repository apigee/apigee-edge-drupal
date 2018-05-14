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

This project follows [Google's Open Source Community
Guidelines](https://opensource.google.com/conduct/).

# Suggested contributing workflow

## For start
* Fork this project on Github.
* If you do not have an Apigee Edge trial organization please create a new one
[here](https://login.apigee.com/login).
* Register on https://travis-ci.org .
* Open https://travis-ci.org/[YOUR-GITHUB-USERNAME]/apigee-client-php and click
on "Activate repository".
* Open https://travis-ci.org/[YOUR-GITHUB-USERNAME]/apigee-client-php/settings
and setup required environment variables for running tests. (See the list of
required environment variables in [README.md's Testing section](README.md#testing).)

## For daily work
* Create a new branch in your fork repository, ex.: patch-1.
* Add changes to the code. If you implement new features please always add new
tests that covers the implemented functionality. If you modify existing features please always update related tests if needed.
* Push your changes to your repo's patch-1 branch.
* Wait until all Travis CI test jobs finish and _pass_. (If any of them fails
please try to restart them once or twice because it could happen that they 
ailed because of an API communication error. You can identify these type of
issues from logs.)
* Create [new pull request](https://github.com/apigee/apigee-edge-drupal/pull/new/8.x-1.x)
and do not forget to add a link to Travis CI build that can confirm your code is working.

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
Please *temporary* add required changes as patches to module's composer.json.
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
        },
```
**Note:** Apigee Client Library for PHP patches should be removed from the
module's composer.json before the next stable release. Code changes cannot be
merged until the related PR(s) have not been released in a new stable version of
the Apigee Client Library for PHP.
