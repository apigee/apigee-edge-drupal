#!/usr/bin/env bash

set -e

if [[ -z "${LOGS_REPO_USER}" ]] || [[ -z "${LOGS_REPO_PASSWORD}" ]] || [[ -z "${LOGS_REPO_HOST}" ]] || [[ -z "${LOGS_REPO_NAME}" ]]; then
  echo "There is at least one missing information about the destination repo. Make sure the following environment variables exist and not empty: LOGS_REPO_USER, LOGS_REPO_PASSWORD, LOGS_REPO_HOST, LOGS_REPO_NAME."
  exit 0
fi

# Initial GIT setup.
git config --global user.email "travis@travis-ci.org"
git config --global user.name "Travis CI"
# Copy logs from the PHP container.
docker cp $(docker ps -f "name=.*_php" --format "{{.ID}}"):/mnt/files/log .
cd log
# Commit and push logs to the git repo.
git init
BRANCH_NAME=${TRAVIS_JOB_NUMBER}-$(date +"%y%m%d-%H%M")
git checkout -b ${BRANCH_NAME}
git add .
git commit -am "Travis build: ${TRAVIS_JOB_NUMBER}"
git remote add origin https://${LOGS_REPO_USER}:${LOGS_REPO_PASSWORD}@${LOGS_REPO_HOST}/${LOGS_REPO_USER}/${LOGS_REPO_NAME}.git
git push -u origin ${BRANCH_NAME}
