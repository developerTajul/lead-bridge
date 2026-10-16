<?php

declare(strict_types=1);

namespace Tests\Feature\Activity;

use Core\Activity\Application\Contracts\ActivityQueryContract;
use Core\Activity\Application\DTOs\Responses\ActivityProjectionDTO;
use Core\Activity\Domain\ValueObjects\ActivityFilter;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature cover for the API list endpoint's rejection of an unmappable filter.
 *
 * The mapper rejects bad query values with InvalidArgumentException, which the
 * controller translates to 422. These tests pin that mapping: if the exception
 * type or the catch ever drifts apart again, a bad filter would surface as an
 * unhandled 500 instead.
 */
final class ApiActivityIndexValidationTest extends TestCase
{
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(ActivityQueryContract::class, new class implements ActivityQueryContract {
            public function getFilteredActivities(ActivityFilter $filter): PaginatedResult
            {
                return new PaginatedResult([], 0, 1, $filter->perPage, 1);
            }

            public function findById(int $id): ?ActivityProjectionDTO
            {
                return null;
            }
        });
    }

    /**
     * A value the mapper cannot convert is client error input, so it must be
     * answered as 422 rather than escaping as an unhandled exception.
     */
    #[DataProvider('unmappableFilterValues')]
    public function test_an_unmappable_filter_is_rejected_with_422(string $field, string $value): void
    {
        $response = $this->getJson('/api/v1/activities?' . $field . '=' . urlencode($value));

        $response->assertStatus(422);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('error_code', 'ACTIVITY_INVALID');
        $this->assertNotSame('', (string) $response->json('message'));
    }

    /**
     * Each case pairs the query field with a value the mapper rejects.
     *
     * @return array<string, array{string, string}>
     */
    public static function unmappableFilterValues(): array
    {
        return [
            'unknown status' => ['status', 'archived'],
            'unknown type' => ['type', 'sms'],
            'unknown period' => ['filter', 'tomorrow'],
            'non-numeric user' => ['user_id', 'someone'],
            'zero user' => ['user_id', '0'],
            'out of range page size' => ['per_page', '500'],
            'unparseable page size' => ['per_page', 'lots'],
        ];
    }

    public function test_a_usable_filter_is_accepted(): void
    {
        $response = $this->getJson('/api/v1/activities?status=pending&per_page=25');

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
    }
}
