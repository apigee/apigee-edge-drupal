#!/bin/bash
# Because bash is missing from any defined path in PATH (like /usr/bin).
# #!/usr/bin/env bash

set -e

# Initial GIT setup.
git config --global user.email "travis@travis-ci.org"
git config --global user.name "Travis CI"
# Copy logs from the PHP container.
docker cp my_project_php:/mnt/files/log .
cd log
# Commit and push logs to the git repo.
git init
BRANCH_NAME=${TRAVIS_JOB_NUMBER}-$(date +"%y%m%d-%H%M")
git checkout -b ${BRANCH_NAME}
git add .
git commit -am "Travis build: ${TRAVIS_JOB_NUMBER}"
git remote add origin https://${LOGS_REPO_USER}:${LOGS_REPO_PASSWORD}@${LOGS_REPO_HOST}/${LOGS_REPO_USER}/${LOGS_REPO_NAME}.git
git push -u origin ${BRANCH_NAME}
