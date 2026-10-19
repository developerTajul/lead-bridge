<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use Core\Company\Application\Contracts\CompanyQueryContract;
use Core\Company\Domain\DataObjects\CompanyDetailData;
use Core\Company\Domain\Entities\CompanyEntity;
use Core\Company\Domain\ValueObjects\CompanyFilter;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature cover for the API company list endpoint.
 *
 * The endpoint must return the CompanyResponseDTO payload as flat JSON with
 * pagination meta, never an Eloquent paginator, and must answer an unusable
 * filter by reporting it rather than failing the request.
 *
 * The read port is stubbed rather than the database used: this machine has no
 * sqlite driver, so the suite's pinned in-memory connection is unreachable.
 */
final class ApiCompanyIndexTest extends TestCase
{
    use WithoutMiddleware;

    /** @var array<int, CompanyFilter> */
    private array $receivedFilters = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindRecordingRepository();
    }

    public function test_it_returns_a_success_envelope_with_pagination_meta(): void
    {
        $this->bindRecordingRepository([$this->makeCompany(id: 1, name: 'Acme Ltd')], total: 4, lastPage: 2);

        $response = $this->getJson('/api/v1/companies');

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('meta.total', 4);
        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.last_page', 2);
        $response->assertJsonPath('data.0.id', 1);
        $response->assertJsonPath('data.0.name', 'Acme Ltd');
    }

    /**
     * The payload is the response DTO, so it carries the fields the DTO
     * produces and nothing an Eloquent model or paginator would add.
     */
    public function test_it_returns_the_response_dto_shape(): void
    {
        $this->bindRecordingRepository([$this->makeCompany(id: 1, name: 'Acme Ltd')]);

        $response = $this->getJson('/api/v1/companies');

        $response->assertOk();
        $response->assertJsonPath('data.0.created_at', '2026-10-05T10:13:16+00:00');

        $item = $response->json('data.0');

        $this->assertSame([
            'id',
            'name',
            'website',
            'industry',
            'phone',
            'address',
            'created_at',
            'updated_at',
        ], array_keys($item));
    }

    /**
     * The raw paginator used to be the payload, so its keys must be gone.
     */
    public function test_it_does_not_leak_paginator_links(): void
    {
        $this->bindRecordingRepository([$this->makeCompany(id: 1, name: 'Acme Ltd')]);

        $payload = $this->getJson('/api/v1/companies')->json();

        $this->assertArrayNotHasKey('links', $payload);
        $this->assertArrayNotHasKey('path', $payload);
        $this->assertArrayNotHasKey('from', $payload);
        $this->assertArrayNotHasKey('to', $payload);
    }

    public function test_it_maps_the_query_string_onto_the_filter(): void
    {
        $this->getJson('/api/v1/companies?search=acme&per_page=25');

        $filter = $this->receivedFilters[0];

        $this->assertSame('acme', $filter->search);
        $this->assertSame(25, $filter->perPage);
    }

    public function test_index_defaults_the_filter_when_nothing_is_supplied(): void
    {
        $this->getJson('/api/v1/companies');

        $filter = $this->receivedFilters[0];

        $this->assertNull($filter->search);
        $this->assertSame(15, $filter->perPage);
    }

    /**
     * An API client means the filters it sends, so an unusable value is a
     * client error rather than something silently dropped. Dropping it would
     * return a wider list than the caller asked for.
     */
    #[DataProvider('unmappableFilterValues')]
    public function test_an_unmappable_filter_is_rejected_with_422(string $field, string $value): void
    {
        $response = $this->getJson('/api/v1/companies?' . $field . '=' . urlencode($value));

        $response->assertStatus(422);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('error_code', 'COMPANY_INVALID');
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
            'out of range page size' => ['per_page', '5000'],
            'unparseable page size' => ['per_page', 'lots'],
        ];
    }

    public function test_a_usable_filter_is_accepted(): void
    {
        $response = $this->getJson('/api/v1/companies?search=acme&per_page=25');

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
    }

    public function test_it_returns_an_empty_data_set_without_erroring(): void
    {
        $response = $this->getJson('/api/v1/companies');

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data', []);
        $response->assertJsonPath('meta.total', 0);
    }

    private function bindRecordingRepository(
        array $items = [],
        ?int $total = null,
        int $lastPage = 1,
    ): void {
        $repository = new class($items, $total ?? count($items), $lastPage, $this) implements CompanyQueryContract {
            /**
             * @param array<int, CompanyEntity> $items
             */
            public function __construct(
                private array $items,
                private int $total,
                private int $lastPage,
                private ApiCompanyIndexTest $test,
            ) {}

            public function getFilteredCompanies(CompanyFilter $filter): PaginatedResult
            {
                $this->test->recordFilter($filter);

                return new PaginatedResult(
                    items: $this->items,
                    total: $this->total,
                    currentPage: 1,
                    perPage: $filter->perPage,
                    lastPage: $this->lastPage,
                );
            }

            /**
             * The list endpoint never asks for a detail read; the contract
             * still has to be satisfied in full.
             */
            public function findDetailById(string $id): ?CompanyDetailData
            {
                return null;
            }
        };

        $this->app->instance(CompanyQueryContract::class, $repository);
    }

    /**
     * Called by the bound repository stub so the test can inspect the criteria.
     */
    public function recordFilter(CompanyFilter $filter): void
    {
        $this->receivedFilters[] = $filter;
    }

    private function makeCompany(int $id, string $name): CompanyEntity
    {
        return CompanyEntity::reconstitute(
            id: $id,
            name: $name,
            website: 'https://acme.test',
            industry: 'Software',
            phone: '+1 555 0100',
            address: '1 Road, Town',
            createdAt: '2026-10-05 10:13:16',
            updatedAt: '2026-10-05 10:13:16',
        );
    }
}
