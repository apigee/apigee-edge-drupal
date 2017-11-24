# apigee-drupal-module
A Drupal 8 module that turns a site into a developer portal for Apigee's API management product

# Testing

To run the tests, some environment variables are needed. Creating a simple script is recommended:

```
#!/bin/bash

export EDGE_USERNAME=""
export EDGE_PASSWORD=""
export EDGE_ORGANIZATION=""
php ./core/scripts/run-tests.sh --verbose --color --url "http://edge.test" ApigeeEdge
```

Change the `--url` parameter to the site's url (it defaults to localhost, and sometimes it breaks the tests).
