# Remove the duplicate account balance route

Removed `/console/account/balance`, its module mapping, and the duplicate account recharge sidebar entry. The account profile remains at `/console/account/profile`, including its balance display and recharge button. The removed URL now returns 404.

This cumulative update includes the previous console layout changes.

## Installation

Extract the ZIP into `tycdn-backend`, replacing existing files, then run:

```sh
php artisan optimize:clear
```

The package includes rebuilt production assets. It has not been deployed.
