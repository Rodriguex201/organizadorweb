<?php

namespace App\Payments\Bold;

interface WebhookEventStore
{
    public function existingHash(string $eventId): ?string;
    public function attempt(string $link): ?array;
    /** Returns 200 on insertion/identical replay, 409 on conflicting replay. */
    public function save(array $event): int;
}
