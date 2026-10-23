<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use Core\Company\Application\Contracts\CompanyQueryContract;
use Core\Company\Domain\DataObjects\CompanyDetailData;
use Core\Company\Domain\Entities\CompanyEntity;
use Core\Company\Domain\ValueObjects\CompanyFilter;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

/**
 * Feature cover for the API company detail endpoint.
 *
 * The endpoint must return the CompanyDetailResponseDTO payload - the company
 * with its contacts, deals, stages and deal activities nested as DTOs - and
 * must answer an unknown id with a 404 rather than an empty success.
 *
 * The read port is stubbed rather than the database used: this machine has no
 * sqlite driver, so the suite's pinned in-memory connection is unreachable.
 */
final class ApiCompanyShowTest extends TestCase
{
    use WithoutMiddleware;

    /** @var array<int, string> */
    private array $receivedIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindRecordingRepository();
    }

    public function test_it_returns_the_company_with_its_related_records(): void
    {
        $this->bindRecordingRepository($this->makeCompanyDetail());

        $response = $this->getJson('/api/v1/companies/7');

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('message', 'Company retrieved successfully.');
        $response->assertJsonPath('data.id', 7);
        $response->assertJsonPath('data.name', 'Acme Ltd');
        $response->assertJsonPath('data.contacts.0.name', 'Jane Doe');
        $response->assertJsonPath('data.contacts.0.job_title', 'Head of Ops');
        $response->assertJsonPath('data.deals.0.title', 'Renewal');
        $response->assertJsonPath('data.deals.0.stage.name', 'Negotiation');
        $response->assertJsonPath('data.deals.0.activities.0.subject', 'Follow up sent');
        $response->assertJsonPath('data.deals.0.activities.0.user_name', 'Aisha Rahman');
    }

    /**
     * The raw Eloquent model used to be the payload, so its internals must be
     * gone: no pivot blob and no morph bookkeeping on the wire.
     */
    public function test_it_does_not_leak_the_eloquent_model_shape(): void
    {
        $this->bindRecordingRepository($this->makeCompanyDetail());

        $contact = $this->getJson('/api/v1/companies/7')->json('data.contacts.0');

        $this->assertSame([
            'id',
            'name',
            'email',
            'phone',
            'job_title',
            'is_primary',
        ], array_keys($contact));
        $this->assertArrayNotHasKey('pivot', $contact);
        $this->assertArrayNotHasKey('trackable_type', $this->getJson('/api/v1/companies/7')->json('data'));
    }

    public function test_the_timestamps_leave_on_the_iso_8601_wire_format(): void
    {
        $this->bindRecordingRepository($this->makeCompanyDetail());

        $response = $this->getJson('/api/v1/companies/7');

        $response->assertJsonPath('data.created_at', '2026-10-05T10:13:16+00:00');
        $response->assertJsonPath('data.deals.0.activities.0.due_date', '2026-10-05T10:13:16+00:00');
    }

    public function test_an_unknown_company_is_reported_as_not_found(): void
    {
        $response = $this->getJson('/api/v1/companies/404');

        $response->assertStatus(404);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('error_code', 'COMPANY_NOT_FOUND');
        $response->assertJsonPath('message', 'Company not found.');
    }

    /**
     * The route hands the id through verbatim; the delivery adapter must not
     * reshape it before the Service sees it.
     */
    public function test_it_passes_the_route_id_to_the_query_contract(): void
    {
        $this->getJson('/api/v1/companies/7');

        $this->assertSame(['7'], $this->receivedIds);
    }

    private function bindRecordingRepository(?CompanyDetailData $detail = null): void
    {
        $repository = new class($detail, $this) implements CompanyQueryContract {
            public function __construct(
                private ?CompanyDetailData $detail,
                private ApiCompanyShowTest $test,
            ) {}

            public function getFilteredCompanies(CompanyFilter $filter): PaginatedResult
            {
                return new PaginatedResult(
                    items: [],
                    total: 0,
                    currentPage: 1,
                    perPage: $filter->perPage,
                    lastPage: 1,
                );
            }

            public function findDetailById(string $id): ?CompanyDetailData
            {
                $this->test->recordId($id);

                return $this->detail;
            }

            /**
             * The detail endpoint this test covers reads through
             * findDetailById(), so the single company read is not exercised
             * here and reports a missing company.
             */
            public function findById(int $id): ?CompanyEntity
            {
                return null;
            }
        };

        $this->app->instance(CompanyQueryContract::class, $repository);
    }

    /**
     * Called by the bound repository stub so the test can inspect the id.
     */
    public function recordId(string $id): void
    {
        $this->receivedIds[] = $id;
    }

    private function makeCompanyDetail(): CompanyDetailData
    {
        return new CompanyDetailData(
            company: CompanyEntity::reconstitute(
                id: 7,
                name: 'Acme Ltd',
                website: 'https://acme.test',
                industry: 'Software',
                phone: '+1 555 0100',
                address: '1 Road, Town',
                createdAt: '2026-10-05 10:13:16',
                updatedAt: '2026-10-05 10:13:16',
            ),
            contacts: [[
                'id' => 3,
                'name' => 'Jane Doe',
                'email' => 'jane@acme.test',
                'phone' => '+1 555 0111',
                'job_title' => 'Head of Ops',
                'is_primary' => true,
            ]],
            deals: [[
                'id' => 11,
                'title' => 'Renewal',
                'amount' => '2500.00',
                'currency' => 'BDT',
                'expected_close_date' => '2026-12-01',
                'actual_close_date' => null,
                'stage' => [
                    'id' => 2,
                    'name' => 'Negotiation',
                    'order_position' => 3,
                    'probability' => 70,
                ],
                'activities' => [[
                    'id' => 21,
                    'type' => 'call',
                    'subject' => 'Follow up sent',
                    'description' => 'Left a voicemail.',
                    'due_date' => '2026-10-05 10:13:16',
                    'completed_at' => null,
                    'user_name' => 'Aisha Rahman',
                ]],
            ]],
            activities: [],
        );
    }
}
