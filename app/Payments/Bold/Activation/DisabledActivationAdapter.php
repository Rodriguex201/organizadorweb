<?php
namespace App\Payments\Bold\Activation;

use RuntimeException;

abstract class DisabledActivationAdapter implements RemoteActivationAdapter
{
    final public function consult(array $target): array { throw new RuntimeException('ACTIVACION_REMOTA_DESHABILITADA'); }
    final public function comparePrecondition(array $expected, array $observed): bool { throw new RuntimeException('ACTIVACION_REMOTA_DESHABILITADA'); }
    final public function writeAbsoluteObjective(array $target, array $expected, array $objective): void { throw new RuntimeException('ACTIVACION_REMOTA_DESHABILITADA'); }
    final public function rereadAndVerify(array $target, array $objective): array { throw new RuntimeException('ACTIVACION_REMOTA_DESHABILITADA'); }
}
