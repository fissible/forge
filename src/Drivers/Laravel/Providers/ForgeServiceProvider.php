<?php

declare(strict_types=1);

namespace Fissible\Forge\Drivers\Laravel\Providers;

use Fissible\Drift\RouteInspectorInterface;
use Fissible\Forge\Console\GenerateCommand;
use Fissible\Forge\Drivers\Laravel\Inspectors\LaravelFormRequestInspector;
use Fissible\Forge\FormRequestInspectorInterface;
use Fissible\Forge\SchemaInferrer;
use Fissible\Forge\SpecGenerator;
use Illuminate\Support\ServiceProvider;

class ForgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SchemaInferrer::class, fn () => new SchemaInferrer());

        $this->app->singleton(FormRequestInspectorInterface::class, function () {
            return new LaravelFormRequestInspector($this->app);
        });

        $this->app->singleton(SpecGenerator::class, function () {
            return new SpecGenerator(
                schemaInferrer:        $this->app->make(SchemaInferrer::class),
                formRequestInspector:  $this->app->make(FormRequestInspectorInterface::class),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateCommand::class,
            ]);
        }
    }
}
