<?php
/**
 * Deploy Configuration
 */

return array(

    /**
     * Specify if `git push` should be run on the local directory before running remote commands.
     * Useful shortcut, so you can run `git add && git commit` followed by `./artisan deploy`, skipping `git push`.
     *
     * Note: It only runs 'git push', so you will need to have git set up with a default branch.
     */
    'push' => true,

    /**
     * 'production' remote settings
     */
    'production' => array(

        /**
         * Enable checking of valid release tag on production or not.
         * Useful shortcut when you require a properly tagged production version, to ensure you don't forget,
         *  and makes it easier to pick the next suitable tag.
         *
         * Set to false if you don't care about verison tags for production deployments.
         */
        'enabled' => true,

        /**
         * Production key name, if you're using something other than 'production' as your production remote.
         */
        'name' => 'production',
    ),

    /**
     * Commands to run on the remote server via SSH, will be run in the base directory specified in
     *  ./app/config/remote.php
     *
     * Depending on the status of PR #5531 (https://github.com/laravel/framework/pull/5531),
     *  you will either need to maintain your own copy of this array if you wish to change a single
     *  entry, or can just change the line you wish to change.
     *
     * Also note, if you wish to use a different set of commands, if that PR is approved, you will need to
     *  blank out the commands you do not wish to run. I.e:
     *     'tags' => '',
     *
     * Option tags are described in the README, but can either be defined with a default value as: {option|default}
     *  or without a default value as: {option}
     *
     * They are called via: ./artisan deploy --set-option=value
     */
    'commands' => array(

        'down'     => 'php artisan down',
        'checkout' => 'git checkout -f {branch|master}',
        'fetch'    => 'git fetch {remote|origin}',
        'tags'     => 'git fetch {remote|origin} --tags',
        'pull'     => 'git pull -f {remote|origin} {branch|master}',
        'composer' => 'composer install {composer-args|--no-dev}',
        'up'       => 'php artisan up',
        'queue'    => 'php artisan queue:restart',

    ),
);
