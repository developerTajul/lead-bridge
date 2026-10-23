<?php

declare(strict_types=1);

namespace Core\User\Domain\Entities;

use Core\Auth\Domain\Enums\AuthRole;
use Core\Auth\Domain\Enums\AuthStatus;
use Core\User\Domain\Exceptions\InvalidUserException;
use Core\User\Domain\Exceptions\UserAlreadyActiveException;
use Core\User\Domain\Exceptions\UserAlreadyApprovedException;
use Core\User\Domain\Exceptions\UserCannotAuthenticateException;
use Core\User\Domain\UserRules;

/**
 * User account with its own identity and status rules.
 *
 * The password this entity holds is always a hash: the Application layer hashes
 * a plain password through PasswordHasherContract before it gets here, so the
 * entity never sees one and never stores one.
 */
readonly class UserEntity
{
    /**
     * A hash that is not readable as a real one still fills the column, so the
     * table's NOT NULL constraint holds. Nothing can be verified against it.
     */
    private const UNUSABLE_PASSWORD = '';

    /**
     * @param int|null    $id
     * @param string      $name
     * @param string      $email
     * @param string|null $emailVerifiedAt
     * @param string      $password      A hash, never a plain-text password.
     * @param string|null $image
     * @param AuthRole    $role
     * @param AuthStatus  $status
     * @param string|null $rememberToken
     * @param string|null $createdAt
     * @param string|null $updatedAt
     * @param string|null $deletedAt
     * @param array<int, object> $domainEvents Events this instance raised.
     *
     * @throws InvalidUserException When a stored value would break an invariant.
     */
    public function __construct(
        public ?int $id,
        public string $name,
        public string $email,
        public ?string $emailVerifiedAt,
        public string $password,
        public ?string $image,
        public AuthRole $role,
        public AuthStatus $status,
        public ?string $rememberToken = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?string $deletedAt = null,
        private array $domainEvents = [],
    ) {
        self::assertNameIsUsable($name);
        self::assertEmailIsUsable($email);
        self::assertPasswordIsHashed($password);
    }

    /**
     * Builds a new, unsaved account.
     *
     * A self-registered account starts as a pending member until an
     * administrator approves it.
     *
     * @param string      $name
     * @param string      $email
     * @param string      $password A hash; hash a plain password before calling.
     * @param string|null $image
     * @param AuthRole    $role
     * @param AuthStatus  $status
     *
     * @return self
     *
     * @throws InvalidUserException When a supplied field is unusable.
     */
    public static function register(
        string $name,
        string $email,
        string $password,
        ?string $image = null,
        AuthRole $role = AuthRole::MEMBER,
        AuthStatus $status = AuthStatus::PENDING,
    ): self {
        return new self(
            id: null,
            name: $name,
            email: $email,
            emailVerifiedAt: null,
            password: $password,
            image: $image,
            role: $role,
            status: $status,
        );
    }

    /**
     * Rebuilds an account from persisted state. The stored values are checked by
     * the constructor; a row that breaks an invariant is the caller's to repair,
     * so the failure is raised rather than a quietly invalid entity returned.
     *
     * @param int|null    $id
     * @param string      $name
     * @param string      $email
     * @param string|null $emailVerifiedAt
     * @param string      $password
     * @param string|null $image
     * @param AuthRole    $role
     * @param AuthStatus  $status
     * @param string|null $rememberToken
     * @param string|null $createdAt
     * @param string|null $updatedAt
     * @param string|null $deletedAt
     *
     * @return self
     *
     * @throws InvalidUserException When a stored value would break an invariant.
     */
    public static function reconstitute(
        ?int $id,
        string $name,
        string $email,
        ?string $emailVerifiedAt,
        string $password,
        ?string $image,
        AuthRole $role,
        AuthStatus $status,
        ?string $rememberToken = null,
        ?string $createdAt = null,
        ?string $updatedAt = null,
        ?string $deletedAt = null,
    ): self {
        return new self(
            id: $id,
            name: $name,
            email: $email,
            emailVerifiedAt: $emailVerifiedAt,
            password: $password,
            image: $image,
            role: $role,
            status: $status,
            rememberToken: $rememberToken,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
    }

    /**
     * Whether the account holds the administrator role.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->role === AuthRole::ADMIN;
    }

    /**
     * Whether the account is allowed to sign in.
     *
     * @return bool
     */
    public function canSignIn(): bool
    {
        return $this->status === AuthStatus::APPROVED;
    }

    /**
     * Whether the account is still waiting for an administrator to approve it.
     *
     * @return bool
     */
    public function isPendingApproval(): bool
    {
        return $this->status === AuthStatus::PENDING;
    }

    /**
     * Whether the account has been suspended.
     *
     * @return bool
     */
    public function isSuspended(): bool
    {
        return $this->status === AuthStatus::SUSPENDED;
    }

    /**
     * Whether the stored password matches the given plain-text one.
     *
     * @param string   $plainPassword
     * @param callable $verify Takes the plain password and this entity's hash.
     *
     * @return bool
     */
    public function passwordMatches(string $plainPassword, callable $verify): bool
    {
        return (bool) $verify($plainPassword, $this->password);
    }

    /**
     * Guards a sign-in attempt. The account is the only thing that knows
     * whether its status allows one.
     *
     * @return self The same instance, once the attempt is allowed.
     *
     * @throws UserCannotAuthenticateException When the status refuses the sign-in.
     */
    public function assertCanSignIn(): self
    {
        if (! $this->canSignIn()) {
            throw UserCannotAuthenticateException::fromStatus($this->status);
        }

        return $this;
    }

    /**
     * Approves the account, letting it sign in. Returns a new instance.
     *
     * @return self
     *
     * @throws UserAlreadyApprovedException When the account is already approved.
     */
    public function approve(): self
    {
        if ($this->status === AuthStatus::APPROVED) {
            throw UserAlreadyApprovedException::withId((int) $this->id);
        }

        return $this->withStatus(AuthStatus::APPROVED);
    }

    /**
     * Suspends the account, refusing its sign-ins. Returns a new instance.
     *
     * @return self
     *
     * @throws UserAlreadyActiveException When there is nothing to suspend.
     */
    public function suspend(): self
    {
        if ($this->status !== AuthStatus::APPROVED) {
            throw UserAlreadyActiveException::withId((int) $this->id);
        }

        return $this->withStatus(AuthStatus::SUSPENDED);
    }

    /**
     * Moves the account directly to a status an administrator picked, refusing
     * a move the account is not allowed to make. Returns a new instance.
     *
     * @param AuthStatus $status
     *
     * @return self
     *
     * @throws UserAlreadyApprovedException When approving an approved account.
     * @throws UserAlreadyActiveException   When suspending an account that is not approved.
     */
    public function changeStatusTo(AuthStatus $status): self
    {
        if ($status === AuthStatus::APPROVED) {
            return $this->approve();
        }

        if ($status === AuthStatus::SUSPENDED) {
            return $this->suspend();
        }

        return $this->withStatus($status);
    }

    /**
     * Applies an edit to the account's own fields and returns a new instance.
     * A null value keeps the stored one, so an edit that names no image keeps
     * the current image.
     *
     * @param string      $name
     * @param string      $email
     * @param string      $password      A hash, or the stored one when unchanged.
     * @param string|null $image
     * @param AuthRole    $role
     *
     * @return self
     *
     * @throws InvalidUserException When a supplied field is unusable.
     */
    public function applyChanges(
        string $name,
        string $email,
        string $password,
        ?string $image,
        AuthRole $role,
    ): self {
        return new self(
            id: $this->id,
            name: $name,
            email: $email,
            emailVerifiedAt: $this->emailVerifiedAt,
            password: $password,
            image: $image,
            role: $role,
            status: $this->status,
            rememberToken: $this->rememberToken,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            deletedAt: $this->deletedAt,
            domainEvents: $this->domainEvents,
        );
    }

    /**
     * Returns the events this instance raised. It only returns them; a readonly
     * entity cannot clear its own list.
     *
     * @return array<int, object>
     */
    public function releaseEvents(): array
    {
        return $this->domainEvents;
    }

    /**
     * Returns a copy of this account carrying the given status.
     *
     * @param AuthStatus $status
     *
     * @return self
     */
    private function withStatus(AuthStatus $status): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            emailVerifiedAt: $this->emailVerifiedAt,
            password: $this->password,
            image: $this->image,
            role: $this->role,
            status: $status,
            rememberToken: $this->rememberToken,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            deletedAt: $this->deletedAt,
            domainEvents: $this->domainEvents,
        );
    }

    /**
     * Guards the name invariant: an account without a name cannot be told apart
     * from any other.
     *
     * @param string $name
     *
     * @return void
     *
     * @throws InvalidUserException When the name is blank or too long.
     */
    private static function assertNameIsUsable(string $name): void
    {
        if (trim($name) === '') {
            throw InvalidUserException::emptyName();
        }

        if (mb_strlen($name) > UserRules::MAX_NAME_LENGTH) {
            throw InvalidUserException::nameTooLong(mb_strlen($name));
        }
    }

    /**
     * Guards the email invariant: an account is signed into and addressed by its
     * email, so a supplied one has to be a usable address that fits the column.
     *
     * @param string $email
     *
     * @return void
     *
     * @throws InvalidUserException When the email is blank, malformed, or too long.
     */
    private static function assertEmailIsUsable(string $email): void
    {
        if (trim($email) === '') {
            throw InvalidUserException::emptyEmail();
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidUserException::invalidEmail($email);
        }

        if (mb_strlen($email) > UserRules::MAX_EMAIL_LENGTH) {
            throw InvalidUserException::emailTooLong(mb_strlen($email));
        }
    }

    /**
     * Guards the credential invariant. Every path that reaches this entity
     * through Application has hashed its password already, so a value that is
     * not a hash is a plain-text password that would be written to the column.
     *
     * php's password_* functions are language built-ins, not framework calls, so
     * the check stays inside the Domain. A database row whose hash is not
     * readable as one passes: the entity accepts it and nothing can verify
     * against it, rather than refusing to load the row at all.
     *
     * @param string $password
     *
     * @return void
     *
     * @throws InvalidUserException When the value is a plain-text password.
     */
    private static function assertPasswordIsHashed(string $password): void
    {
        if ($password === self::UNUSABLE_PASSWORD) {
            return;
        }

        if (password_get_info($password)['algo'] === null) {
            throw InvalidUserException::passwordNotHashed();
        }
    }
}
