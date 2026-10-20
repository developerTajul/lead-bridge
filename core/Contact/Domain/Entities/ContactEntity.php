<?php

declare(strict_types=1);

namespace Core\Contact\Domain\Entities;

use Core\Contact\Domain\ContactRules;
use Core\Contact\Domain\Exceptions\InvalidContactException;
use Core\Contact\Domain\ValueObjects\CompanyLink;

/**
 * Domain entity representing a contact.
 */
readonly class ContactEntity
{
    /**
     * @param int|null    $id
     * @param string      $name
     * @param string      $email
     * @param string|null $phone
     * @param string|null $createdAt
     * @param string|null $updatedAt
     */
    public function __construct(
        public ?int $id,
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}

    /**
     * Builds a new, unsaved contact.
     *
     * @param string      $name
     * @param string      $email
     * @param string|null $phone
     *
     * @return self
     *
     * @throws InvalidContactException When a field would break one of the contact's invariants.
     */
    public static function create(
        string $name,
        string $email,
        ?string $phone = null,
    ): self {
        self::assertNameIsUsable($name);
        self::assertEmailIsUsable($email);
        self::assertPhoneIsUsable($phone);

        return new self(
            id: null,
            name: $name,
            email: $email,
            phone: $phone,
        );
    }

    /**
     * Rebuilds a contact from persisted state. The stored fields are taken as
     * they are, so a row written before a rule existed still loads.
     *
     * @param int         $id
     * @param string      $name
     * @param string      $email
     * @param string|null $phone
     * @param string|null $createdAt
     * @param string|null $updatedAt
     *
     * @return self
     */
    public static function reconstitute(
        int $id,
        string $name,
        string $email,
        ?string $phone = null,
        ?string $createdAt = null,
        ?string $updatedAt = null,
    ): self {
        return new self(
            id: $id,
            name: $name,
            email: $email,
            phone: $phone,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    /**
     * Applies an update payload and returns the updated contact. A null field
     * with no clear flag keeps the stored value; a clear flag drops it. The
     * whole payload is validated before anything is applied, so a rejected
     * update leaves the contact untouched.
     *
     * @param string|null $name
     * @param string|null $email
     * @param string|null $phone
     * @param bool        $clearPhone Drop the stored phone number.
     *
     * @return self
     *
     * @throws InvalidContactException When a change would break an invariant.
     */
    public function applyChanges(
        ?string $name = null,
        ?string $email = null,
        ?string $phone = null,
        bool $clearPhone = false,
    ): self {
        // A contact is identified by its name and reached through its email, so
        // an update may replace either but never blank one. There is no clear
        // flag for them.
        $updatedName = $name ?? $this->name;
        $updatedEmail = $email ?? $this->email;
        // Decided as a guard, not a ternary: a clear flag with a value beside
        // it is a contradiction, and the flag is what the caller meant. Letting
        // the value win would ignore the flag and store the very number the
        // payload asked to drop.
        $updatedPhone = $phone ?? $this->phone;

        if ($clearPhone) {
            $updatedPhone = null;
        }

        // Guard the resulting state as a whole, so a payload rejected on one
        // field cannot be left half applied.
        self::assertNameIsUsable($updatedName);
        self::assertEmailIsUsable($updatedEmail);
        self::assertPhoneIsUsable($updatedPhone);

        return new self(
            id: $this->id,
            name: $updatedName,
            email: $updatedEmail,
            phone: $updatedPhone,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
        );
    }

    /**
     * Applies a change to the company the contact is attached to and returns
     * the updated contact. An attach and a detach are contradictions, so a
     * payload carrying both is refused rather than silently resolved one way.
     *
     * The company itself is not part of this entity's state; it lives on the
     * pivot table, so the Value Object comes back to the caller to hand to the
     * repository instead of being stored here.
     *
     * @param CompanyLink|null $companyLink The link to attach, or null when nothing is attached.
     * @param bool             $clearCompany Detach the contact from its company.
     *
     * @return self
     *
     * @throws InvalidContactException When the change would break an invariant.
     */
    public function applyCompanyChange(?CompanyLink $companyLink = null, bool $clearCompany = false): self
    {
        if ($clearCompany && $companyLink !== null) {
            throw InvalidContactException::contradictoryCompanyChange();
        }

        return new self(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            phone: $this->phone,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
        );
    }

    /**
     * Guards the name invariant: a contact without a name cannot be told apart
     * from any other.
     *
     * @param string $name
     *
     * @return void
     *
     * @throws InvalidContactException When the name is blank or too long.
     */
    private static function assertNameIsUsable(string $name): void
    {
        if (trim($name) === '') {
            throw InvalidContactException::emptyName();
        }

        if (mb_strlen($name) > ContactRules::MAX_NAME_LENGTH) {
            throw InvalidContactException::nameTooLong(mb_strlen($name));
        }
    }

    /**
     * Guards the email invariant: a contact is reached through its email, so a
     * supplied one has to be a usable address that fits the column.
     *
     * @param string $email
     *
     * @return void
     *
     * @throws InvalidContactException When the email is blank, malformed, or too long.
     */
    private static function assertEmailIsUsable(string $email): void
    {
        if (trim($email) === '') {
            throw InvalidContactException::emptyEmail();
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidContactException::invalidEmail($email);
        }

        if (mb_strlen($email) > ContactRules::MAX_EMAIL_LENGTH) {
            throw InvalidContactException::emailTooLong(mb_strlen($email));
        }
    }

    /**
     * Guards the phone invariant: a supplied phone number has to fit the column.
     *
     * @param string|null $phone
     *
     * @return void
     *
     * @throws InvalidContactException When the phone number is longer than the column accepts.
     */
    private static function assertPhoneIsUsable(?string $phone): void
    {
        if ($phone !== null && mb_strlen($phone) > ContactRules::MAX_PHONE_LENGTH) {
            throw InvalidContactException::phoneTooLong(mb_strlen($phone));
        }
    }
}
