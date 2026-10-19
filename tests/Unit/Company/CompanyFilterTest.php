<?php

declare(strict_types=1);

namespace Tests\Unit\Company;

use Core\Company\Application\Mappers\CompanyMapper;
use Core\Company\Domain\CompanyRules;
use Core\Company\Domain\Exceptions\InvalidCompanyException;
use Core\Company\Domain\ValueObjects\CompanyFilter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Covers the company list filter and how query string input becomes a
 * CompanyFilterDTO.
 */
final class CompanyFilterTest extends TestCase
{
    private CompanyMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new CompanyMapper();
    }

    public function test_it_defaults_an_absent_search_and_page_size(): void
    {
        $filter = new CompanyFilter();

        $this->assertNull($filter->search);
        $this->assertSame(15, $filter->perPage);
    }

    public function test_it_accepts_the_documented_page_size_bounds(): void
    {
        $smallest = new CompanyFilter(perPage: CompanyRules::MIN_PER_PAGE);
        $largest = new CompanyFilter(perPage: CompanyRules::MAX_PER_PAGE);

        $this->assertSame(CompanyRules::MIN_PER_PAGE, $smallest->perPage);
        $this->assertSame(CompanyRules::MAX_PER_PAGE, $largest->perPage);
    }

    public function test_it_rejects_a_page_size_below_the_lower_bound(): void
    {
        $this->expectException(InvalidCompanyException::class);

        new CompanyFilter(perPage: 0);
    }

    public function test_it_rejects_a_page_size_above_the_upper_bound(): void
    {
        $this->expectException(InvalidCompanyException::class);

        new CompanyFilter(perPage: CompanyRules::MAX_PER_PAGE + 1);
    }

    public function test_the_strict_mapper_defaults_an_absent_page_size(): void
    {
        $this->assertSame(15, $this->mapper->mapToFilterDTO([])->perPage);
        $this->assertNull($this->mapper->mapToFilterDTO([])->search);
    }

    public function test_the_strict_mapper_reads_every_filter(): void
    {
        $filterDTO = $this->mapper->mapToFilterDTO([
            'search' => 'acme',
            'per_page' => '25',
        ]);

        $this->assertSame('acme', $filterDTO->search);
        $this->assertSame(25, $filterDTO->perPage);
    }

    public function test_it_drops_a_blank_search_rather_than_matching_nothing(): void
    {
        $this->assertNull($this->mapper->mapToFilterDTO(['search' => '   '])->search);
    }

    public function test_it_trims_a_supplied_search(): void
    {
        $this->assertSame('acme', $this->mapper->mapToFilterDTO(['search' => '  acme  '])->search);
    }

    public function test_the_strict_mapper_rejects_a_non_string_search(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->mapper->mapToFilterDTO(['search' => ['nested' => 'array']]);
    }

    public function test_the_strict_mapper_rejects_an_out_of_range_page_size(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->mapper->mapToFilterDTO(['per_page' => '5000']);
    }

    public function test_the_strict_mapper_rejects_an_unparseable_page_size(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->mapper->mapToFilterDTO(['per_page' => 'lots']);
    }

    public function test_it_accepts_the_documented_page_size_bound(): void
    {
        $filterDTO = $this->mapper->mapToFilterDTO([
            'per_page' => (string) CompanyRules::MAX_PER_PAGE,
        ]);

        $this->assertSame(CompanyRules::MAX_PER_PAGE, $filterDTO->perPage);
    }
}
