<?php

declare(strict_types=1);

namespace Fissible\Forge\Drivers\Laravel\Inspectors;

use Fissible\Drift\RouteDefinition;
use Fissible\Forge\FormRequestInspectorInterface;
use Illuminate\Container\Container;
use Illuminate\Foundation\Http\FormRequest;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Discovers FormRequest classes by reflecting controller action signatures.
 *
 * For a route pointing to UserController@store, this inspector reflects the
 * store() method parameters and finds any that extend FormRequest. It then
 * instantiates the FormRequest via the container and calls rules().
 *
 * Limitations:
 *   - Closure-based routes cannot be inspected
 *   - FormRequests with complex constructor dependencies may fail to instantiate
 *   - rules() that depend on request data will return empty/partial results
 */
class LaravelFormRequestInspector implements FormRequestInspectorInterface
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function getFormRequestClass(RouteDefinition $route): ?string
    {
        // Route action may be stored in rawPath context; we need the controller action.
        // This is called from SpecGenerator which passes the RouteDefinition — but
        // the controller info comes from Laravel's route registry, not RouteDefinition.
        // In practice, this inspector is called with additional context from the
        // GenerateCommand which resolves the action string separately.
        // See LaravelGenerateCommand for the full wiring.
        return null;
    }

    /**
     * Find the FormRequest class for a given controller action string (e.g. "UserController@store").
     */
    public function getFormRequestClassForAction(string $action): ?string
    {
        if (!str_contains($action, '@')) {
            return null;
        }

        [$controller, $method] = explode('@', $action, 2);

        try {
            $reflection = new ReflectionMethod($controller, $method);
        } catch (ReflectionException) {
            return null;
        }

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $className = $type->getName();

            if (class_exists($className) && is_subclass_of($className, FormRequest::class)) {
                return $className;
            }
        }

        return null;
    }

    public function getRules(string $formRequestClass): array
    {
        try {
            /** @var FormRequest $instance */
            $instance = $this->container->make($formRequestClass);

            return $instance->rules();
        } catch (\Throwable) {
            return [];
        }
    }
}
