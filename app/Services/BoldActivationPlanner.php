<?php
namespace App\Services;

use App\Payments\Bold\{ActivationTaskRepository,ActivationRuleResolver};
use App\Payments\Bold\Activation\{RemoteActivationAdapter,DisabledNormalActivationAdapter};

final class BoldActivationPlanner
{
    public function __construct(private ActivationTaskRepository $repository, private ActivationRuleResolver $rules,
        private RemoteActivationAdapter $adapter = new DisabledNormalActivationAdapter()) {}
    public static function configured(array $bold, array $activations, string $environment): self
    {
        // Validate disabled configuration before opening even the local database.
        $rules=new ActivationRuleResolver($activations);
        return new self(ActivationTaskRepository::open($bold['database']??[],$environment,$bold['environment']??''),$rules);
    }
    public function one(int $id):array{return $this->repository->plan($id,$this->rules,$this->adapter);}
    public function batch(int $limit=10):array{
        $out=[];foreach($this->repository->candidates(max(1,min(50,$limit)))as$id)$out[]=$this->one((int)$id);return $out;
    }
}
