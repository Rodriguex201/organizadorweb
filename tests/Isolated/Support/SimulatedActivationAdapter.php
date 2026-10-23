<?php
use App\Payments\Bold\Activation\RemoteActivationAdapter;
/** In-memory only. No PDO, HTTP, files or production service calls. */
final class SimulatedActivationAdapter implements RemoteActivationAdapter
{
    public ?array $snapshot=null;
    public bool $unavailable=false;
    public int $reads=0;
    public int $writes=0;
    public static function inventory(string $company, string $start='2026-01-29', string $end='2026-01-31'):array
    {
        return ['mysql_213'=>['consultado'=>true,'empresas'=>[['codigo'=>$company,'base'=>strtolower($company),
            'individual'=>[['seg_fecha'=>$start,'seg_maxima'=>$end]],'global'=>[['emprfpago'=>$start,'emprfinit'=>$end]]]]],
            'mysql_167'=>['consultado'=>true,'empresas'=>[]]];
    }
    public function consult(array $target):array
    {
        $this->reads++;if($this->unavailable)throw new RuntimeException('SIMULATED_UNAVAILABLE');
        return $this->snapshot??self::inventory($target['empresa_codigo']);
    }
    public function comparePrecondition(array $expected,array $observed):bool{return $expected===$observed;}
    public function writeAbsoluteObjective(array $target,array $expected,array $objective):void
    {
        if($this->consult($target)!==$expected)throw new RuntimeException('SIMULATED_PRECONDITION_CHANGED');
        $this->writes++;
        $this->snapshot=self::inventory($target['empresa_codigo'],$objective['fecha_inicio_objetivo'],$objective['fecha_fin_objetivo']);
    }
    public function rereadAndVerify(array $target,array $objective):array{return $this->consult($target);}
}
