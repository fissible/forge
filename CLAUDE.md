# CLAUDE.md — fissible/forge

## What this is

OpenAPI spec generation for PHP. Enumerates routes, inspects FormRequest classes to extract validation rules, and produces a valid OpenAPI 3.0 spec as a PHP array (serialised to YAML by the consumer). Response schemas are intentionally scaffolded as empty objects — they require human authorship.

Depends on **fissible/accord** and **fissible/drift**.

## Running tests

```bash
vendor/bin/phpunit
```

One suite: `Unit`. Tests are in `tests/Unit/`. Illuminate is not available in the forge test environment — the Laravel-specific inspector (`LaravelFormRequestInspector`) is tested indirectly through fissible/pilot's integration test suite.

## Key files

| File | Purpose |
|---|---|
| `src/SpecGenerator.php` | Main entry point — iterates routes, calls inspector and inferrer, builds spec array |
| `src/SchemaInferrer.php` | Converts a Laravel rules array to a JSON Schema `properties` object |
| `src/FormRequestInspectorInterface.php` | Contract: `getFormRequestClass(RouteDefinition): ?string` + `getRules(string): array` |
| `src/Console/GenerateCommand.php` | `accord:generate` Artisan command |
| `src/Drivers/Laravel/Inspectors/LaravelFormRequestInspector.php` | Reflects controller actions to find FormRequest subclasses and extract their rules |
| `src/Drivers/Laravel/Providers/ForgeServiceProvider.php` | Registers `SchemaInferrer`, `FormRequestInspectorInterface`, and `SpecGenerator` as singletons |

## Critical implementation note — FormRequest instantiation

`LaravelFormRequestInspector::getRules()` uses `ReflectionClass::newInstanceWithoutConstructor()` to obtain a FormRequest instance, **not** `$this->container->make()`.

**Why:** The container resolution path triggers `FormRequest::validateResolved()`, which throws `ValidationException` for any FormRequest with `required` fields when there is no real HTTP request in scope (e.g. during spec generation). This silently returned `[]` via the `catch(\Throwable)` handler, causing required field rules to be dropped from the generated spec.

**Do not revert** this to container resolution. The `newInstanceWithoutConstructor()` approach correctly bypasses the validation lifecycle. If `rules()` itself throws (e.g. when it accesses `$this->route()`), the catch handler correctly falls back to `[]`.

## Schema inference rules

`SchemaInferrer` maps pipe-delimited rule strings to JSON Schema. The mapping is documented in the README. Key points:

- `required` adds the field to the schema's `required` array — it is not a property-level key
- Non-string rule objects (`Rule::in([...])`) are silently skipped — mention this in user-facing output/docs
- Dot-notation nested fields (`address.city`) are skipped; the parent field gets `type: array`
- `string` is the default type when no type rule is present

## Conventions

- `declare(strict_types=1)` on every file
- `SpecGenerator::generate()` returns a plain PHP array — YAML serialisation is the caller's responsibility
- No framework code outside `src/Drivers/`
- `SchemaInferrer` has no dependencies — keep it that way (pure function over a rules array)

## Extending

**Custom FormRequest inspector:** Implement `FormRequestInspectorInterface` and bind it before `ForgeServiceProvider` runs. Useful for non-Laravel apps or when rules come from a source other than `FormRequest::rules()`.

**Testing the inspector:** Because Illuminate is not in forge's own vendor, integration tests for `LaravelFormRequestInspector` live in fissible/pilot (`tests/Feature/Stories/GenerateOpenapiSpecStory.php`).

## Relationship to other packages

- **fissible/accord** is a direct dependency — uses `SpecSourceInterface` and `FileSpecSource`
- **fissible/drift** is a direct dependency — uses `RouteDefinition`
- **fissible/pilot** wires `SpecGenerator` into its Forge page and uses the output to preview/write specs to disk
