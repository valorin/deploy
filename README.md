Deploy
======

Deploy was inspired by [Laravel Envoy](laravel.com/docs/ssh#envoy-task-runner), with the aim to provide a very simple
way to deploy your code into production while ensuring valid version tags are applied.
It allows you to deploy into staging/acceptance at any time, while adding an extra version tag check when you push to
production, to ensure all production releases are tagged appropriately.

It uses the remote hosts configured in `app/config/remote.php`, and integrates into your application as an Artisan
command, so deploying code is as easy as: `./artisan deploy`.

**As of v1.2**, it includes the ability to specify custom tags which can be replaced
when the command is being executed, to allow you to specify a command like this:

```
$commands = ['git pull -f {remote|origin} {branch|master}'];
```
But apply a custom branch and remote name as needed:
```
php artisan deploy --remote=github --branch=production
```

Installation
------------

Add the package to your application with composer:

```
composer require "valorin/deploy:~1.2"
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

To deploy to a remote host specified in `app/config/remote.php`, run the `deploy` command and specific the remote host name (the key, not the hostname), or leave it blank to use the default remote:

```
./artisan deploy <name>
```

By default, Deploy will do the following:

First, in the **local environment**, it will check the specified remote name.
If this is `production`, it will trigger the version tag check.

The version tag check looks for a valid [semantic version](http://semver.org/) tag on
the latest commit. If none is found, it will calculate the next possible version for
a number of release types (major, minor, patch, rc, etc), and prompt you to chose one.
When you've selected a valid version, Deploy will tag your latest commit.

Once there is a valid version tag, or you're not deploying to production, Deploy will
do a `git push` to make sure you haven't forgotten to push anything.

After a successful push, it will connect to the remote server and run the predefined commands list:

```
'commands' => array(
    'down'     => 'php artisan down',
    'checkout' => 'git checkout -f {branch|master}',
    'fetch'    => 'git fetch {remote|origin}',
    'tags'     => 'git fetch {remote|origin} --tags',
    'pull'     => 'git pull -f {remote|origin} {branch|master}',
    'composer' => 'composer install {composer-args|--no-dev}',
    'up'       => 'php artisan up',
    'queue'    => 'php artisan queue:restart',
)
```

*Note: All of these options and behaviours are customisable in the configuration.*

**Using Option Tags**

Deploy supports the user of option tags to allow you pass parameters (and even
commands) via the command line when you run deploy, rather than needing to manually
extend and edit the config file.

As seen in the default commands list above tags are in the format `{option}` or
`{option|default}`. When you specify a `{option}` without a default value, Deploy will
throw an error if the option is not provided.

Options are specified by adding an argument in the format `--set-option=value`, with `--set-` prefixed
onto the name of the option.

Using the default commands (shown above), you can tell Deploy to use the `github` remote and
the `production` branch like this:

```
php artisan deploy --set-remote=github --set-branch-production
```

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

The file will contain explinations for each of the options, so you can customise
Deploy as much as you require.

Version History
---------------

- **v1.2.1**
    - Removed DownSafe command, due to [merged bugfix](https://github.com/laravel/framework/pull/5565) in Laravel.
- **v1.2.0**
    - Implemented option tags to make commands more flexible.
    - Added key names to each default command to make extending easier.*
    - Cleaned up the next version prompt with a new selector.
- **v1.1.0**
    - Cleaned up the default config so it's friendlier for new users.
- **v1.0.0**
    - Initial Release

\* Note, [there is a bug](https://github.com/laravel/framework/pull/5531) which prevents nested package configs from cascading nicely.
