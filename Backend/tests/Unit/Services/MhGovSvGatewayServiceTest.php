<?php

namespace Tests\Unit\Services;

use App\Models\Admin\Empresa;
use App\Services\MhGovSvGatewayService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MhGovSvGatewayServiceTest extends TestCase
{
    public function test_post_json_envia_user_agent_requerido_por_mh(): void
    {
        Http::fake([
            'https://api.dtes.mh.gob.sv/seguridad/auth' => Http::response([
                'status' => 'OK',
                'body' => ['token' => 'Bearer test-token'],
            ], 200),
            'https://api.dtes.mh.gob.sv/fesv/recepciondte' => Http::response([
                'estado' => 'PROCESADO',
            ], 200),
        ]);

        $empresa = new Empresa([
            'fe_ambiente' => '01',
            'mh_usuario' => '06143006690074',
            'mh_contrasena' => 'secret',
        ]);
        $empresa->id = 24;

        $result = (new MhGovSvGatewayService())->postJson($empresa, '/fesv/recepciondte', [
            'ambiente' => '01',
            'idEnvio' => 1,
            'version' => 1,
            'tipoDte' => '01',
            'documento' => 'jwt',
        ]);

        $this->assertSame(200, $result['status']);

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), '/fesv/recepciondte')) {
                return false;
            }

            $ua = $request->header('User-Agent')[0] ?? '';

            return $request->hasHeader('Authorization', 'Bearer test-token')
                && $ua !== ''
                && !str_starts_with($ua, 'GuzzleHttp');
        });
    }
}
