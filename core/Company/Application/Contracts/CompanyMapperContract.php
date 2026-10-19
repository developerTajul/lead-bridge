<?php

declare(strict_types=1);

namespace Core\Company\Application\Contracts;

use Core\Company\Application\DTOs\Requests\CompanyFilterDTO;
use Core\Company\Application\DTOs\Requests\StoreCompanyDTO;
use Core\Company\Application\DTOs\Requests\UpdateCompanyDTO;
use Core\Company\Application\DTOs\Responses\CompanyEditResponseDTO;

/**
 * PORT: request-to-DTO mapping for companies.
 */
interface CompanyMapperContract
{
    /**
     * Builds a StoreCompanyDTO from validated input.
     *
     * @param array $validatedData
     *
     * @return StoreCompanyDTO
     */
    public function mapToStoreDTO(array $validatedData): StoreCompanyDTO;

    /**
     * Builds an UpdateCompanyDTO from validated input and the route-bound ID.
     *
     * @param int   $id            The company ID from the route.
     * @param array $validatedData The validated input data from the delivery layer.
     *
     * @return UpdateCompanyDTO
     */
    public function mapToUpdateDTO(int $id, array $validatedData): UpdateCompanyDTO;

    /**
     * Builds a CompanyFilterDTO from query input, rejecting unrecognised values.
     *
     * @param array $queryParams The raw query string values.
     *
     * @return CompanyFilterDTO
     *
     * @throws \InvalidArgumentException When a supplied value is not usable.
     */
    public function mapToFilterDTO(array $queryParams): CompanyFilterDTO;

    /**
     * Builds a CompanyEditResponseDTO from query input, rejecting unrecognised
     * values. Serves the same job as mapToFilterDTO: an unusable page size is
     * bad input rather than something to drop.
     *
     * @param array $queryParams The raw query string values.
     *
     * @return CompanyEditResponseDTO
     *
     * @throws \InvalidArgumentException When a supplied value is not usable.
     */
    public function mapToEditDTO(array $queryParams): CompanyEditResponseDTO;
}
