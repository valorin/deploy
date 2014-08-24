<?php namespace Valorin\Deploy\Command;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\SSH;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class Deploy extends Command
{
    /**
     * @var string
     */
    const TAG_PREFIX = 'set-';
    const REGEX_TAG = '/(\{\s*([^|} ]+)(?:\s*[|]\s*([^|} ]*))?\s*\})/i';

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
     * @var array
     */
    protected $tags    = [];
    protected $options = [];

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        // Run production scripts
        if (!$this->production()) {
            return 1;
        }

        // Run push scripts
        if (!$this->push()) {
            return 1;
        }

        // Run commands
        if (!$this->runCommands()) {
            return 1;
        }
    }

    /**
     * Override Illuminate\Console\Command to dynamic inject the user-specified --set-* options in the configuration.
     *
     * @return void
     */
    protected function specifyParameters()
    {
        // Identify our options
        foreach ($_SERVER['argv'] as $argument) {

            if (!Str::startsWith($argument, '--'.self::TAG_PREFIX)) {
                continue;
            }

            // Strip past the '='
            $argument = substr($argument, 0, strpos($argument, '='));

            // Add as option, if prefix matches
            $this->tags[]    = substr($argument, strlen(self::TAG_PREFIX));
            $this->options[] = [$argument, null, InputOption::VALUE_REQUIRED];
        }

        return parent::specifyParameters();
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
     * @return boolean
     */
    protected function production()
    {
        // Check if push is enabled
        if (!Config::get('vdeploy::config.production.enabled')) {
            return true;
        }

        // Check for production
        $remote = $this->argument('remote');
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
        $this->line('');
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
            $this->line('');
            $release = array_search($this->choice("Please select the next release version:", $tag), $tag);
        }

        // Check valid release
        if (!isset($tag[$release])) {
            $this->error("Unknown release type: {$release}");
            return false;
        }

        return $tag[$release];
    }

    /**
     * Runs the SSH commands on the remote
     *
     * @return boolean
     */
    protected function runCommands()
    {
        // Load Remote directory
        $remote    = $this->argument('remote');
        $directory = Config::get('remote.connections.'.$remote.'.root');

        // Define commands
        $commands = array_merge(['cd '.$directory], Config::get('vdeploy::config.commands'));
        $commands = array_filter($commands);

        // Parse tags in command
        if (!$this->parseTags($commands)) {
            return false;
        }

        // Run Commands
        SSH::into($remote)->run($commands);

        return true;
    }

    /**
     * Parses the user tags in the commands array
     *
     * @param  string|string[] $command
     * @return boolean
     */
    protected function parseTags(&$command)
    {
        // Recurse?
        if (is_array($command)) {
            foreach ($command as &$cmd) {
                if (!$this->parseTags($cmd)) {
                    return false;
                }
            }

            return true;
        }

        // Look for tags
        while (preg_match(self::REGEX_TAG, $command, $matches)) {

            // Attempt to load tag
            try {
                $value = $this->userOption($matches[2]);

            // if tag doesn't exist
            } catch (\InvalidArgumentException $exception) {

                // Default value provided?
                if (isset($matches[3])) {
                    $value = $matches[3];

                } else {
                    // Else, throw error
                    $this->error("ERROR: You need to specifiy the --".self::TAG_PREFIX.$matches[2]." argument!");
                    return false;
                }
            }

            // Replace
            $command = str_replace($matches[0], $value, $command);
        }

        return true;
    }

    /**
     * Translates user option into final option value
     *
     * @param  string $option
     * @param  string $default
     * @return string
     */
    protected function userOption($option, $default = null)
    {
        return $this->option(self::TAG_PREFIX.$option, $default);
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

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return $this->options;
    }
}
