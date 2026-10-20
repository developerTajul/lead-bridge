<?php

declare(strict_types=1);

namespace Core\Contact\Infrastructure\Repositories;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use Core\Contact\Domain\Contracts\ContactQueryContract;
use Core\Contact\Domain\Contracts\ContactRepositoryContract;
use Core\Contact\Domain\DataObjects\ContactDetailData;
use Core\Contact\Domain\DataObjects\StoredContactData;
use Core\Contact\Domain\Entities\ContactEntity;
use Core\Contact\Domain\ValueObjects\CompanyLink;
use Core\Contact\Domain\ValueObjects\ContactFilter;
use Core\Contact\Infrastructure\Mappers\ContactEntityMapper;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Core\Shared\Infrastructure\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Eloquent-backed persistence for contacts. The only place in the module
 * where Laravel/Eloquent is used; results are mapped to Domain Entities first.
 *
 * @implements ContactRepositoryContract
 * @implements ContactQueryContract
 */
final class ContactEloquentRepository extends BaseRepository implements ContactRepositoryContract, ContactQueryContract
{
    /**
     * @param Contact             $model
     * @param ContactEntityMapper $mapper
     */
    public function __construct(
        Contact $model,
        private readonly ContactEntityMapper $mapper,
    ) {
        parent::__construct($model);
    }

    /**
     * Persists the contact and, when a company link is given, attaches it to
     * that company on the pivot table. Both writes run in one transaction, so
     * a failure part-way leaves neither behind.
     *
     * @param ContactEntity   $contact
     * @param CompanyLink|null $link Null when the contact is created without a company.
     *
     * @return StoredContactData The stored contact with its company links.
     *
     * @throws RuntimeException When the contact row could not be written.
     */
    public function save(ContactEntity $contact, ?CompanyLink $link = null): StoredContactData
    {
        $contactModel = DB::transaction(function () use ($contact, $link): Contact {
            $contactModel = $this->createRecord($this->mapper->toPersistenceArray($contact));

            if ($link !== null) {
                $contactModel->companies()->attach($link->companyId, [
                    'job_title' => $link->jobTitle,
                    'is_primary' => $link->isPrimary,
                ]);
            }

            return $contactModel;
        });

        if (! $contactModel instanceof Contact) {
            throw new RuntimeException('The contact row could not be written.');
        }

        return new StoredContactData(
            contact: $this->mapper->toEntity($contactModel->toArray()),
            companies: $this->toCompanyRows($contactModel),
        );
    }

    /**
     * Reads a contact while holding a row lock, so a concurrent write cannot
     * slip between the read and the save.
     *
     * @param int $id
     *
     * @return ContactEntity|null Null when no contact carries the id.
     */
    public function findByIdWithLock(int $id): ?ContactEntity
    {
        $contactModel = $this->findRecordForUpdate($id);

        if (! $contactModel instanceof Contact) {
            return null;
        }

        return $this->mapper->toEntity($contactModel->toArray());
    }

    /**
     * Persists changes to an existing contact and applies the requested company
     * change on the pivot table, returning the contact with its company links.
     *
     * The row, its links and the read that follows all run in one transaction,
     * so the returned state is the committed state.
     *
     * @param ContactEntity    $contact
     * @param CompanyLink|null $link  The link to attach, or null when none is attached.
     * @param bool             $clearCompany Detach the contact from its company.
     *
     * @return StoredContactData
     *
     * @throws RuntimeException When the entity has no ID, or the row no longer exists.
     */
    public function update(
        ContactEntity $contact,
        ?CompanyLink $link = null,
        bool $clearCompany = false,
    ): StoredContactData {
        if ($contact->id === null) {
            throw new RuntimeException('Cannot update a contact that has no ID.');
        }

        return DB::transaction(function () use ($contact, $link, $clearCompany): StoredContactData {
            $record = $this->findRecord($contact->id);

            if (! $record instanceof Contact) {
                throw new RuntimeException(sprintf(
                    'Contact with ID %d no longer exists.',
                    $contact->id,
                ));
            }

            $record->fill($this->mapper->toPersistenceArray($contact));
            $record->save();

            if ($clearCompany) {
                $record->companies()->detach();
            }

            if ($link !== null) {
                // The pivot is unique on (company_id, contact_id), so a second
                // attach would collide. syncWithoutDetaching() writes the same
                // row and refreshes the role on it when the link is already
                // there, which is the shape this use case means.
                $record->companies()->syncWithoutDetaching([
                    $link->companyId => [
                        'job_title' => $link->jobTitle,
                        'is_primary' => $link->isPrimary,
                    ],
                ]);
            }

            // Read back inside the transaction, so a caller never sees the
            // state an earlier read would have given it.
            return new StoredContactData(
                contact: $this->mapper->toEntity($record->refresh()->toArray()),
                companies: $this->toCompanyRows($record),
            );
        });
    }

    /**
     * Deletes a contact.
     *
     * @param ContactEntity $contact
     *
     * @return void
     *
     * @throws RuntimeException When the entity has no ID, or the row could not be deleted.
     */
    public function delete(ContactEntity $contact): void
    {
        if ($contact->id === null) {
            throw new RuntimeException('Cannot delete a contact that has no ID.');
        }

        $deleted = $this->deleteRecord($contact->id);

        if (! $deleted) {
            throw new RuntimeException(sprintf(
                'Contact with ID %d could not be deleted.',
                $contact->id,
            ));
        }
    }

    /**
     * Returns the contacts matching the given filter criteria.
     *
     * @param ContactFilter $filter
     *
     * @return PaginatedResult Items are StoredContactData instances.
     */
    public function getFilteredContacts(ContactFilter $filter): PaginatedResult
    {
        $query = Contact::query();

        if ($filter->search !== null) {
            // One term matched against the identifying columns. Wrapped in a
            // closure so the ORs cannot escape a conjunction added later.
            $term = '%' . $filter->search . '%';

            $query->where(function ($query) use ($term) {
                $query->where('name', 'like', $term)
                      ->orWhere('email', 'like', $term)
                      ->orWhere('phone', 'like', $term);
            });
        }

        // The id tie-breaks rows sharing a created_at. Without it the order of
        // equal keys is undefined, so a paginated offset can repeat a row on
        // one page and skip another. Two rows created in the same second are
        // an ordinary case.
        $eloquentPaginator = $query
            ->with('companies')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($filter->perPage);

        $contacts = $eloquentPaginator
            ->getCollection()
            ->map(fn(Contact $item) => new StoredContactData(
                contact: $this->mapper->toEntity($item->toArray()),
                companies: $this->toCompanyRows($item),
            ))
            ->all();

        return new PaginatedResult(
            items: $contacts,
            total: $eloquentPaginator->total(),
            currentPage: $eloquentPaginator->currentPage(),
            perPage: $eloquentPaginator->perPage(),
            lastPage: $eloquentPaginator->lastPage(),
        );
    }

    /**
     * Returns one contact with its companies and activities.
     *
     * @param int $id
     *
     * @return ContactDetailData|null Null when no contact carries the id.
     */
    public function findDetailById(int $id): ?ContactDetailData
    {
        $contactModel = $this->findRecordWithRelations($id, ['companies', 'activities.user']);

        if (! $contactModel instanceof Contact) {
            return null;
        }

        // The graph is flattened here, so nothing above Infrastructure has to
        // know how Eloquent nests a relation.
        $activities = $contactModel->activities
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

        return new ContactDetailData(
            contact: $this->mapper->toEntity($contactModel->toArray()),
            companies: $this->toCompanyRows($contactModel),
            activities: $activities,
        );
    }

    /**
     * Flattens the attached companies to plain rows, so nothing above
     * Infrastructure has to know how the pivot is read.
     *
     * @param Contact $contactModel
     *
     * @return array<int, array<string, mixed>>
     */
    private function toCompanyRows(Contact $contactModel): array
    {
        return $contactModel->companies()
            ->get()
            ->map(fn(Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'phone' => $company->phone,
                'website' => $company->website,
                'industry' => $company->industry,
                'job_title' => $company->pivot?->job_title,
                'is_primary' => (bool) $company->pivot?->is_primary,
            ])
            ->all();
    }

    /**
     * The casts already turn these into date objects, so only the string form
     * is left to take.
     *
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
