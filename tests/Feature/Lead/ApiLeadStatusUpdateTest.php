<?php

declare(strict_types=1);

namespace Tests\Feature\Lead;

use Core\Lead\Application\Contracts\LeadServiceInterface;
use Core\Lead\Application\DTOs\Requests\LeadCreateDTO;
use Core\Lead\Application\DTOs\Requests\LeadFilterDTO;
use Core\Lead\Application\DTOs\Responses\LeadResponseDTO;
use Core\Lead\Domain\Enums\LeadStatus;
use Core\Shared\Application\DTOs\Result;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

/**
 * Feature cover for the manual lead status endpoint.
 *
 * A refused transition must reach the client as a 422 carrying the domain's
 * own wording, and a successful one must answer with the same payload shape
 * the rest of the lead API uses.
 */
final class ApiLeadStatusUpdateTest extends TestCase
{
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindStubService();
    }

    public function test_it_changes_the_status_and_returns_the_lead(): void
    {
        $response = $this->patchJson('/api/v1/leads/7/status', ['status' => 'contacted']);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.id', 7);
    }

    public function test_it_passes_the_mapped_status_enum_to_the_service(): void
    {
        $this->patchJson('/api/v1/leads/7/status', ['status' => 'qualified']);

        $service = $this->app->make(LeadServiceInterface::class);

        $this->assertSame(7, $service->receivedLeadId);
        $this->assertSame(LeadStatus::QUALIFIED, $service->receivedStatus);
    }

    /**
     * An illegal transition has to surface the domain message rather than a
     * generic failure, so the client learns why it was refused.
     */
    public function test_it_returns_422_with_the_domain_message_for_a_refused_transition(): void
    {
        $this->app->instance(LeadServiceInterface::class, $this->makeStubService(
            changeStatusResult: Result::failure(
                'A lead cannot move from Converted to New.',
                'LEAD_INVALID',
            ),
        ));

        $response = $this->patchJson('/api/v1/leads/7/status', ['status' => 'new']);

        $response->assertStatus(422);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('error_code', 'LEAD_INVALID');
        $response->assertJsonPath('message', 'A lead cannot move from Converted to New.');
    }

    public function test_it_returns_404_when_the_lead_does_not_exist(): void
    {
        $this->app->instance(LeadServiceInterface::class, $this->makeStubService(
            changeStatusResult: Result::failure('Lead not found.', 'LEAD_NOT_FOUND'),
        ));

        $response = $this->patchJson('/api/v1/leads/999999/status', ['status' => 'contacted']);

        $response->assertStatus(404);
        $response->assertJsonPath('error_code', 'LEAD_NOT_FOUND');
    }

    public function test_it_rejects_an_unknown_status_with_422(): void
    {
        $response = $this->patchJson('/api/v1/leads/7/status', ['status' => 'archived']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');
    }

    public function test_it_rejects_a_missing_status_with_422(): void
    {
        $response = $this->patchJson('/api/v1/leads/7/status', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');
    }

    /**
     * The write itself is what answers the request now: there is no read-back
     * to fail, so a successful write is a successful response and the controller
     * never touches the read port.
     */
    public function test_it_answers_from_the_write_without_reading_the_lead_back(): void
    {
        $this->app->instance(LeadServiceInterface::class, $this->makeStubService(
            readResult: Result::failure('Failed to retrieve lead.', 'LEAD_FETCH_FAILED'),
        ));

        $response = $this->patchJson('/api/v1/leads/7/status', ['status' => 'contacted']);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.id', 7);
        $response->assertJsonPath('data.status', 'contacted');
    }

    private function bindStubService(): void
    {
        $this->app->instance(LeadServiceInterface::class, $this->makeStubService());
    }

    private function makeStubService(
        ?Result $changeStatusResult = null,
        ?Result $readResult = null,
    ): LeadServiceInterface {
        return new class($changeStatusResult, $readResult) implements LeadServiceInterface {
            public ?int $receivedLeadId = null;

            public ?LeadStatus $receivedStatus = null;

            public function __construct(
                private ?Result $changeStatusResult,
                private ?Result $readResult,
            ) {}

            public function captureLead(LeadCreateDTO $dto): void {}

            public function createLead(LeadCreateDTO $leadCreateDTO): Result
            {
                return Result::success();
            }

            public function getLeads(LeadFilterDTO $leadFilterDTO): Result
            {
                return Result::success();
            }

            public function getLeadById(int $id): Result
            {
                return $this->readResult ?? Result::success(
                    data: $this->makeResponseDTO($id),
                    message: 'Lead retrieved successfully.',
                );
            }

            public function changeStatus(int $leadId, LeadStatus $status): Result
            {
                $this->receivedLeadId = $leadId;
                $this->receivedStatus = $status;

                return $this->changeStatusResult ?? Result::success(
                    data: $this->makeResponseDTO($leadId),
                    message: 'Lead status updated successfully.',
                );
            }

            private function makeResponseDTO(int $id): LeadResponseDTO
            {
                return new LeadResponseDTO(
                    id: $id,
                    name: 'Acme Prospect',
                    email: 'prospect@acme.test',
                    phone: '555-0100',
                    company_name: 'Acme Ltd',
                    status: 'contacted',
                    status_label: 'Contacted',
                    source: 'referral',
                    created_at: '2026-10-05T10:13:16+00:00',
                    updated_at: '2026-10-05T10:13:16+00:00',
                );
            }
        };
    }
}
