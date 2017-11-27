# apigee-drupal-module
A Drupal 8 module that turns a site into a developer portal for Apigee's API management product

# Testing

To run the tests, some environment variables are needed both for the script and the server. Creating a simple script is recommended:

```
#!/bin/bash

export APIGEE_EDGE_USERNAME=""
export APIGEE_EDGE_PASSWORD=""
export APIGEE_EDGE_ORGANIZATION=""
export APIGEE_EDGE_BASE_URL=""
php ./core/scripts/run-tests.sh --verbose --color --url "http://edge.test" ApigeeEdge
```

Change the `--url` parameter to the site's url (it defaults to localhost, and sometimes it breaks the tests).
