<?php

declare(strict_types=1);

namespace Fissible\Forge\Tests\Unit;

use Fissible\Forge\SchemaInferrer;
use PHPUnit\Framework\TestCase;

class SchemaInferrerTest extends TestCase
{
    private SchemaInferrer $inferrer;

    protected function setUp(): void
    {
        $this->inferrer = new SchemaInferrer();
    }

    public function test_empty_rules_returns_empty_object_schema(): void
    {
        $schema = $this->inferrer->inferRequestSchema([]);

        $this->assertSame('object', $schema['type']);
        $this->assertSame([], $schema['properties']);
        $this->assertArrayNotHasKey('required', $schema);
    }

    public function test_required_field_is_collected(): void
    {
        $schema = $this->inferrer->inferRequestSchema([
            'name' => 'required|string',
        ]);

        $this->assertSame(['name'], $schema['required']);
    }

    public function test_optional_field_not_in_required(): void
    {
        $schema = $this->inferrer->inferRequestSchema([
            'nickname' => 'nullable|string',
        ]);

        $this->assertArrayNotHasKey('required', $schema);
    }

    /** @dataProvider typeProvider */
    public function test_type_inference(string|array $rules, string $expectedType): void
    {
        $schema = $this->inferrer->inferRequestSchema(['field' => $rules]);

        $this->assertSame($expectedType, $schema['properties']['field']['type']);
    }

    public static function typeProvider(): array
    {
        return [
            'integer keyword'   => ['integer', 'integer'],
            'int keyword'       => ['int', 'integer'],
            'numeric keyword'   => ['numeric', 'integer'],
            'boolean keyword'   => ['boolean', 'boolean'],
            'bool keyword'      => ['bool', 'boolean'],
            'accepted keyword'  => ['accepted', 'boolean'],
            'declined keyword'  => ['declined', 'boolean'],
            'array keyword'     => ['array', 'array'],
            'number keyword'    => ['number', 'number'],
            'decimal keyword'   => ['decimal', 'number'],
            'string keyword'    => ['string', 'string'],
            'default (no type)' => ['required', 'string'],
            'array rules list'  => [['required', 'integer'], 'integer'],
        ];
    }

    /** @dataProvider formatProvider */
    public function test_format_inference(string $rules, string $expectedFormat): void
    {
        $schema = $this->inferrer->inferRequestSchema(['field' => $rules]);

        $this->assertSame($expectedFormat, $schema['properties']['field']['format']);
    }

    public static function formatProvider(): array
    {
        return [
            'email'       => ['email', 'email'],
            'url'         => ['url', 'uri'],
            'date'        => ['date', 'date'],
            'date_format' => ['date_format:Y-m-d H:i:s', 'date-time'],
            'uuid'        => ['uuid', 'uuid'],
        ];
    }

    public function test_nullable_sets_nullable_flag(): void
    {
        $schema = $this->inferrer->inferRequestSchema(['field' => 'nullable|string']);

        $this->assertTrue($schema['properties']['field']['nullable']);
    }

    public function test_non_nullable_has_no_nullable_flag(): void
    {
        $schema = $this->inferrer->inferRequestSchema(['field' => 'string']);

        $this->assertArrayNotHasKey('nullable', $schema['properties']['field']);
    }

    public function test_min_on_string_sets_minLength(): void
    {
        $schema = $this->inferrer->inferRequestSchema(['field' => 'string|min:3']);

        $this->assertSame(3, $schema['properties']['field']['minLength']);
        $this->assertArrayNotHasKey('minimum', $schema['properties']['field']);
    }

    public function test_max_on_string_sets_maxLength(): void
    {
        $schema = $this->inferrer->inferRequestSchema(['field' => 'string|max:255']);

        $this->assertSame(255, $schema['properties']['field']['maxLength']);
        $this->assertArrayNotHasKey('maximum', $schema['properties']['field']);
    }

    public function test_min_on_integer_sets_minimum(): void
    {
        $schema = $this->inferrer->inferRequestSchema(['field' => 'integer|min:1']);

        $this->assertSame(1, $schema['properties']['field']['minimum']);
        $this->assertArrayNotHasKey('minLength', $schema['properties']['field']);
    }

    public function test_max_on_integer_sets_maximum(): void
    {
        $schema = $this->inferrer->inferRequestSchema(['field' => 'integer|max:100']);

        $this->assertSame(100, $schema['properties']['field']['maximum']);
        $this->assertArrayNotHasKey('maxLength', $schema['properties']['field']);
    }

    public function test_in_rule_sets_enum(): void
    {
        $schema = $this->inferrer->inferRequestSchema(['field' => 'in:foo,bar,baz']);

        $this->assertSame(['foo', 'bar', 'baz'], $schema['properties']['field']['enum']);
    }

    public function test_digits_sets_min_and_max_length(): void
    {
        $schema = $this->inferrer->inferRequestSchema(['field' => 'digits:4']);

        $this->assertSame(4, $schema['properties']['field']['minLength']);
        $this->assertSame(4, $schema['properties']['field']['maxLength']);
    }

    public function test_dot_notation_fields_are_skipped(): void
    {
        $schema = $this->inferrer->inferRequestSchema([
            'address'      => 'array',
            'address.city' => 'required|string',
        ]);

        $this->assertArrayHasKey('address', $schema['properties']);
        $this->assertArrayNotHasKey('address.city', $schema['properties']);
    }

    public function test_array_rules_with_rule_objects_skips_non_strings(): void
    {
        $fakeRuleObject = new \stdClass(); // simulates a Rule object

        $schema = $this->inferrer->inferRequestSchema([
            'status' => ['required', 'string', $fakeRuleObject],
        ]);

        $this->assertSame('string', $schema['properties']['status']['type']);
        $this->assertSame(['status'], $schema['required']);
    }

    public function test_multiple_fields_produces_correct_properties(): void
    {
        $schema = $this->inferrer->inferRequestSchema([
            'name'  => 'required|string|max:100',
            'age'   => 'required|integer|min:0|max:150',
            'email' => 'required|email',
        ]);

        $this->assertCount(3, $schema['properties']);
        $this->assertSame(['name', 'age', 'email'], $schema['required']);
        $this->assertSame('email', $schema['properties']['email']['format']);
        $this->assertSame(100, $schema['properties']['name']['maxLength']);
        $this->assertSame(150, $schema['properties']['age']['maximum']);
    }
}
