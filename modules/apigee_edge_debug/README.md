# Debug helper for Apigee Edge

An utility module that helps developers to work with the
Apigee Edge module.


## Features

- Log requests, responses and transfer statistics of API calls made by the module.


### Known issues

- API call logging does not work if Devel module's Webprofiler submodule is
enabled, because it overrides this module's [on_stats](http://docs.guzzlephp.org/en/stable/request-options.html#on-stats) callback.
Drupal.org Issue: https://www.drupal.org/project/devel/issues/2948701
