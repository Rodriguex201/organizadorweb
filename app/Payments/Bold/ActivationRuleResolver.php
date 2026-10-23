<?php
namespace App\Payments\Bold;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** Pure policy. No database, network, or concept-to-service mapping. */
final class ActivationRuleResolver
{
    public const VERSION = 'PAGO_APLICADO_UN_MES_V1';
    public function __construct(private array $config)
    {
        if (($config['remote_enabled'] ?? null) !== false
            || ($config['rules_version'] ?? '') !== self::VERSION
            || ($config['rules'] ?? null) !== []) {
            throw new RuntimeException('Configuración de activación no permitida.');
        }
    }

    public function resolve(array $lines, ?string $company, ?string $paymentDate, ?array $snapshot, ?string $error = null): array
    {
        $valid = is_string($company) && preg_match('/\A[A-Za-z0-9]{1,100}\z/', $company);
        $task = [
            'servicio'=>'ACTIVACION_NORMAL', 'estado'=>'REVISION',
            'empresa_codigo'=>$valid ? $company : null, 'regla_version'=>self::VERSION,
            'conceptos_origen'=>array_map(fn($line)=>['linea_id'=>(int)$line['id'], 'ref_codigo'=>$line['ref_codigo']], $lines),
            'error_codigo'=>$error,
        ];
        try {
            if ($error !== null) return $task;
            if (!$valid) throw new RuntimeException('CLIENTE_SIN_CODIGO');
            $payment = self::date($paymentDate);
            if ($payment === null) throw new RuntimeException('FECHA_PAGO_INVALIDA');
            if ($snapshot === null) throw new RuntimeException('LECTURA_REMOTA_NO_DISPONIBLE');
            $state = self::strictState($snapshot, $company);
            if ($state['inicio'] !== $state['inicio_global'] || $state['fin'] !== $state['fin_global']) throw new RuntimeException('FECHAS_NO_SINCRONIZADAS');
            if (self::date($state['inicio'])->modify('+2 days')->format('Y-m-d') !== $state['fin']) throw new RuntimeException('SEPARACION_NO_VALIDADA');
            $base = max($state['fin'], $payment->format('Y-m-d'));
            $date = self::date($base);
            $nextMonth = $date->modify('first day of next month');
            $end = $nextMonth->setDate((int)$nextMonth->format('Y'), (int)$nextMonth->format('m'), min((int)$date->format('d'), (int)$nextMonth->format('t')));
            if (self::date($end->format('Y-m-d'))===null) throw new RuntimeException('FECHA_OBJETIVO_INVALIDA');
            $task += [
                'conexion_logica'=>$state['conexion'], 'base_destino'=>$state['base'],
                'fecha_inicio_anterior'=>$state['inicio'], 'fecha_fin_anterior'=>$state['fin'],
                'fecha_inicio_global_anterior'=>$state['inicio_global'], 'fecha_fin_global_anterior'=>$state['fin_global'],
                'fecha_inicio_objetivo'=>$end->modify('-2 days')->format('Y-m-d'), 'fecha_fin_objetivo'=>$end->format('Y-m-d'),
            ];
            $task['estado']='PENDIENTE'; $task['error_codigo']=null;
            $task['objetivo_sha256']=self::fingerprint($task);
        } catch (RuntimeException $e) { $task['error_codigo']=$e->getMessage(); }
        catch (\Throwable) { $task['error_codigo']='RESPUESTA_REMOTA_INVALIDA'; }
        return $task;
    }

    /** Inventory must explicitly report a successful inspection of BOTH logical servers. */
    public static function strictState(array $snapshot, string $company): array
    {
        $matches=[];
        foreach (['mysql_213','mysql_167'] as $server) {
            $entry=$snapshot[$server]??null;
            if (!is_array($entry) || ($entry['consultado']??false)!==true || !is_array($entry['empresas']??null)) throw new RuntimeException('RESOLUCION_INCOMPLETA');
            foreach ($entry['empresas'] as $match) $matches[]=[$server,$match];
        }
        if (count($matches)!==1) throw new RuntimeException(count($matches)===0?'EMPRESA_NO_ENCONTRADA':'EMPRESA_AMBIGUA');
        [$server,$entry]=$matches[0];
        if (($entry['codigo']??null)!==$company || ($entry['base']??null)!==strtolower($company)) throw new RuntimeException('DESTINO_NO_COINCIDE');
        if (!is_array($entry['individual']??null)||count($entry['individual'])!==1) throw new RuntimeException('REGISTRO_INDIVIDUAL_NO_UNICO');
        if (!is_array($entry['global']??null)||count($entry['global'])!==1) throw new RuntimeException('REGISTRO_GLOBAL_NO_UNICO');
        $individual=$entry['individual'][0];$global=$entry['global'][0];
        $out=['conexion'=>$server,'base'=>$entry['base'],'inicio'=>$individual['seg_fecha']??null,'fin'=>$individual['seg_maxima']??null,
            'inicio_global'=>$global['emprfpago']??null,'fin_global'=>$global['emprfinit']??null];
        foreach (['inicio','fin','inicio_global','fin_global'] as $key) if (self::date($out[$key])===null) throw new RuntimeException('FECHAS_INVALIDAS');
        return $out;
    }

    public static function fingerprint(array $task): string
    {
        $values=[];
        foreach (['empresa_codigo','conexion_logica','base_destino','regla_version','fecha_inicio_anterior','fecha_fin_anterior',
            'fecha_inicio_global_anterior','fecha_fin_global_anterior','fecha_inicio_objetivo','fecha_fin_objetivo'] as $key) $values[$key]=$task[$key]??null;
        return hash('sha256',json_encode($values,JSON_THROW_ON_ERROR));
    }

    /** Compare all four dates. Never recompute an objective during a retry. */
    public static function compare(array $task, array $snapshot): string
    {
        if (!hash_equals((string)$task['objetivo_sha256'],self::fingerprint($task))) return 'OBJETIVO_ALTERADO';
        try { $state=self::strictState($snapshot,$task['empresa_codigo']); }
        catch (RuntimeException $e) { return $e->getMessage(); }
        catch (\Throwable) { return 'RESPUESTA_REMOTA_INVALIDA'; }
        if ($state['conexion']!==$task['conexion_logica'] || $state['base']!==$task['base_destino']) return 'DESTINO_NO_COINCIDE';
        $actual=[$state['inicio'],$state['fin'],$state['inicio_global'],$state['fin_global']];
        if ($actual===[$task['fecha_inicio_objetivo'],$task['fecha_fin_objetivo'],$task['fecha_inicio_objetivo'],$task['fecha_fin_objetivo']]) return 'OBJETIVO';
        if ($actual===[$task['fecha_inicio_anterior'],$task['fecha_fin_anterior'],$task['fecha_inicio_global_anterior'],$task['fecha_fin_global_anterior']]) return 'PRECONDICION';
        return 'ESTADO_REMOTO_DIVERGENTE';
    }

    private static function date(?string $value): ?DateTimeImmutable
    {
        if ($value===null) return null;
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('UTC'));
        return $date && $date->format('Y-m-d')===$value ? $date : null;
    }
}
