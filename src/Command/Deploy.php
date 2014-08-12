<?php namespace Valorin\Deploy\Command;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\SSH;
use Symfony\Component\Console\Input\InputArgument;

class Deploy extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'deploy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactive application deploy helper.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        // Load Remote name
        $remote = $this->argument('remote');
        $directory = Config::get('remote.connections.'.$remote.'.root');

        // Run production scripts
        if (!$this->production($remote)) {
            return 1;
        }

        // Run push scripts
        if (!$this->push()) {
            return 1;
        }

        // Define commands
        $commands = array_merge(['cd '.$directory], Config::get('vdeploy::config.commands'));

        // Run Commands
        SSH::into($remote)->run($commands);

        $this->info('All done!');
    }

    /**
     * Runs the push, if requested
     *
     * @return boolean
     */
    protected function push()
    {
        // Check if push is enabled
        if (!Config::get('vdeploy::config.push')) {
            return true;
        }

        passthru('git push', $code);
        if ($code) {
            $this->error("FAILED: git push");
            return false;
        }

        passthru('git push --tags', $code);
        if ($code) {
            $this->error("Failed: git push --tags");
            return false;
        }

        return true;
    }

    /**
     * Runs the production code, if requested
     *
     * @param  string  $remote
     * @return boolean
     */
    protected function production($remote)
    {
        // Check if push is enabled
        if (!Config::get('vdeploy::config.production.enabled')) {
            return true;
        }

        // Check for production
        if ($remote != Config::get('vdeploy::config.production.name')) {
            return true;
        }

        // Check if we need to tag
        $rawTag = trim(`git describe --tags --match 'v[0-9]*'`);
        $tag = trim(`git describe --tags --match 'v[0-9]*' --abbrev=0`);

        if ($rawTag == $tag) {
            return true;
        }

        // Extract parts
        $parts = preg_split("#[.-]#", substr($tag, 1));
        $preRelease = null;
        $preIncrement = 1;

        if (count($parts) == 3) {
            list($major, $minor, $patch) = $parts;
        } elseif (count($parts) == 4) {
            list($major, $minor, $patch, $preRelease) = $parts;
        } elseif (count($parts) == 5) {
            list($major, $minor, $patch, $preRelease, $preIncrement) = $parts;
        } else {
            $this->error("Unknown tag format: {$tag}");
            return false;
        }

        // Check if we've been told what type of release to do
        $newTag = $this->incrementTag($tag, $major, $minor, $patch, $preRelease, $preIncrement);

        if (!$newTag) {
            return false;
        }

        // Add tag
        $this->info("Tagging deployed version as: {$newTag}.");
        passthru("git tag {$newTag}", $code);
        if ($code) {
            $this->error("FAILED: git tag {$newTag}");
            return false;
        }

        return true;
    }

    /**
     * Increments the tag, based on argument or user input
     *
     * @param  string  $current
     * @param  integer $major
     * @param  integer $minor
     * @param  integer $patch
     * @param  sting   $preRelease
     * @param  integer $preIncrement
     * @return string
     */
    protected function incrementTag($current, $major, $minor, $patch, $preRelease, $preIncrement)
    {
        // Current version
        $this->info("The current release version is: {$current}.");

        // Simple options
        $tag['major'] = 'v'.($major+1).'.0.0';
        $tag['minor'] = "v{$major}.".($minor+1).'.0';
        $tag['patch'] = "v{$major}.{$minor}.".($patch+1);

        // Next RC versions
        $majorPlus = $major + 1;
        $minorPlus = $minor + 1;
        $tag['rcmajor'] = "v{$majorPlus}.0.0-rc";
        $tag['rcminor'] = "v{$major}.{$minorPlus}.0-rc";

        // Increment RC version
        if ($preRelease) {
            $extra = ((($preIncrement + 1) > 1) ? '.'.($preIncrement + 1) : '');
            $tag['rc'] = "v{$major}.{$minor}.{$patch}-rc{$extra}";
        }

        // Check if user provided the release
        $release = $this->argument("release");

        // If release not specified, ask the user
        if (!$release) {
            $extra = ($preRelease ? "-{$preRelease}".($preIncrement > 1 ? '.'.$preIncrement : '') : '');
            $this->info("The following release types are available:");
            $this->comment(" * Major:    {$tag['major']}");
            $this->comment(" * Minor:    {$tag['minor']}");
            $this->comment(" * Patch:    {$tag['patch']}");
            $this->comment(" * RC Major: {$tag['rcmajor']}");
            $this->comment(" * RC Minor: {$tag['rcminor']}");
            if (isset($tag['rc'])) {
                $this->comment(" * RC:       {$tag['rc']}");
            }
            $release = $this->ask("Which would you like? [".implode('|', array_keys($tag))."] ");
        }

        // Check valid release
        if (!isset($tag[$release])) {
            $this->error("Unknown release type: {$release}");
            return false;
        }

        return $tag[$release];
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        $default = Config::get('remote.default');
        $description = "Define which remote to connect to (default: {$default}).";

        return array(
            array('remote', InputArgument::OPTIONAL, $description, $default),
            array('release', InputArgument::OPTIONAL, "Release type, to increment the tag by."),
        );
    }
}
