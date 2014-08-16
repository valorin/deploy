Deploy
======

Deploy is a simple Artisan command to help with deploying a project to both a staging area (for testing), and a production area (when it's ready) without needing to stuff around with SSH/FTP or custom set ups.

It relies on the remote hosts configured in `app/config/remote.php`, and integrates into Artisan so deploying code is as easy as: `./artisan deploy`.

Deploy is configured to use two of my helper packages by default:

- [CronSync](https://github.com/valorin/cronsync)
- [L4 Down Safe](https://github.com/valorin/l4-down-safe)

*Note: I have Deploy is tailored to my standard use, you will most likely have to change the remote command list to suit your own needs.*

Installation
------------

Add the package to your application with composer:

```
composer require "valorin/deploy:~1.0"
```

Add the `DeployServiceProvider` service provider to the `providers` list in `./app/config/app.php`:

```
'providers' => array(
    ...
    'Valorin\Deploy\DeployServiceProvider',
),
```

Update `app/config/remote.php` with at least one remote host.

Usage
-----

To deploy to a remote host specified in `app/config/remote.php`, run the `deploy` command and specific the remote host name (the key, not the hostname), or leave it blank to use the default:

```
./artisan deploy <name>
```

Deploy will, by default, do the following:

1. **On the local environment**
  1. **If Remote Name == 'production'**
    1. Check if a Semantic Version tag is set on the latest commit.
    1. If latest is not tagged:
      1. Show the last specified tag, and prompt for the selection of a new git version tag based on common semver options.
      1. Tag the commit with the specified tag.
  1. Push the latest commits to the remote (using `git push`, relying on git to know defaults).
1. **On the Remote Environment**
  1. `php artisan down:safe`
  1. `git checkout -f master`
  1. `git fetch origin`
  1. `git fetch origin --tags`
  1. `git pull -f origin master`
  1. `composer install --no-dev`
  1. `php artisan migrate`
  1. `php artisan cronsync`
  1. `php artisan cache:clear`
  1. `php artisan up`

All of these options are customisable in the configuration.

Configuration
-------------

To change the default configuration, run:

```
./artisan config:publish "valorin/deploy"
```

And then edit the configuration file at:

```
./app/config/packages/valorin/deploy/config.php
```

Version History
---------------

`v2.0.0` -- Added [CronSync](https://github.com/valorin/cronsync) command into remote commands list. **WARNING: Will break existing cronjobs.**

`v1.0.0` -- Initial Release
