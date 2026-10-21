<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Portal\Mock\PortalFixture;
use Illuminate\Http\Response;

final class MockPortalController extends Controller
{
    public function inicio(): Response { return $this->pantalla('inicio'); }
    public function verificacion(): Response { return $this->pantalla('verificacion'); }
    public function proforma(): Response { return $this->pantalla('proforma'); }
    public function resultado(string $estado): Response { return $this->pantalla('resultado', $estado); }
    public function activacion(string $estado): Response { return $this->pantalla('activacion', $estado); }

    private function pantalla(string $pantalla, string $estado = ''): Response
    {
        return response()->view('portal.layout', [
            'pantalla' => $pantalla,
            'estado' => $estado,
            'proforma' => PortalFixture::proforma(),
            'portalBase' => url('/portal'),
            'assetBase' => url('/'),
            'portalViewRoot' => resource_path('views/portal'),
        ])->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
