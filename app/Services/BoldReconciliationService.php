<?php
namespace App\Services;

use App\Payments\Bold\{AttemptRepository,BoldHttpClient,CurlBoldHttpClient};
use Closure;
use RuntimeException;
use Throwable;

final class BoldReconciliationService
{
    public function __construct(private AttemptRepository $repo, private BoldHttpClient $http,
        #[\SensitiveParameter] private string $key, private Closure $clock) {}

    public static function configured(array $config, string $environment): self
    {
        if ($environment!=='local' || ($config['environment']??'')!=='sandbox'
            || ($config['database']['database']??'')!=='organizadorprueba'
            || ($config['enabled']??false)!==true || !is_string($config['api_key']??null)
            || trim($config['api_key'])==='' || preg_match('/[\r\n]/',$config['api_key'])) {
            throw new RuntimeException('Conciliación disponible solo en local/sandbox con configuración dedicada.');
        }
        return new self(AttemptRepository::open($config['database'],$environment,'sandbox'),new CurlBoldHttpClient(),$config['api_key'],fn()=>time());
    }

    public function batch(int $limit=5): array
    {
        $results=[];
        foreach($this->repo->reconciliationCandidates(max(1,min(20,$limit))) as $id) $results[]=$this->one((int)$id);
        return $results;
    }

    public function one(int $id): array
    {
        if ($id<1) throw new RuntimeException('Intento inválido.');
        return $this->repo->reconcile($id,function(array $row): array {
            if (!in_array($row['estado'],['ACTIVO','PROCESANDO','INDETERMINADO','LINK_CREADO'],true)
                || !is_string($row['payment_link']) || trim($row['payment_link'])==='') return [];
            $now=($this->clock)();
            $last=$row['ultima_consulta_at']===null?null:strtotime($row['ultima_consulta_at'].' UTC');
            $created=strtotime($row['created_at'].' UTC');
            $age=max(0,$now-($created===false?$now:$created));
            // Deterministic age-based backoff, durable across restarts, without schema changes.
            $delay=$age<300?60:($age<1800?300:($age<7200?900:3600));
            if ($row['estado']==='ACTIVO' && $row['estado_remoto']==='ACTIVE') $delay=max(1800,$delay);
            if ($last!==null && ($last===false || $now-$last<$delay)) return [];
            $fields=['estado'=>$row['estado'],'ultima_consulta_at'=>gmdate('Y-m-d H:i:s',$now),'http_status'=>null,'error_codigo'=>'GET_UNAVAILABLE'];
            try { $r=$this->http->lookup($this->key,$row['payment_link']); }
            catch(Throwable) { return $fields; }
            $status=$r['status']??null;
            $fields['http_status']=is_int($status)&&$status>=100&&$status<=599?$status:null;
            if (($r['uncertain']??true) || $status===null || $status>=500 || in_array($status,[408,429],true)) return $fields;
            // API Links does not document 404 as expired/deleted. Keep the blocker.
            if ($status===404) return array_replace($fields,['estado'=>'REVISION','error_codigo'=>'GET_HTTP_404']);
            try {
                if ($status<200 || $status>=300 || !is_string($r['body']??null) || strlen($r['body'])>65536
                    || strtolower(trim(explode(';',$r['content_type']??'')[0]))!=='application/json') throw new RuntimeException();
                // Preserve number lexemes, never round money through binary floats.
                json_decode($r['body'],false,32,JSON_THROW_ON_ERROR);
                $lossless=preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?/s',fn($m)=>$m[0][0]==='"'?$m[0]:'"'.$m[0].'"',$r['body']);
                $remote=json_decode($lossless,false,32,JSON_THROW_ON_ERROR);
                if (!is_object($remote)) throw new RuntimeException();
            } catch(Throwable) { return array_replace($fields,['estado'=>'REVISION','error_codigo'=>'GET_INVALID_RESPONSE']); }
            $reason=match(true) {
                ($remote->id??null)!==$row['payment_link']=>'LINK_MISMATCH',
                ($remote->reference??null)!==$row['referencia']=>'REFERENCE_MISMATCH',
                self::money($remote->total??null)===null || self::money($remote->total??null)!==self::money($row['importe'])=>'TOTAL_MISMATCH',
                ($remote->amount_type??null)!=='CLOSE'=>'AMOUNT_TYPE_MISMATCH',
                property_exists($remote,'currency') && $remote->currency!==$row['moneda']=>'CURRENCY_MISMATCH',
                ($remote->is_sandbox??null)!==true || $row['ambiente']!=='sandbox'=>'ENVIRONMENT_MISMATCH',
                !in_array($remote->status??null,['ACTIVE','PROCESSING','PAID','REJECTED','CANCELLED','EXPIRED'],true)=>'STATUS_MISMATCH',
                default=>null,
            };
            if ($reason!==null) return array_replace($fields,['estado'=>'REVISION','error_codigo'=>$reason]);
            $fields+=['estado_remoto'=>$remote->status,'ambiente_verificado'=>1];
            $fields['estado']=match($remote->status){'ACTIVE'=>'ACTIVO','PROCESSING','REJECTED'=>'PROCESANDO','PAID'=>'PAGADO','CANCELLED'=>'CANCELADO','EXPIRED'=>'EXPIRADO'};
            $fields['error_codigo']=$remote->status==='REJECTED'?'REMOTE_REJECTED_LINK_RECHECK':null;
            if ($remote->status==='PAID') {
                $tx=$remote->transaction_id??null;
                if (!is_string($tx)||!preg_match('/\A[\x21-\x7e]{1,128}\z/',$tx)) {
                    $fields['estado']='REVISION';$fields['error_codigo']='PAID_TRANSACTION_MISSING';
                } elseif (!empty($row['transaction_id']) && $row['transaction_id']!==$tx) {
                    $fields['estado']='REVISION';$fields['error_codigo']='TRANSACTION_MISMATCH';
                } else $fields['transaction_id']=$tx;
            }
            // Currency is the frozen local currency, NOT asserted as verified by this GET.
            return $fields;
        });
    }

    private static function money(mixed $value): ?string
    {
        if (!is_string($value)||!preg_match('/\A(0|[1-9]\d{0,15})(?:\.(\d{1,2}))?\z/',$value,$m))return null;
        if ($m[1]==='0' && (int)($m[2]??0)===0)return null;
        return $m[1].'.'.str_pad($m[2]??'',2,'0');
    }
}
