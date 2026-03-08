<?php

declare(strict_types=1);

namespace Fissible\Forge;

use Fissible\Drift\RouteDefinition;

/**
 * Generates an OpenAPI 3.0 spec array from a set of route definitions.
 *
 * The generated spec is a valid starting point — response schemas are
 * scaffolded as empty objects ({}) to be filled in by the developer.
 * Request body schemas are inferred from FormRequest rules when a
 * FormRequestInspectorInterface implementation is provided.
 */
class SpecGenerator
{
    public function __construct(
        private readonly SchemaInferrer $schemaInferrer,
        private readonly ?FormRequestInspectorInterface $formRequestInspector = null,
    ) {}

    /**
     * @param RouteDefinition[] $routes
     */
    public function generate(array $routes, string $version, string $title = 'API'): array
    {
        $paths = [];

        foreach ($routes as $route) {
            $pathKey = $route->openApiPath();

            $paths[$pathKey][strtolower($route->method)] = $this->buildOperation($route);
        }

        return [
            'openapi' => '3.0.3',
            'info'    => [
                'title'   => $title,
                'version' => '1.0.0',
            ],
            'paths'   => $paths,
        ];
    }

    private function buildOperation(RouteDefinition $route): array
    {
        $operation = [
            'operationId' => $this->buildOperationId($route),
            'responses'   => $this->buildDefaultResponse($route),
        ];

        if (in_array($route->method, ['POST', 'PUT', 'PATCH'], strict: true)) {
            $operation['requestBody'] = $this->buildRequestBody($route);
        }

        return $operation;
    }

    private function buildOperationId(RouteDefinition $route): string
    {
        $parts = array_values(array_filter(explode('/', $route->openApiPath())));

        $parts = array_map(
            fn(string $p) => preg_match('/^\{.+\}$/', $p) ? 'item' : $p,
            $parts,
        );

        return implode('.', $parts) . '.' . strtolower($route->method);
    }

    private function buildDefaultResponse(RouteDefinition $route): array
    {
        $status = $route->method === 'POST' ? '201' : '200';
        $desc   = match ($route->method) {
            'POST'   => 'Created',
            'DELETE' => 'No Content',
            default  => 'OK',
        };

        return [
            $status => [
                'description' => $desc,
                'content'     => [
                    'application/json' => [
                        // Empty schema — fill in with actual response structure
                        'schema' => ['type' => 'object'],
                    ],
                ],
            ],
        ];
    }

    private function buildRequestBody(RouteDefinition $route): array
    {
        $schema = ['type' => 'object'];

        if ($this->formRequestInspector !== null) {
            $class = $this->formRequestInspector->getFormRequestClass($route);

            if ($class !== null) {
                $rules  = $this->formRequestInspector->getRules($class);
                $schema = $this->schemaInferrer->inferRequestSchema($rules);
            }
        }

        return [
            'required' => true,
            'content'  => [
                'application/json' => ['schema' => $schema],
            ],
        ];
    }
}
