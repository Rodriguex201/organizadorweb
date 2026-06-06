<?php

namespace Tests\Feature;

use App\Services\ProformaEmailService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class ProformaEmailServiceRecipientParsingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('clientes_potenciales');
        Schema::create('clientes_potenciales', function (Blueprint $table): void {
            $table->unsignedInteger('idclientes_potenciales')->primary();
            $table->string('nit')->nullable();
            $table->text('email')->nullable();
        });
    }

    public function test_resolve_destinatarios_acepta_comas_punto_y_coma_espacios_y_descarta_invalidos(): void
    {
        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 1,
            'nit' => '9001',
            'email' => 'correo1@empresa.com ; correo2@empresa.com, correo-invalido, ; ,correo3@empresa.com;correo2@empresa.com',
        ]);

        $service = new ProformaEmailService();
        $proforma = (object) [
            'id' => 77,
            'id_cliente' => 1,
            'nit' => '9001',
        ];

        $destinatarios = $service->resolveDestinatarios($proforma);

        $this->assertSame(
            'correo1@empresa.com ; correo2@empresa.com, correo-invalido, ; ,correo3@empresa.com;correo2@empresa.com',
            $destinatarios['original']
        );
        $this->assertSame([
            'correo1@empresa.com',
            'correo2@empresa.com',
            'correo3@empresa.com',
        ], $destinatarios['emails']);
        $this->assertSame(3, $destinatarios['count']);
        $this->assertSame(['correo-invalido'], $destinatarios['invalidos']);
    }

    public function test_send_proforma_envia_a_resend_un_array_to_en_lugar_de_un_string_concatenado(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('proformas/pf-88.pdf', 'pdf-demo');

        config([
            'services.resend.key' => 're_test_123',
            'services.resend.from_address' => 'facturacion@empresa.com',
            'services.resend.from_name' => 'RM Soft',
            'services.resend.reply_to' => 'cartera@empresa.com',
        ]);

        Http::fake([
            'https://api.resend.com/emails' => Http::response(['id' => 'msg_123'], 200),
        ]);

        $service = new ProformaEmailService();
        $proforma = (object) [
            'id' => 88,
            'nro_prof' => 'PF-88',
            'rpdf' => 'proformas',
            'npdf' => 'pf-88.pdf',
        ];

        $destinatarios = [
            'original' => 'correo1@empresa.com; correo2@empresa.com',
            'emails' => ['correo1@empresa.com', 'correo2@empresa.com'],
            'count' => 2,
            'invalidos' => [],
        ];

        $service->sendProforma($proforma, [
            'destinatarios' => $destinatarios,
            'log_prefix' => '[TEST RESEND]',
        ]);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.resend.com/emails'
                && $payload['to'] === ['correo1@empresa.com', 'correo2@empresa.com']
                && $payload['reply_to'] === ['cartera@empresa.com']
                && $payload['subject'] === 'Proforma #PF-88';
        });
    }
}
