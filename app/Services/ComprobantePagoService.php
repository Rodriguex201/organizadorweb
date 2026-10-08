<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ComprobantePagoService
{
    public function __construct(private readonly ProformasService $proformas) {}

    private static function segmento(?string $texto): string
    {
        $texto = Str::ascii(trim($texto ?? ''));
        $texto = preg_replace('/[^A-Za-z0-9_-]+/', '_', $texto);
        return trim(substr($texto, 0, 100), '_-');
    }

    public static function nombre(int $proformaId, int $mes, int $anio, ?object $cliente, string $extension): string
    {
        $codigo = self::segmento($cliente->codigo ?? null);
        $empresa = self::segmento($cliente->empresa ?? null);
        $nombre = self::segmento($cliente->nombre ?? null);
        $base = $cliente === null ? 'PROFORMA_'.$proformaId
            : ($codigo === '' ? 'CLIENTE_'.$cliente->idclientes_potenciales
                : $codigo.($empresa !== '' ? '_'.$empresa : ($nombre !== '' ? '_'.$nombre : '')));
        return sprintf('%s_%02d_%04d.%s', $base, $mes, $anio, strtolower($extension));
    }

    public static function nombreVisible(string $ruta): string
    {
        return basename(str_replace('\\', '/', $ruta));
    }

    public function registrar(int $id, string $metodo, ?UploadedFile $archivo): array
    {
        $disk = Storage::disk('local');
        $nuevo = null;
        $anterior = null;
        $respaldo = null;
        try {
            $resultado = DB::transaction(function () use ($id, $metodo, $archivo, $disk, &$nuevo, &$anterior, &$respaldo): array {
                $contexto = $this->proformas->contextoComprobante($id);
                if ($contexto === null) return ['ok' => false, 'message' => 'La proforma no existe.'];
                $anterior = $contexto['proforma']->comprobante_pago;
                if ($archivo !== null && $metodo !== 'EFECTIVO') {
                    $proforma = $contexto['proforma'];
                    $nombre = self::nombre($id, (int) $proforma->mes, (int) $proforma->anio,
                        $contexto['cliente'], $archivo->getClientOriginalExtension());
                    $directorio = 'proformas/comprobantes/'.$id;
                    $destino = $directorio.'/'.$nombre;
                    // Conserva los bytes anteriores si el reemplazo usa exactamente la misma ruta.
                    if ($disk->exists($destino)) {
                        $copia = $destino.'.respaldo-'.Str::random(24);
                        if (!$disk->copy($destino, $copia)) throw new \RuntimeException('No fue posible respaldar el comprobante anterior.');
                        $respaldo = $copia;
                    }
                    $nuevo = $destino;
                    $ruta = $disk->putFileAs($directorio, $archivo, $nombre);
                    if ($ruta === false) throw new \RuntimeException('No fue posible almacenar el comprobante de pago.');
                    $nuevo = $ruta;
                }
                if ((int) $contexto['proforma']->estado === ProformasService::ESTADO_PAGADA && $nuevo !== null) {
                    // Reemplazar el comprobante no cambia la fecha ni vuelve a ejecutar la transición.
                    DB::table('sg_proform')->where('id', $id)->update(['comprobante_pago' => $nuevo, 'fpago' => $metodo]);
                    return ['ok' => true, 'message' => 'Comprobante de pago reemplazado.', 'from' => ProformasService::ESTADO_PAGADA, 'to' => ProformasService::ESTADO_PAGADA];
                }
                return $this->proformas->updateEstado($id, ProformasService::ESTADO_PAGADA, $metodo, $nuevo);
            });
        } catch (\Throwable $error) {
            $this->descartar($nuevo, $respaldo);
            throw $error;
        }
        if (!$resultado['ok']) {
            $this->descartar($nuevo, $respaldo);
            return $resultado;
        }
        // Solo después del commit: un fallo de limpieza nunca borra el archivo ya persistido.
        if (!$this->limpiar($respaldo)) {
            $resultado['message'] .= ' No se pudo eliminar el respaldo temporal; requiere revisión.';
        }
        if ($anterior && $anterior !== $nuevo && !$this->limpiar($anterior)) {
            $resultado['message'] .= ' No se pudo eliminar el archivo anterior; requiere revisión.';
        }
        $resultado['comprobante_pago'] = $nuevo;
        return $resultado;
    }

    private function descartar(?string $nuevo, ?string $respaldo): void
    {
        $this->limpiar($nuevo);
        if ($respaldo === null) return;
        // Si falla la restauración, conserva el respaldo para no perder el original.
        if (!Storage::disk('local')->copy($respaldo, $nuevo)) {
            throw new \RuntimeException('No fue posible restaurar el comprobante anterior; se conserva su respaldo.');
        }
        $this->limpiar($respaldo);
    }

    private function limpiar(?string $ruta): bool
    {
        if (!$ruta) return true;
        try {
            if (!Storage::disk('local')->delete($ruta)) throw new \RuntimeException('No se pudo limpiar el comprobante anterior o descartado.');
            return true;
        } catch (\Throwable $error) {
            report($error);
            return false;
        }
    }
}
