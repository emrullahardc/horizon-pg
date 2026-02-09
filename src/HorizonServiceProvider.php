<?php

namespace HorizonPg;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use HorizonPg\Connectors\DatabaseConnector;

class HorizonServiceProvider extends ServiceProvider
{
    use EventMap, ServiceBindings;

    public function boot()
    {
        $this->normalizeConfig();
        $this->registerEvents();
        $this->registerRoutes();
        $this->registerResources();
        $this->offerPublishing();
        $this->registerCommands();
        $this->registerMigrations();
    }

    protected function normalizeConfig()
    {
        if (! $this->app['config']->get('horizon.name')) {
            $this->app['config']->set('horizon.name', $this->app['config']->get('app.name'));
        }
    }

    protected function registerEvents()
    {
        $events = $this->app->make(Dispatcher::class);

        foreach ($this->events as $event => $listeners) {
            foreach ($listeners as $listener) {
                $events->listen($event, $listener);
            }
        }
    }

    protected function registerRoutes()
    {
        if ($this->app instanceof CachesRoutes && $this->app->routesAreCached()) {
            return;
        }

        Route::group([
            'domain' => config('horizon.domain', null),
            'prefix' => config('horizon.path'),
            'namespace' => 'HorizonPg\Http\Controllers',
            'middleware' => config('horizon.middleware', 'web'),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    protected function registerResources()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'horizon');
    }

    protected function offerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../stubs/HorizonServiceProvider.stub' => app_path('Providers/HorizonServiceProvider.php'),
            ], 'horizon-provider');

            $this->publishes([
                __DIR__.'/../config/horizon.php' => config_path('horizon.php'),
            ], 'horizon-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'horizon-migrations');
        }
    }

    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ClearCommand::class,
                Console\ClearMetricsCommand::class,
                Console\ContinueCommand::class,
                Console\ContinueSupervisorCommand::class,
                Console\ForgetFailedCommand::class,
                Console\HorizonCommand::class,
                Console\InstallCommand::class,
                Console\ListCommand::class,
                Console\ListenCommand::class,
                Console\PauseCommand::class,
                Console\PauseSupervisorCommand::class,
                Console\PublishCommand::class,
                Console\PurgeCommand::class,
                Console\SupervisorCommand::class,
                Console\SupervisorStatusCommand::class,
                Console\TerminateCommand::class,
                Console\TimeoutCommand::class,
                Console\WorkCommand::class,
                Console\CleanupCommand::class,
            ]);

            if (method_exists($this, 'reloads')) {
                $this->reloads('horizon:terminate', 'queue');
            }
        }

        $this->commands([
            Console\SnapshotCommand::class,
            Console\StatusCommand::class,
            Console\SupervisorsCommand::class,
        ]);
    }

    protected function registerMigrations()
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    public function register()
    {
        if (! defined('HORIZON_PATH')) {
            define('HORIZON_PATH', realpath(__DIR__.'/../'));
        }

        $this->app->bind(Console\WorkCommand::class, function ($app) {
            return new Console\WorkCommand($app['queue.worker'], $app['cache.store']);
        });

        $this->configure();
        $this->registerDatabaseConnection();
        $this->registerServices();
        $this->registerQueueConnectors();
    }

    protected function configure()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/horizon.php', 'horizon'
        );

        Horizon::use(config('horizon.use', 'default'));
    }

    protected function registerDatabaseConnection()
    {
        $this->app->singleton(ConnectionInterface::class, function ($app) {
            $connection = config('horizon.database_connection', config('database.default'));

            return $app['db']->connection($connection);
        });
    }

    protected function registerServices()
    {
        foreach ($this->serviceBindings as $key => $value) {
            is_numeric($key)
                ? $this->app->singleton($value)
                : $this->app->singleton($key, $value);
        }
    }

    protected function registerQueueConnectors()
    {
        $this->callAfterResolving(QueueManager::class, function ($manager) {
            $manager->addConnector('database', function () {
                return new DatabaseConnector($this->app['db']);
            });
        });
    }
}
