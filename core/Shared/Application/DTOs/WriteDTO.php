<?php

declare(strict_types=1);

namespace Core\Shared\Application\DTOs;

/**
 * Base class for all Write DTOs.
 * This ensures that any DTO intended for writing data 
 * can be handled generically by services like QueueManager.
 */
abstract readonly class WriteDTO 
{
    // এখানে আপনি চাইলে কমন মেথড বা প্রোপার্টি রাখতে পারেন 
    // যা সব রাইট ডিটিও-তে প্রয়োজন।
}