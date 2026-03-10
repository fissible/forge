# fissible/forge

OpenAPI spec generation for PHP. Scaffolds a valid OpenAPI 3.0 spec from your application's routes, inferring request body schemas from Laravel FormRequest validation rules.

Part of the [Fissible](https://github.com/fissible) suite. New to Fissible? [Start with fissible/accord](https://github.com/fissible/accord) — it explains how the three packages work together.

---

## Why spec generation helps

Writing an OpenAPI spec by hand for an existing API is tedious and easy to get wrong. Every route needs a path entry, every request body needs a schema, and any mistake means the spec diverges from reality before it's even been published.

**forge** does the mechanical work for you. It reads the routes your application already defines, inspects any FormRequest classes to understand what each endpoint expects, and produces a valid, structured OpenAPI 3.0 spec as a starting point. You fill in the response schemas; forge handles everything else.

The output is a real spec — one you can commit, version, and hand to fissible/accord for validation, or share with API consumers and tooling that understands OpenAPI.

---

## Requirements

- PHP ^8.2
- fissible/accord ^1.0
- fissible/drift ^1.0

## Installation

```bash
composer require fissible/forge
```

### Laravel auto-discovery

The service provider registers automatically. The generate command is registered with Artisan:

```bash
php artisan accord:generate
```

---

## How it works

Forge enumerates your routes, groups them by path, and builds an OpenAPI operation for each. For `POST`, `PUT`, and `PATCH` routes, it inspects the controller action signature to find any `FormRequest` class, calls `rules()` on it, and converts those rules to a JSON Schema object for the `requestBody`.

Response schemas are scaffolded as empty objects (`{}`) — these are intentionally left for you to fill in, since response structure can't be reliably inferred from routes alone.

---

## Console command

### `accord:generate`

Generates a spec file from your API routes:

```bash
php artisan accord:generate
php artisan accord:generate --version=v2
php artisan accord:generate --title="Acme API" --output=docs/openapi.yaml
php artisan accord:generate --force   # overwrite existing file
```

| Option | Default | Description |
|--------|---------|-------------|
| `--version` | `v1` | URI version to generate for (filters routes matching `/v1/...`) |
| `--title` | `API` | Value for `info.title` in the spec |
| `--output` | `resources/openapi/{version}.yaml` | Output file path |
| `--force` | — | Overwrite an existing spec file |

The generated file is ready to commit and compatible with fissible/accord for runtime validation.

---

## Schema inference

Forge maps Laravel validation rules to JSON Schema properties:

| Rule | Schema effect |
|------|--------------|
| `integer`, `int`, `numeric` | `type: integer` |
| `boolean`, `bool`, `accepted`, `declined` | `type: boolean` |
| `array` | `type: array` |
| `number`, `decimal` | `type: number` |
| `string` (default) | `type: string` |
| `email` | `format: email` |
| `url` | `format: uri` |
| `date` | `format: date` |
| `date_format:*` | `format: date-time` |
| `uuid` | `format: uuid` |
| `nullable` | `nullable: true` |
| `min:N` | `minLength` (string) or `minimum` (numeric) |
| `max:N` | `maxLength` (string) or `maximum` (numeric) |
| `in:a,b,c` | `enum: [a, b, c]` |
| `digits:N` | `minLength: N`, `maxLength: N` |
| `required` | field added to `required` array |

Rule objects (e.g. `Rule::in([...])`) are skipped — add those schema details manually after generation.

Dot-notation nested fields (e.g. `address.city`) are currently skipped. The parent field (`address`) is included with `type: array`.

---

## Example output

Given a `POST /v1/users` route with this FormRequest:

```php
public function rules(): array
{
    return [
        'name'  => 'required|string|max:100',
        'email' => 'required|email',
        'role'  => 'in:admin,editor,viewer',
    ];
}
```

Forge generates:

```yaml
openapi: 3.0.3
info:
  title: API
  version: 1.0.0
paths:
  /v1/users:
    post:
      operationId: v1.users.post
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
                  maxLength: 100
                email:
                  type: string
                  format: email
                role:
                  type: string
                  enum: [admin, editor, viewer]
              required: [name, email]
      responses:
        '201':
          description: Created
          content:
            application/json:
              schema:
                type: object   # ← fill this in
```

---

## Laravel

### FormRequest inspector

The bundled `LaravelFormRequestInspector` reflects controller action signatures to find `FormRequest` subclasses:

```php
// Automatically wired by ForgeServiceProvider
use Fissible\Forge\Drivers\Laravel\Inspectors\LaravelFormRequestInspector;
```

It handles any controller action of the form `ControllerClass@method`. Closure-based routes are skipped. The inspector uses `ReflectionClass::newInstanceWithoutConstructor()` to call `rules()` without triggering Laravel's request validation lifecycle — this means FormRequests with `required` fields work correctly during spec generation, even outside of an HTTP request context. If `rules()` itself cannot run without a real request (e.g. because it reads `$this->route()`), it falls back to an empty object schema.

### Custom inspectors

Implement `FormRequestInspectorInterface` to extract validation rules from any source:

```php
use Fissible\Forge\FormRequestInspectorInterface;
use Fissible\Drift\RouteDefinition;

class MyInspector implements FormRequestInspectorInterface
{
    public function getFormRequestClass(RouteDefinition $route): ?string
    {
        // return the FQCN of the form request for this route, or null
    }

    public function getRules(string $formRequestClass): array
    {
        // return an array<string, string|array> of validation rules
    }
}
```

Bind it in your service provider before `ForgeServiceProvider` loads, or override it after:

```php
$this->app->singleton(FormRequestInspectorInterface::class, MyInspector::class);
```

---

## Core API

Use forge programmatically without the console command:

```php
use Fissible\Forge\SchemaInferrer;
use Fissible\Forge\SpecGenerator;

$generator = new SpecGenerator(
    schemaInferrer:       new SchemaInferrer(),
    formRequestInspector: $inspector, // optional
);

$spec = $generator->generate($routes, version: 'v1', title: 'My API');
// $spec is a plain PHP array — serialize it however you like
```

---

## Recommended workflow

1. **Generate** a spec from your existing routes with `accord:generate`
2. **Fill in** the response schemas in the generated YAML
3. **Commit** the spec to version control
4. **Validate** requests and responses at runtime with fissible/accord
5. **Detect drift** as your API evolves with fissible/drift

---

## License

MIT
