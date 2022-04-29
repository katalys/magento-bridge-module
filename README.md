# magento-1o
Magento module for 1o shop integration


# Installation

Run the following command to install the package using composer:
```
composer require 1o/magento-bridge-module
bin/magento module:enable OneO_Shop
bin/magento setup:upgrade
```
# Removal

Run the following command to remove the package using composer:
```
bin/magento module:disable OneO_Shop
composer remove 1o/magento-bridge-module
composer update
```

# Configuration

Open the Admin interface and go to Stores -> Configuration

# How to update

To update this module run the following command:
```
composer update
```
Note that this will also update any other packages in the project according to composer definition. Depending on the nature of the update the following commands might also be required:
```
$ bin/magento setup:upgrade
$ bin/magento setup:di:compile
$ bin/magento setup:static-content:deploy
```
