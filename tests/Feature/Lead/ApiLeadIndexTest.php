<?php

declare(strict_types=1);

namespace Tests\Feature\Lead;

use Core\Lead\Application\Contracts\LeadQueryContract;
use Core\Lead\Application\DTOs\Responses\LeadProjectionDTO;
use Core\Lead\Domain\Entities\LeadEntity;
use Core\Lead\Domain\Enums\LeadStatus;
use Core\Lead\Domain\ValueObjects\LeadFilter;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature cover for the API lead list endpoint.
 *
 * The endpoint must return the LeadResponseDTO payload as flat JSON, never an
 * Eloquent model, and must answer an unusable filter by reporting it rather
 * than failing the request.
 */
final class ApiLeadIndexTest extends TestCase
{
    use WithoutMiddleware;

    /** @var array<int, LeadFilter> */
    private array $receivedFilters = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindRecordingRepository();
    }

    public function test_it_returns_a_success_envelope_with_pagination_meta(): void
    {
        $this->bindRecordingRepository([$this->makeLead(id: 1, name: 'Acme Prospect')], total: 4, lastPage: 2);

        $response = $this->getJson('/api/v1/leads');

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('meta.total', 4);
        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.last_page', 2);
        $response->assertJsonPath('data.0.id', 1);
        $response->assertJsonPath('data.0.name', 'Acme Prospect');
    }

    /**
     * The payload is the response DTO, so it carries the labels and ISO
     * datetimes the DTO produces and nothing an Eloquent model would add.
     */
    public function test_it_returns_the_response_dto_shape(): void
    {
        $this->bindRecordingRepository([$this->makeLead(id: 1, name: 'Acme Prospect')]);

        $response = $this->getJson('/api/v1/leads');

        $response->assertOk();
        $response->assertJsonPath('data.0.status', 'qualified');
        $response->assertJsonPath('data.0.status_label', 'Qualified');
        $response->assertJsonPath('data.0.company_name', 'Acme Ltd');
        $response->assertJsonPath('data.0.created_at', '2026-10-05T10:13:16+00:00');

        $item = $response->json('data.0');

        // No raw model leaks in: the response exposes exactly the DTO fields.
        $this->assertSame([
            'id',
            'name',
            'email',
            'phone',
            'company_name',
            'status',
            'status_label',
            'source',
            'created_at',
            'updated_at',
        ], array_keys($item));
    }

    public function test_it_maps_the_query_string_onto_the_filter(): void
    {
        $this->getJson('/api/v1/leads?status=qualified&source=referral&search=acme&per_page=25');

        $filter = $this->receivedFilters[0];

        $this->assertSame(LeadStatus::QUALIFIED, $filter->status);
        $this->assertSame('referral', $filter->source);
        $this->assertSame('acme', $filter->search);
        $this->assertSame(25, $filter->perPage);
    }

    /**
     * An API client means the filters it sends, so an unusable value is a
     * client error rather than something silently dropped. Dropping it would
     * return a wider list than the caller asked for.
     */
    #[DataProvider('unmappableFilterValues')]
    public function test_an_unmappable_filter_is_rejected_with_422(string $field, string $value): void
    {
        $response = $this->getJson('/api/v1/leads?' . $field . '=' . urlencode($value));

        $response->assertStatus(422);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('error_code', 'LEAD_INVALID');
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
            'out of range page size' => ['per_page', '5000'],
            'unparseable page size' => ['per_page', 'lots'],
        ];
    }

    public function test_a_usable_filter_is_accepted(): void
    {
        $response = $this->getJson('/api/v1/leads?status=qualified&per_page=25');

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
    }

    public function test_it_returns_an_empty_data_set_without_erroring(): void
    {
        $response = $this->getJson('/api/v1/leads');

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data', []);
        $response->assertJsonPath('meta.total', 0);
    }

    public function test_it_returns_a_single_lead_as_json(): void
    {
        $this->bindRecordingRepository([$this->makeLead(id: 7, name: 'Acme Prospect')]);

        $response = $this->getJson('/api/v1/leads/7');

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.id', 7);
        $response->assertJsonPath('data.name', 'Acme Prospect');
    }

    /**
     * A lead that does not exist is a missing resource, so it must be a 404
     * rather than the 400 a generic failure would produce.
     */
    public function test_it_returns_404_for_a_missing_lead(): void
    {
        $response = $this->getJson('/api/v1/leads/999999');

        $response->assertStatus(404);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('error_code', 'LEAD_NOT_FOUND');
    }

    private function bindRecordingRepository(
        array $items = [],
        ?int $total = null,
        int $lastPage = 1,
    ): void {
        $repository = new class($items, $total ?? count($items), $lastPage, $this) implements LeadQueryContract {
            /**
             * @param array<int, LeadEntity> $items
             */
            public function __construct(
                private array $items,
                private int $total,
                private int $lastPage,
                private ApiLeadIndexTest $test,
            ) {}

            public function getFilteredLeads(LeadFilter $filter): PaginatedResult
            {
                $this->test->recordFilter($filter);

                $projections = array_map(
                    fn(LeadEntity $entity) => new LeadProjectionDTO(
                        entity: $entity,
                        createdAt: new \DateTimeImmutable('2026-10-05 10:13:16'),
                        updatedAt: new \DateTimeImmutable('2026-10-05 10:13:16'),
                    ),
                    $this->items,
                );

                return new PaginatedResult(
                    items: $projections,
                    total: $this->total,
                    currentPage: 1,
                    perPage: $filter->perPage,
                    lastPage: $this->lastPage,
                );
            }

            public function findById(int $id): ?LeadProjectionDTO
            {
                foreach ($this->items as $entity) {
                    if ($entity->id === $id) {
                        return new LeadProjectionDTO(
                            entity: $entity,
                            createdAt: new \DateTimeImmutable('2026-10-05 10:13:16'),
                            updatedAt: new \DateTimeImmutable('2026-10-05 10:13:16'),
                        );
                    }
                }

                return null;
            }
        };

        $this->app->instance(LeadQueryContract::class, $repository);
    }

    /**
     * Called by the bound repository stub so the test can inspect the criteria.
     */
    public function recordFilter(LeadFilter $filter): void
    {
        $this->receivedFilters[] = $filter;
    }

    private function makeLead(int $id, string $name): LeadEntity
    {
        return new LeadEntity(
            id: $id,
            name: $name,
            email: 'prospect@acme.test',
            phone: '555-0100',
            companyName: 'Acme Ltd',
            status: LeadStatus::QUALIFIED,
            source: 'referral',
            createdAt: '2026-10-05 10:13:16',
            updatedAt: '2026-10-05 10:13:16',
        );
    }
}
