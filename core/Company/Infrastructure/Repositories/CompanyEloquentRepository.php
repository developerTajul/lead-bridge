<?php

declare(strict_types=1);

namespace Core\Company\Infrastructure\Repositories;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use Core\Company\Application\Contracts\CompanyQueryContract;
use Core\Company\Domain\Contracts\CompanyRepositoryContract;
use Core\Company\Domain\DataObjects\CompanyDetailData;
use Core\Company\Domain\Entities\CompanyEntity;
use Core\Company\Domain\ValueObjects\CompanyFilter;
use Core\Company\Infrastructure\Mappers\CompanyEntityMapper;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Core\Shared\Infrastructure\Repositories\BaseRepository;
use RuntimeException;

/**
 * Eloquent-backed persistence for companies. The only place in the module
 * where Laravel/Eloquent is used; results are mapped to Domain Entities first.
 *
 * @implements \Core\Company\Domain\Contracts\CompanyRepositoryContract
 * @implements \Core\Company\Application\Contracts\CompanyQueryContract
 */
final class CompanyEloquentRepository extends BaseRepository implements CompanyRepositoryContract, CompanyQueryContract
{
    /**
     * @param Company             $model
     * @param CompanyEntityMapper $mapper
     */
    public function __construct(
        Company $model,
        private readonly CompanyEntityMapper $mapper,
    ) {
        parent::__construct($model);
    }

    /**
     * Persists the entity, inserting it when it has no ID yet.
     *
     * @param CompanyEntity $company
     *
     * @return CompanyEntity
     *
     * @throws RuntimeException When the entity carries an ID that no longer exists.
     */
    public function save(CompanyEntity $company): CompanyEntity
    {
        $companyModel = $this->resolveRecord($company);

        $companyModel->fill($this->mapper->toPersistenceArray($company));
        $companyModel->save();

        return $this->mapper->toEntity($companyModel->toArray());
    }

    /**
     * Resolves the row to write: a fresh instance for an insert, otherwise the
     * existing row.
     *
     * @param CompanyEntity $company
     *
     * @return Company
     *
     * @throws RuntimeException When the entity carries an ID that no longer exists.
     */
    private function resolveRecord(CompanyEntity $company): Company
    {
        if ($company->id === null) {
            return new Company();
        }

        $companyModel = $this->findRecord($company->id);

        if (! $companyModel) {
            throw new RuntimeException(sprintf(
                'Company with ID %d no longer exists.',
                $company->id,
            ));
        }

        return $companyModel;
    }

    /**
     * Reads a company while holding a row lock, so a concurrent write cannot
     * slip between the read and the save.
     *
     * @param int $id
     *
     * @return CompanyEntity|null Null when no company carries the id.
     */
    public function findByIdWithLock(int $id): ?CompanyEntity
    {
        $companyModel = $this->findRecordForUpdate($id);

        if (! $companyModel instanceof Company) {
            return null;
        }

        return $this->mapper->toEntity($companyModel->toArray());
    }

    /**
     * Deletes a company.
     *
     * @param CompanyEntity $company
     *
     * @return void
     *
     * @throws RuntimeException When the entity has no ID, or the row could not be deleted.
     */
    public function delete(CompanyEntity $company): void
    {
        if ($company->id === null) {
            throw new RuntimeException('Cannot delete a company that has no ID.');
        }

        $deleted = $this->deleteRecord($company->id);

        if (! $deleted) {
            throw new RuntimeException(sprintf(
                'Company with ID %d could not be deleted.',
                $company->id,
            ));
        }
    }

    /**
     * Returns the companies matching the given filter criteria.
     *
     * @param CompanyFilter $filter
     *
     * @return PaginatedResult Items are CompanyEntity instances.
     */
    public function getFilteredCompanies(CompanyFilter $filter): PaginatedResult
    {
        $query = Company::query();

        if ($filter->search !== null) {
            // One term matched against the identifying columns. Wrapped in a
            // closure so the ORs cannot escape a conjunction added later.
            $term = '%' . $filter->search . '%';

            $query->where(function ($query) use ($term) {
                $query->where('name', 'like', $term)
                      ->orWhere('industry', 'like', $term);
            });
        }

        // The id tie-breaks rows sharing a created_at. Without it the order of
        // equal keys is undefined, so a paginated offset can repeat a row on
        // one page and skip another. Two rows created in the same second are
        // an ordinary case.
        $eloquentPaginator = $query
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($filter->perPage);

        $companies = $eloquentPaginator
            ->getCollection()
            ->map(fn(Company $item) => $this->mapper->toEntity($item->toArray()))
            ->all();

        return new PaginatedResult(
            items: $companies,
            total: $eloquentPaginator->total(),
            currentPage: $eloquentPaginator->currentPage(),
            perPage: $eloquentPaginator->perPage(),
            lastPage: $eloquentPaginator->lastPage(),
        );
    }

    /**
     * Returns one company on its own, without the related records the detail
     * read drags in.
     *
     * @param int $id
     *
     * @return CompanyEntity|null Null when no company carries the id.
     */
    public function findById(int $id): ?CompanyEntity
    {
        $companyModel = $this->findRecord($id);

        if (! $companyModel instanceof Company) {
            return null;
        }

        return $this->mapper->toEntity($companyModel->toArray());
    }

    /**
     * Returns one company with its contacts, deals, deal stages and deal
     * activities.
     *
     * @param string $id
     *
     * @return CompanyDetailData|null Null when no company carries the id.
     */
    public function findDetailById(string $id): ?CompanyDetailData
    {
        $companyModel = $this->findRecordWithRelations((int) $id, [
            'contacts',
            'deals.stage',
            'deals.activities.user',
        ]);

        if (! $companyModel instanceof Company) {
            return null;
        }

        // The graph is flattened here, so nothing above Infrastructure has to
        // know how Eloquent nests a relation.
        $deals = $companyModel->deals
            ->map(fn(Deal $deal) => [
                'id' => $deal->id,
                'title' => $deal->title,
                'amount' => $deal->amount,
                'currency' => $deal->currency,
                'expected_close_date' => $this->toDateString($deal->expected_close_date),
                'actual_close_date' => $this->toDateString($deal->actual_close_date),
                'stage' => $deal->stage?->only(['id', 'name', 'order_position', 'probability']),
                'activities' => $deal->activities
                    ->map(fn(Activity $activity) => [
                        'id' => $activity->id,
                        'type' => $activity->type,
                        'subject' => $activity->subject,
                        'description' => $activity->description,
                        'due_date' => $this->toDateTimeString($activity->due_date),
                        'completed_at' => $this->toDateTimeString($activity->completed_at),
                        'user_name' => $activity->user?->name,
                    ])
                    ->all(),
            ])
            ->all();

        $contacts = $companyModel->contacts
            ->map(fn(Contact $contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'job_title' => $contact->pivot?->job_title,
                'is_primary' => (bool) $contact->pivot?->is_primary,
            ])
            ->all();

        $activities = $companyModel->activities
            ->map(fn(Activity $activity) => [
                'id' => $activity->id,
                'type' => $activity->type,
                'subject' => $activity->subject,
                'description' => $activity->description,
                'due_date' => $this->toDateTimeString($activity->due_date),
                'completed_at' => $this->toDateTimeString($activity->completed_at),
                'user_name' => $activity->user?->name,
            ])
            ->all();

        return new CompanyDetailData(
            company: $this->mapper->toEntity($companyModel->toArray()),
            contacts: $contacts,
            deals: $deals,
            activities: $activities,
        );
    }

    /**
     * The casts already turn these into date objects, so only the string form
     * is left to take.
     *
     * @param mixed $value
     *
     * @return string|null
     */
    private function toDateString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value->toDateString();
    }

    /**
     * @param mixed $value
     *
     * @return string|null
     */
    private function toDateTimeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value->toDateTimeString();
    }
}
