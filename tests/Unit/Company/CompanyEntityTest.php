<?php

declare(strict_types=1);

namespace Tests\Unit\Company;

use Core\Company\Domain\CompanyRules;
use Core\Company\Domain\Entities\CompanyEntity;
use Core\Company\Domain\Exceptions\InvalidCompanyException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the CompanyEntity's own invariants. Pure - no mocks.
 */
final class CompanyEntityTest extends TestCase
{
    public function test_create_starts_without_an_id(): void
    {
        $entity = CompanyEntity::create(name: 'Acme Ltd');

        $this->assertNull($entity->id);
        $this->assertSame('Acme Ltd', $entity->name);
        $this->assertNull($entity->website);
        $this->assertNull($entity->industry);
        $this->assertNull($entity->phone);
        $this->assertNull($entity->address);
    }

    public function test_create_keeps_every_supplied_field(): void
    {
        $entity = CompanyEntity::create(
            name: 'Acme Ltd',
            website: 'https://acme.test',
            industry: 'Software',
            phone: '+1 555 0100',
            address: '1 Road, Town',
        );

        $this->assertSame('https://acme.test', $entity->website);
        $this->assertSame('Software', $entity->industry);
        $this->assertSame('+1 555 0100', $entity->phone);
        $this->assertSame('1 Road, Town', $entity->address);
    }

    public function test_create_rejects_a_blank_name(): void
    {
        $this->expectException(InvalidCompanyException::class);
        $this->expectExceptionMessage('A company must carry a name.');

        CompanyEntity::create(name: '   ');
    }

    public function test_create_rejects_a_name_longer_than_the_column(): void
    {
        $this->expectException(InvalidCompanyException::class);
        $this->expectExceptionMessage('cannot exceed 255 characters');

        CompanyEntity::create(name: str_repeat('a', CompanyRules::MAX_NAME_LENGTH + 1));
    }

    public function test_create_rejects_a_website_that_is_not_an_absolute_url(): void
    {
        $this->expectException(InvalidCompanyException::class);
        $this->expectExceptionMessage('must be a valid absolute URL');

        CompanyEntity::create(name: 'Acme Ltd', website: 'acme.test');
    }

    public function test_create_rejects_a_website_without_an_http_scheme(): void
    {
        $this->expectException(InvalidCompanyException::class);
        $this->expectExceptionMessage('must be a valid absolute URL');

        CompanyEntity::create(name: 'Acme Ltd', website: 'ftp://acme.test');
    }

    public function test_create_accepts_a_website_without_a_path(): void
    {
        $entity = CompanyEntity::create(name: 'Acme Ltd', website: 'https://acme.test');

        $this->assertSame('https://acme.test', $entity->website);
    }

    public function test_create_treats_an_empty_website_as_not_set(): void
    {
        $entity = CompanyEntity::create(name: 'Acme Ltd', website: '');

        $this->assertSame('', $entity->website);
    }

    public function test_create_rejects_an_industry_longer_than_the_column(): void
    {
        $this->expectException(InvalidCompanyException::class);
        $this->expectExceptionMessage('cannot exceed 100 characters');

        CompanyEntity::create(
            name: 'Acme Ltd',
            industry: str_repeat('a', CompanyRules::MAX_INDUSTRY_LENGTH + 1),
        );
    }

    public function test_create_rejects_a_phone_longer_than_the_column(): void
    {
        $this->expectException(InvalidCompanyException::class);
        $this->expectExceptionMessage('cannot exceed 50 characters');

        CompanyEntity::create(
            name: 'Acme Ltd',
            phone: str_repeat('5', CompanyRules::MAX_PHONE_LENGTH + 1),
        );
    }

    public function test_reconstitute_rebuilds_a_stored_row_without_rechecking_it(): void
    {
        // A row written before a rule existed still loads: reconstitution takes
        // the stored state as it is, so a legacy oversized name is not rejected.
        $entity = CompanyEntity::reconstitute(
            id: 7,
            name: str_repeat('a', CompanyRules::MAX_NAME_LENGTH + 25),
            createdAt: '2026-10-19 08:00:00',
            updatedAt: '2026-10-19 08:00:00',
        );

        $this->assertSame(7, $entity->id);
        $this->assertSame('2026-10-19 08:00:00', $entity->createdAt);
    }
}
