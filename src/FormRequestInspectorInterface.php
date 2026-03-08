<?php

declare(strict_types=1);

namespace Fissible\Forge;

use Fissible\Drift\RouteDefinition;

/**
 * Extracts FormRequest (or equivalent) validation rules for a given route.
 *
 * Implement this interface to teach Forge how to find request schemas in
 * your framework. The Laravel driver inspects controller method type-hints
 * to locate FormRequest classes and reads their rules() output.
 */
interface FormRequestInspectorInterface
{
    /**
     * Return the fully-qualified FormRequest class name for this route's
     * controller action, or null if no FormRequest is used.
     */
    public function getFormRequestClass(RouteDefinition $route): ?string;

    /**
     * Return the validation rules array from the given FormRequest class.
     * Rules may be strings ('required|string') or arrays (['required', 'string']).
     *
     * @return array<string, string|array>
     */
    public function getRules(string $formRequestClass): array;
}
