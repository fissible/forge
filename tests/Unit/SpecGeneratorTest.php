<?php

declare(strict_types=1);

namespace Fissible\Forge\Tests\Unit;

use Fissible\Drift\RouteDefinition;
use Fissible\Forge\FormRequestInspectorInterface;
use Fissible\Forge\SchemaInferrer;
use Fissible\Forge\SpecGenerator;
use PHPUnit\Framework\TestCase;

class SpecGeneratorTest extends TestCase
{
    private SchemaInferrer $inferrer;

    protected function setUp(): void
    {
        $this->inferrer = new SchemaInferrer();
    }

    private function generator(?FormRequestInspectorInterface $inspector = null): SpecGenerator
    {
        return new SpecGenerator($this->inferrer, $inspector);
    }

    private function route(string $method, string $path): RouteDefinition
    {
        return new RouteDefinition($method, $path);
    }

    // ── Envelope ──────────────────────────────────────────────────────────────

    public function test_generate_returns_openapi_envelope(): void
    {
        $spec = $this->generator()->generate([], 'v1', 'My API');

        $this->assertSame('3.0.3', $spec['openapi']);
        $this->assertSame('My API', $spec['info']['title']);
        $this->assertSame('1.0.0', $spec['info']['version']);
        $this->assertSame([], $spec['paths']);
    }

    public function test_default_title_is_API(): void
    {
        $spec = $this->generator()->generate([], 'v1');

        $this->assertSame('API', $spec['info']['title']);
    }

    // ── Path grouping ─────────────────────────────────────────────────────────

    public function test_routes_grouped_by_openapi_path(): void
    {
        $routes = [
            $this->route('GET', '/v1/users'),
            $this->route('POST', '/v1/users'),
        ];

        $spec = $this->generator()->generate($routes, 'v1');

        $this->assertArrayHasKey('/v1/users', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/v1/users']);
        $this->assertArrayHasKey('post', $spec['paths']['/v1/users']);
    }

    public function test_param_path_uses_curly_brace_notation(): void
    {
        $routes = [$this->route('GET', '/v1/users/:id')];

        $spec = $this->generator()->generate($routes, 'v1');

        $this->assertArrayHasKey('/v1/users/{id}', $spec['paths']);
    }

    // ── operationId ───────────────────────────────────────────────────────────

    public function test_operation_id_built_from_path_and_method(): void
    {
        $routes = [$this->route('GET', '/v1/users')];

        $spec = $this->generator()->generate($routes, 'v1');

        $this->assertSame('v1.users.get', $spec['paths']['/v1/users']['get']['operationId']);
    }

    public function test_operation_id_replaces_param_segment_with_item(): void
    {
        $routes = [$this->route('GET', '/v1/users/:id')];

        $spec = $this->generator()->generate($routes, 'v1');

        $this->assertSame('v1.users.item.get', $spec['paths']['/v1/users/{id}']['get']['operationId']);
    }

    // ── Default responses ─────────────────────────────────────────────────────

    public function test_get_route_has_200_response(): void
    {
        $routes = [$this->route('GET', '/v1/users')];

        $spec = $this->generator()->generate($routes, 'v1');

        $this->assertArrayHasKey('200', $spec['paths']['/v1/users']['get']['responses']);
    }

    public function test_post_route_has_201_response(): void
    {
        $routes = [$this->route('POST', '/v1/users')];

        $spec = $this->generator()->generate($routes, 'v1');

        $this->assertArrayHasKey('201', $spec['paths']['/v1/users']['post']['responses']);
    }

    public function test_delete_response_description_is_no_content(): void
    {
        $routes = [$this->route('DELETE', '/v1/users/:id')];

        $spec = $this->generator()->generate($routes, 'v1');

        $this->assertSame('No Content', $spec['paths']['/v1/users/{id}']['delete']['responses']['200']['description']);
    }

    public function test_response_schema_is_empty_object_placeholder(): void
    {
        $routes = [$this->route('GET', '/v1/users')];

        $spec  = $this->generator()->generate($routes, 'v1');
        $schema = $spec['paths']['/v1/users']['get']['responses']['200']['content']['application/json']['schema'];

        $this->assertSame(['type' => 'object'], $schema);
    }

    // ── requestBody ───────────────────────────────────────────────────────────

    public function test_post_has_request_body(): void
    {
        $routes = [$this->route('POST', '/v1/users')];

        $spec = $this->generator()->generate($routes, 'v1');

        $this->assertArrayHasKey('requestBody', $spec['paths']['/v1/users']['post']);
    }

    public function test_put_has_request_body(): void
    {
        $routes = [$this->route('PUT', '/v1/users/:id')];

        $spec = $this->generator()->generate($routes, 'v1');

        $this->assertArrayHasKey('requestBody', $spec['paths']['/v1/users/{id}']['put']);
    }

    public function test_patch_has_request_body(): void
    {
        $routes = [$this->route('PATCH', '/v1/users/:id')];

        $spec = $this->generator()->generate($routes, 'v1');

        $this->assertArrayHasKey('requestBody', $spec['paths']['/v1/users/{id}']['patch']);
    }

    public function test_get_has_no_request_body(): void
    {
        $routes = [$this->route('GET', '/v1/users')];

        $spec = $this->generator()->generate($routes, 'v1');

        $this->assertArrayNotHasKey('requestBody', $spec['paths']['/v1/users']['get']);
    }

    public function test_delete_has_no_request_body(): void
    {
        $routes = [$this->route('DELETE', '/v1/users/:id')];

        $spec = $this->generator()->generate($routes, 'v1');

        $this->assertArrayNotHasKey('requestBody', $spec['paths']['/v1/users/{id}']['delete']);
    }

    public function test_request_body_is_required(): void
    {
        $routes = [$this->route('POST', '/v1/users')];

        $spec = $this->generator()->generate($routes, 'v1');

        $this->assertTrue($spec['paths']['/v1/users']['post']['requestBody']['required']);
    }

    public function test_request_body_without_inspector_is_empty_object(): void
    {
        $routes = [$this->route('POST', '/v1/users')];

        $spec   = $this->generator()->generate($routes, 'v1');
        $schema = $spec['paths']['/v1/users']['post']['requestBody']['content']['application/json']['schema'];

        $this->assertSame(['type' => 'object'], $schema);
    }

    // ── FormRequestInspector integration ──────────────────────────────────────

    public function test_request_body_schema_inferred_from_form_request(): void
    {
        $inspector = $this->createMock(FormRequestInspectorInterface::class);
        $inspector->method('getFormRequestClass')->willReturn('App\\Http\\Requests\\CreateUserRequest');
        $inspector->method('getRules')->willReturn([
            'name'  => 'required|string|max:100',
            'email' => 'required|email',
        ]);

        $routes = [$this->route('POST', '/v1/users')];
        $spec   = $this->generator($inspector)->generate($routes, 'v1');

        $schema = $spec['paths']['/v1/users']['post']['requestBody']['content']['application/json']['schema'];

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayHasKey('email', $schema['properties']);
        $this->assertSame('email', $schema['properties']['email']['format']);
    }

    public function test_null_form_request_class_falls_back_to_empty_object(): void
    {
        $inspector = $this->createMock(FormRequestInspectorInterface::class);
        $inspector->method('getFormRequestClass')->willReturn(null);

        $routes = [$this->route('POST', '/v1/users')];
        $spec   = $this->generator($inspector)->generate($routes, 'v1');

        $schema = $spec['paths']['/v1/users']['post']['requestBody']['content']['application/json']['schema'];

        $this->assertSame(['type' => 'object'], $schema);
    }
}
