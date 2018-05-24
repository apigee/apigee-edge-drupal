| Q                           | A
| --------------------------- | -----------------------------------------------
| Bug?                        | no|yes
| New Feature?                | no|yes
| Module Version              | Version or commit SHA <br> This can be the version you can see on admin/modules in Drupal or the output of this command: <code>composer show | grep drupal/apigee_edge | awk '{if ($2 ~ "dev$") print $2 " "$3; else print $2;}'</code>
| PHP Client version          | Version or commit SHA <br> This can be the output of this command: <code>composer show | grep apigee/apigee-client-php | awk '{if ($2 ~ "dev$") print $2 " "$3; else print $2;}'</code>
| Requires PHP client fix     | no|link to an Apigee Client PHP library issue or pull request

#### Actual Behavior

What is the actual behavior?


#### Expected Behavior

What is the behavior you expect?


#### Steps to Reproduce the behavior

What are the steps to reproduce this bug? Please add code examples,
screenshots or links to GitHub repositories that can demonstrate the problem.


#### Proposed Solution

If you have already ideas how to solve the issue please describe it here.
(Remove this section if it is not needed.)
