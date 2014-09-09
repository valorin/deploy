<?php namespace Valorin\Deploy;

use Illuminate\Support\ServiceProvider;
use Valorin\Deploy\Command\Deploy;

class DeployServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->package('valorin/deploy', 'vdeploy', __DIR__);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Register commands
        $this->registerCommands();
    }

    /**
     * Registers Artisan Commands
     *
     * @return void
     */
    protected function registerCommands()
    {
        $this->app['command.deploy'] = $this->app->share(function () {
            return new Deploy();
        });
        $this->commands('command.deploy');
    }
}
