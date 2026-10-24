<?php
namespace App\Payments\Bold\Activation;

interface NormalActivationWriteConnection
{
    public function begin(): void;
    /** Lock both records, check transactional engines, return all matching rows. */
    public function lockedSnapshot(): array;
    public function updateIndividual(array $previous, array $objective): int;
    public function updateGlobal(array $previous, array $objective): int;
    public function commit(): void;
    public function rollback(): void;
}
