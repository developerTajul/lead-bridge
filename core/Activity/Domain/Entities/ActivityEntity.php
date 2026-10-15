<?php

declare(strict_types=1);

namespace Core\Activity\Domain\Entities;

use Core\Activity\Domain\Enums\ActivityType;
use DateTimeImmutable;

/**
 * Pure domain entity representing an activity.
 */
class ActivityEntity
{
    // রিলেশন এবং টাইমস্ট্যাম্পের জন্য নতুন প্রোপার্টি
    private ?array $user = null;
    private ?array $trackable = null;
    private ?DateTimeImmutable $createdAt = null;
    private ?DateTimeImmutable $updatedAt = null;

    private function __construct(
        private readonly ?int $id,
        private readonly string $trackableType,
        private readonly int $trackableId,
        private ActivityType $type,
        private string $subject,
        private ?int $userId = null,
        private ?string $description = null,
        private ?DateTimeImmutable $dueDate = null,
        private ?DateTimeImmutable $completedAt = null
    ) {}


    public static function createNew(        
        string $trackableType,
        int $trackableId,
        ActivityType $type,
        string $subject,
        ?int $userId = null,
        ?string $description = null,
        ?DateTimeImmutable $dueDate = null
    ): self
    {
        return new self(
            id: null,
            trackableType: $trackableType,
            trackableId: $trackableId,
            type: $type,
            subject: $subject,
            userId: $userId,
            description: $description,
            dueDate: $dueDate
        );
    }


    public static function reconstitute(
        int $id,
        string $trackableType,
        int $trackableId,
        ActivityType $type,
        string $subject,
        ?int $userId = null,
        ?string $description = null,
        ?DateTimeImmutable $dueDate = null,
        ?DateTimeImmutable $completedAt = null
    ):self
    {
        return new self(
            id: $id,
            trackableType: $trackableType,
            trackableId: $trackableId,
            type: $type,
            subject: $subject,
            userId: $userId,
            description: $description,
            dueDate: $dueDate,
            completedAt: $completedAt
        );
    }



    public function getId(): ?int 
    { 
        return $this->id; 
    }

    public function getTrackableType(): string 
    { 
        return $this->trackableType; 
    }

    public function getTrackableId(): int 
    { 
        return $this->trackableId; 
    }

    public function getType(): ActivityType 
    { 
        return $this->type; 
    }

    public function getSubject(): string 
    { 
        return $this->subject; 
    }

    public function getUserId(): ?int 
    { 
        return $this->userId; 
    }

    public function getDescription(): ?string 
    { 
        return $this->description; 
    }

    public function getDueDate(): ?DateTimeImmutable 
    { 
        return $this->dueDate; 
    }

    public function getCompletedAt(): ?DateTimeImmutable 
    { 
        return $this->completedAt; 
    }

    // --- নতুন যুক্ত করা Getter মেথডসমূহ ---

    public function getUser(): ?array 
    { 
        return $this->user; 
    }

    public function getTrackable(): ?array 
    { 
        return $this->trackable; 
    }

    public function getCreatedAt(): ?DateTimeImmutable 
    { 
        return $this->createdAt; 
    }

    public function getUpdatedAt(): ?DateTimeImmutable 
    { 
        return $this->updatedAt; 
    }

    // --- নতুন যুক্ত করা Setter মেথডসমূহ ---

    public function setUser(?array $user): void 
    {
        $this->user = $user;
    }

    public function setTrackable(?array $trackable): void 
    {
        $this->trackable = $trackable;
    }

    public function setCreatedAt(?DateTimeImmutable $createdAt): void 
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): void 
    {
        $this->updatedAt = $updatedAt;
    }
}