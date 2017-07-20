## Install WP Tests

1. SSH into your environment and `cd` to the `woocommerce-<plugin-name>` directory
2. Run the following command:
```sh
# bash path/to/tests/bin/install.sh DB_NAME DB_USER DB_PASS DB_HOST WP_VERSION WC_BRANCH
bash tests/bin/install.sh memberships_tests root root localhost latest master
```

## Run Tests

From the plugin directory, run:
```sh
phpunit
```
