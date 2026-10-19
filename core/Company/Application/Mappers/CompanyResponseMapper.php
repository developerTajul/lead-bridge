<?php

declare(strict_types=1);

namespace Core\Company\Application\Mappers;

use Core\Company\Application\DTOs\Responses\CompanyActivityResponseDTO;
use Core\Company\Application\DTOs\Responses\CompanyContactResponseDTO;
use Core\Company\Application\DTOs\Responses\CompanyDealResponseDTO;
use Core\Company\Application\DTOs\Responses\CompanyDealStageResponseDTO;
use Core\Company\Application\DTOs\Responses\CompanyDetailResponseDTO;
use Core\Company\Application\DTOs\Responses\CompanyEditResponseDTO;
use Core\Company\Application\DTOs\Responses\CompanyResponseDTO;
use Core\Company\Domain\DataObjects\CompanyDetailData;
use Core\Company\Domain\Entities\CompanyEntity;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Maps a company entity to CompanyResponseDTO.
 */
final class CompanyResponseMapper
{
    /**
     * Builds the response DTO from the company entity. Timestamps are stored as
     * UTC wall-clock strings and leave on the same ISO-8601 wire format the
     * other modules use, so a client parses every module's dates alike.
     *
     * @param CompanyEntity $company
     *
     * @return CompanyResponseDTO
     */
    public function toResponseDTO(CompanyEntity $company): CompanyResponseDTO
    {
        return new CompanyResponseDTO(
            id: $company->id,
            name: $company->name,
            website: $company->website,
            industry: $company->industry,
            phone: $company->phone,
            address: $company->address,
            created_at: $this->toAtom($company->createdAt),
            updated_at: $this->toAtom($company->updatedAt),
        );
    }

    /**
     * Builds the edit-form DTO from the company entity. Only the editable
     * fields travel, since a form has nothing to do with the timestamps the
     * full response DTO carries.
     *
     * @param CompanyEntity $company
     *
     * @return CompanyEditResponseDTO
     */
    public function toEditResponseDTO(CompanyEntity $company): CompanyEditResponseDTO
    {
        return new CompanyEditResponseDTO(
            id: (int) $company->id,
            name: $company->name,
            website: $company->website,
            industry: $company->industry,
            phone: $company->phone,
            address: $company->address,
        );
    }

    /**
     * Builds the 360-degree profile DTO: the company's own fields plus its
     * contacts, deals and each deal's activities. This is the one place the
     * flattened rows the repository returns become nested DTOs.
     *
     * @param CompanyDetailData $companyDetail
     *
     * @return CompanyDetailResponseDTO
     */
    public function toDetailResponseDTO(CompanyDetailData $companyDetail): CompanyDetailResponseDTO
    {
        $company = $companyDetail->company;

        return new CompanyDetailResponseDTO(
            id: $company->id,
            name: $company->name,
            website: $company->website,
            industry: $company->industry,
            phone: $company->phone,
            address: $company->address,
            created_at: $this->toAtom($company->createdAt),
            updated_at: $this->toAtom($company->updatedAt),
            contacts: array_map($this->toContactDTO(...), $companyDetail->contacts),
            deals: array_map($this->toDealDTO(...), $companyDetail->deals),
        );
    }

    /**
     * @param array<string, mixed> $contact
     *
     * @return CompanyContactResponseDTO
     */
    private function toContactDTO(array $contact): CompanyContactResponseDTO
    {
        return new CompanyContactResponseDTO(
            id: $contact['id'],
            name: (string) $contact['name'],
            email: $contact['email'],
            phone: $contact['phone'],
            job_title: $contact['job_title'],
            is_primary: (bool) $contact['is_primary'],
        );
    }

    /**
     * @param array<string, mixed> $deal
     *
     * @return CompanyDealResponseDTO
     */
    private function toDealDTO(array $deal): CompanyDealResponseDTO
    {
        $stage = $deal['stage'];

        return new CompanyDealResponseDTO(
            id: $deal['id'],
            title: (string) $deal['title'],
            amount: (string) $deal['amount'],
            currency: (string) $deal['currency'],
            expected_close_date: $this->toDate($deal['expected_close_date']),
            actual_close_date: $this->toDate($deal['actual_close_date']),
            stage: $stage === null ? null : $this->toDealStageDTO($stage),
            activities: array_map($this->toActivityDTO(...), $deal['activities']),
        );
    }

    /**
     * @param array<string, mixed> $stage
     *
     * @return CompanyDealStageResponseDTO
     */
    private function toDealStageDTO(array $stage): CompanyDealStageResponseDTO
    {
        return new CompanyDealStageResponseDTO(
            id: $stage['id'],
            name: (string) $stage['name'],
            order_position: $stage['order_position'],
            probability: $stage['probability'],
        );
    }

    /**
     * @param array<string, mixed> $activity
     *
     * @return CompanyActivityResponseDTO
     */
    private function toActivityDTO(array $activity): CompanyActivityResponseDTO
    {
        return new CompanyActivityResponseDTO(
            id: $activity['id'],
            type: (string) $activity['type'],
            subject: (string) $activity['subject'],
            description: $activity['description'],
            due_date: $this->toAtom($activity['due_date']),
            completed_at: $this->toAtom($activity['completed_at']),
            user_name: $activity['user_name'],
        );
    }

    /**
     * Formats a stored UTC timestamp as ISO-8601. Parsing it in the application
     * timezone would shift the instant on every round trip.
     *
     * @param string|null $value
     *
     * @return string|null
     */
    private function toAtom(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->format(DateTimeInterface::ATOM);
    }

    /**
     * A close date is a calendar day, so it leaves as one rather than as an
     * instant a client would have to localise back.
     *
     * @param string|null $value
     *
     * @return string|null
     */
    private function toDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (new DateTimeImmutable($value))->format('Y-m-d');
    }
}
