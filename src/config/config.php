<?php
/**
 * Deploy Configuration
 */

return array(

    /**
     * Specify if `git push` should be run on the local directory before running remote commands.
     * Useful shortcut, so you can run `git add && git commit` followed by `./artisan deploy`, skipping `git push`.
     */
    'push' => true,

    /**
     * Special Production Settings
     */
    'production' => array(

        /**
         * Enable checking of valid release tag on production or not.
         * Useful shortcut when you require a properly tagged production version, to ensure you don't forget,
         *  and makes it easier to pick the next suitable tag.
         */
        'enabled' => true,

        /**
         * Production key name, if you're using something other than 'production' as your production remote.
         */
        'name' => 'production',
    ),

    /**
     * Commands to run on the remote server via SSH, will be run in the base directory specified in
     *  ./app/config/remote.php.
     */
    'commands' => array(
        'php artisan down:safe',
        'git checkout -f master',
        'git fetch origin',
        'git fetch origin --tags',
        'git pull -f origin master',
        'composer install --no-dev',
        'php artisan up',
    ),
);
