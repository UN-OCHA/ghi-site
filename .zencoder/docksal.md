---
description: "Use docksal"
alwaysApply: false
---

# Rule Content

Use "fin phpunit -s" instead of "vendor/bin/phpunit" when you want to run tests.
The additional "-s" flag is important to have the tests run sequentially directly using phpunit. Without that flag PARAUNIT is used which will make it more complicated to interpret the test results.