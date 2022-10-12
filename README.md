Acquia BLT pa11y
====

This is an [Acquia BLT](https://github.com/acquia/blt) plugin providing integration with the Pa11y test framework.

This plugin provides a set of commands in the `tests` namespace that use these frameworks to run automated tests on your Drupal site.

This plugin is **community-supported**.


# Installation and usage

To use this plugin, you must already have a Drupal project using BLT 13 or higher.

Add the following to the `repositories` section of your project's composer.json:

```json lines
"blt-pa11y": {
    "type": "vcs",
    "url": "https://github.com/nikunjkotecha/blt-pa11y.git",
    "no-api": true
}
```

or run:

```bash
composer config repositories.blt-pa11y '{"type": "vcs", "url": "https://github.com/nikunjkotecha/blt-pa11y.git", "no-api": true}'
```

In your project, require the plugin with Composer:

```bash
composer require --dev nikunjkotecha/blt-pa11y
```

## Initialize Config

Run the recipe to initialize the necessary pa11y files / directories.

```bash
blt recipes:pa11y:init
blt tests:pa11y:init
```

## Configuration

Update the configuration to specify all the urls to test. Make sure the site setup takes care of
creating all required content.

Configurations file `tests/pa11y/pa11y.yml` in your repo.

To understand different possible values for configuration, please visit https://github.com/pa11y/pa11y


To disable Pa11y validation, please add following to your blt.yml
```yaml
pa11y:
  validate: false
```

Example implementation can be found in the repo [github-actions-behat](https://github.com/nikunjkotecha/github-actions-behat/tree/docker)

## Run Tests

Run the tests:

```bash
 blt tests:pa11y
```

# License

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
