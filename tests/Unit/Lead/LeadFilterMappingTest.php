<?php

declare(strict_types=1);

namespace Tests\Unit\Lead;

use Core\Lead\Application\Mappers\LeadMapper;
use Core\Lead\Domain\Enums\LeadStatus;
use Core\Lead\Domain\LeadRules;
use PHPUnit\Framework\TestCase;

/**
 * Covers how query string input becomes a LeadFilterDTO.
 */
final class LeadFilterMappingTest extends TestCase
{
    private LeadMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new LeadMapper();
    }

    public function test_it_defaults_every_filter_when_nothing_is_supplied(): void
    {
        [$filterDTO, $ignored] = $this->mapper->mapToFilterDTOLenient([]);

        $this->assertNull($filterDTO->status);
        $this->assertNull($filterDTO->source);
        $this->assertNull($filterDTO->search);
        $this->assertSame(15, $filterDTO->perPage);
        // An absent filter is not a dropped one.
        $this->assertSame([], $ignored);
    }

    public function test_it_reads_every_filter_from_the_query_string(): void
    {
        [$filterDTO, $ignored] = $this->mapper->mapToFilterDTOLenient([
            'status' => 'qualified',
            'source' => 'referral',
            'search' => 'acme',
            'per_page' => '25',
        ]);

        $this->assertSame(LeadStatus::QUALIFIED, $filterDTO->status);
        $this->assertSame('referral', $filterDTO->source);
        $this->assertSame('acme', $filterDTO->search);
        $this->assertSame(25, $filterDTO->perPage);
        $this->assertSame([], $ignored);
    }

    public function test_it_drops_a_bogus_status_and_reports_it(): void
    {
        [$filterDTO, $ignored] = $this->mapper->mapToFilterDTOLenient(['status' => 'bogus']);

        $this->assertNull($filterDTO->status);
        $this->assertSame(['status'], $ignored);
    }

    public function test_it_caps_an_out_of_range_page_size_and_reports_it(): void
    {
        [$filterDTO, $ignored] = $this->mapper->mapToFilterDTOLenient(['per_page' => '5000']);

        $this->assertSame(15, $filterDTO->perPage);
        $this->assertSame(['per_page'], $ignored);
    }

    public function test_it_accepts_the_documented_page_size_bound(): void
    {
        [$filterDTO, $ignored] = $this->mapper->mapToFilterDTOLenient([
            'per_page' => (string) LeadRules::MAX_PER_PAGE,
        ]);

        $this->assertSame(LeadRules::MAX_PER_PAGE, $filterDTO->perPage);
        $this->assertSame([], $ignored);
    }

    public function test_it_drops_a_blank_search_rather_than_matching_nothing(): void
    {
        [$filterDTO, $ignored] = $this->mapper->mapToFilterDTOLenient(['search' => '   ']);

        $this->assertNull($filterDTO->search);
        $this->assertSame([], $ignored);
    }

    public function test_it_drops_a_non_string_filter_and_reports_it(): void
    {
        [$filterDTO, $ignored] = $this->mapper->mapToFilterDTOLenient(['search' => ['nested' => 'array']]);

        $this->assertNull($filterDTO->search);
        $this->assertSame(['search'], $ignored);
    }

    public function test_it_reports_every_dropped_field_at_once(): void
    {
        [$filterDTO, $ignored] = $this->mapper->mapToFilterDTOLenient([
            'status' => 'bogus',
            'per_page' => 'abc',
        ]);

        $this->assertNull($filterDTO->status);
        $this->assertSame(15, $filterDTO->perPage);
        $this->assertSame(['status', 'per_page'], $ignored);
    }

    public function test_the_strict_mapper_reads_every_filter(): void
    {
        $filterDTO = $this->mapper->mapToFilterDTO([
            'status' => 'qualified',
            'source' => 'referral',
            'search' => 'acme',
            'per_page' => '25',
        ]);

        $this->assertSame(LeadStatus::QUALIFIED, $filterDTO->status);
        $this->assertSame('referral', $filterDTO->source);
        $this->assertSame('acme', $filterDTO->search);
        $this->assertSame(25, $filterDTO->perPage);
    }

    public function test_the_strict_mapper_defaults_an_absent_page_size(): void
    {
        $this->assertSame(15, $this->mapper->mapToFilterDTO([])->perPage);
    }

    /**
     * The API depends on the strict mapper rejecting bad input rather than
     * dropping it, so a bad filter cannot silently widen the result set.
     */
    public function test_the_strict_mapper_rejects_a_bogus_status(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->mapper->mapToFilterDTO(['status' => 'bogus']);
    }

    public function test_the_strict_mapper_rejects_an_out_of_range_page_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->mapper->mapToFilterDTO(['per_page' => '5000']);
    }

    public function test_the_strict_mapper_rejects_an_unparseable_page_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->mapper->mapToFilterDTO(['per_page' => 'lots']);
    }

    public function test_it_offers_a_label_for_every_status(): void
    {
        $options = $this->mapper->toStatusOptions();

        $this->assertCount(count(LeadStatus::cases()), $options);

        foreach ($options as $option) {
            $this->assertNotSame('', $option['label']);
            $this->assertNotSame('', $option['value']);
        }
    }
}
