<?php

namespace App\Http\Controllers;

use App\Support\DirectorioAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectorioAuditController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless(in_array($request->ip(), ['127.0.0.1', '::1'], true), 403);

        return response()->json(DirectorioAudit::collect(), 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
