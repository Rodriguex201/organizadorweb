<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Support\DirectorioAudit;

Artisan::command('bold:conciliar {--id=} {--limit=5}', function () {
    try {
        $service=\App\Services\BoldReconciliationService::configured(config('bold',[]),app()->environment());
        $id=$this->option('id');
        if ($id!==null && (!ctype_digit((string)$id)||(int)$id<1)) throw new \RuntimeException();
        $result=$id!==null?$service->one((int)$id):$service->batch((int)$this->option('limit'));
        $this->line(json_encode($result,JSON_UNESCAPED_UNICODE));
    } catch (\Throwable) { $this->error('No se pudo conciliar. No se crearán links ni se aplicarán pagos.'); return 1; }
})->purpose('Conciliar links conocidos en sandbox sin aplicar pagos a proformas');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('debug:directorio-audit', function () {
    $this->line(json_encode(
        DirectorioAudit::collect(),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ));
})->purpose('Audita acceso al directorio UNC desde el contexto CLI');
