<?php

declare(strict_types=1);

namespace ScalE\Tests;

use PHPUnit\Framework\TestCase;
use ScalE\Http\Request;
use ScalE\Http\Router;
use ScalE\Validation\Validator;

final class ApiTest extends TestCase
{
    private string|false $originalApiKeys;

    protected function setUp(): void
    {
        $this->originalApiKeys = getenv('API_KEYS');
        putenv('API_KEYS=test-api-key');
    }

    protected function tearDown(): void
    {
        if ($this->originalApiKeys === false) {
            putenv('API_KEYS');
        } else {
            putenv('API_KEYS=' . $this->originalApiKeys);
        }

        http_response_code(200);
    }

    public function testEventValidationAcceptsValidPayload(): void
    {
        $payload = [
            'customer' => [
                'email' => 'John@Example.com',
                'name' => 'John Doe',
            ],
            'event' => 'purchase',
            'properties' => [
                'amount' => 120,
                'product' => 'Shoes',
            ],
            'timestamp' => '2026-04-10T12:00:00Z',
        ];

        $validated = Validator::validateEventPayload($payload);

        $this->assertSame('john@example.com', $validated['customer']['email']);
        $this->assertSame('purchase', $validated['event']);
        $this->assertSame(120, $validated['properties']['amount']);
    }

    public function testSegmentValidationRequiresConditionFields(): void
    {
        $payload = [
            'conditions' => [
                [
                    'event' => 'purchase',
                    'property' => 'amount',
                    'operator' => '>',
                    'value' => 100,
                ],
            ],
        ];

        $validated = Validator::validateSegmentQuery($payload);
        $this->assertCount(1, $validated['conditions']);
    }

    public function testEventValidationRejectsEventCodesLongerThanTwentyCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Validator::validateEventPayload([
            'customer' => [
                'email' => 'john@example.com',
                'name' => 'John Doe',
            ],
            'event' => str_repeat('a', 21),
            'timestamp' => '2026-04-10T12:00:00Z',
        ]);
    }

    public function testApiRoutesRequireAValidBearerKey(): void
    {
        $this->assertSame(401, $this->dispatchStatus('/api/unknown'));
        $this->assertSame(401, $this->dispatchStatus('/api/unknown', 'Bearer wrong-key'));
        $this->assertSame(404, $this->dispatchStatus('/api/unknown', 'Bearer test-api-key'));
    }

    public function testDashboardApiRemainsPublic(): void
    {
        $this->assertSame(400, $this->dispatchStatus('/dashboard-api/customers', null, ['page' => 0]));
    }

    public function testCustomerPaginationRejectsInvalidParameters(): void
    {
        $this->assertSame(400, $this->dispatchStatus('/api/customers', 'Bearer test-api-key', ['page' => 'abc']));
        $this->assertSame(400, $this->dispatchStatus('/api/customers', 'Bearer test-api-key', ['per_page' => 51]));
        $this->assertSame(400, $this->dispatchStatus('/api/customers', 'Bearer test-api-key', ['order_by' => 'id']));
        $this->assertSame(400, $this->dispatchStatus('/api/customers', 'Bearer test-api-key', ['order_by' => 'name; DROP TABLE customers']));
        $this->assertSame(400, $this->dispatchStatus('/api/customers', 'Bearer test-api-key', ['order' => 'random']));
    }

    private function dispatchStatus(string $path, ?string $authorization = null, array $query = []): int
    {
        $headers = $authorization === null ? [] : ['Authorization' => $authorization];
        $response = (new Router())->dispatch(new Request('GET', $path, $query, [], $headers));

        ob_start();
        $response->send();
        ob_end_clean();

        $statusCode = http_response_code();
        return is_int($statusCode) ? $statusCode : 200;
    }
}
