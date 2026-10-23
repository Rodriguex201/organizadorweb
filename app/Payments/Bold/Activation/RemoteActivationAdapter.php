<?php
namespace App\Payments\Bold\Activation;

interface RemoteActivationAdapter
{
    public function consult(array $target): array;
    public function comparePrecondition(array $expected, array $observed): bool;
    public function writeAbsoluteObjective(array $target, array $expected, array $objective): void;
    public function rereadAndVerify(array $target, array $objective): array;
}
