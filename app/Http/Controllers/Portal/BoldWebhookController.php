<?php

namespace App\Http\Controllers\Portal;

use App\Payments\Bold\WebhookEventRepository;
use App\Services\BoldWebhookService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class BoldWebhookController
{
    public function __invoke(Request $request): Response
    {
        $config = config('bold', []);
        $environment = app()->environment();
        if (!BoldWebhookService::allowed($environment, $config,
            (string) $request->server('REMOTE_ADDR'), $request->getHost())) {
            return new Response('', 404);
        }
        // Stream only a bounded amount; never use request->all() or reconstructed JSON.
        $stream = $request->getContent(true);
        $raw = stream_get_contents($stream, BoldWebhookService::MAX_BYTES + 1);
        if ($raw === false) return new Response('', 400);
        $service = new BoldWebhookService(
            fn () => WebhookEventRepository::open($config['database'], $environment, $config['environment']),
            $config['webhook']['merchant_id'],
        );
        return new Response('', $service->receive($raw,
            (string) $request->headers->get('x-bold-signature', ''),
            (string) $request->headers->get('Content-Type', '')),
            ['Cache-Control' => 'no-store']);
    }
}
