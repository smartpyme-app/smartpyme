<?php

namespace Tests\Unit\Services\FacturacionElectronica\CostaRica;

use App\Services\FacturacionElectronica\CostaRica\BccrTipoCambioClient;
use PHPUnit\Framework\TestCase;

final class BccrTipoCambioClientTest extends TestCase
{
    private BccrTipoCambioClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new BccrTipoCambioClient();
    }

    public function test_parse_response_reads_num_valor_from_plain_xml(): void
    {
        $xml = <<<'XML'
<Datos_de_INGC011_CAT_INDICADORECONOMIC>
    <INGC011_CAT_INDICADORECONOMIC>
        <COD_INDICADORINTERNO>318</COD_INDICADORINTERNO>
        <DES_FECHA>2026-08-05T00:00:00-06:00</DES_FECHA>
        <NUM_VALOR>512.34567000</NUM_VALOR>
    </INGC011_CAT_INDICADORECONOMIC>
</Datos_de_INGC011_CAT_INDICADORECONOMIC>
XML;

        $this->assertSame(512.34567, $this->client->parseResponse($xml));
    }

    public function test_parse_response_reads_num_valor_wrapped_in_dataset_diffgram(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<DataSet xmlns="http://ws.sdde.bccr.fi.cr">
    <diffgram>
        <NewDataSet>
            <Datos_de_INGC011_CAT_INDICADORECONOMIC>
                <COD_INDICADORINTERNO>318</COD_INDICADORINTERNO>
                <NUM_VALOR>594.97000000</NUM_VALOR>
            </Datos_de_INGC011_CAT_INDICADORECONOMIC>
        </NewDataSet>
    </diffgram>
</DataSet>
XML;

        $this->assertSame(594.97, $this->client->parseResponse($xml));
    }

    public function test_parse_response_reads_num_valor_from_html_escaped_soap_wrapper(): void
    {
        $inner = '<Datos_de_INGC011_CAT_INDICADORECONOMIC><INGC011_CAT_INDICADORECONOMIC>'
            .'<NUM_VALOR>510.50000000</NUM_VALOR></INGC011_CAT_INDICADORECONOMIC></Datos_de_INGC011_CAT_INDICADORECONOMIC>';
        $wrapped = '<string xmlns="http://ws.sdde.bccr.fi.cr">'.htmlspecialchars($inner, ENT_XML1).'</string>';

        $this->assertSame(510.5, $this->client->parseResponse($wrapped));
    }

    public function test_parse_response_returns_null_when_value_missing(): void
    {
        $xml = '<Datos_de_INGC011_CAT_INDICADORECONOMIC></Datos_de_INGC011_CAT_INDICADORECONOMIC>';
        $this->assertNull($this->client->parseResponse($xml));
    }

    public function test_parse_response_returns_null_when_value_is_zero_or_negative(): void
    {
        $this->assertNull($this->client->parseResponse('<NUM_VALOR>0</NUM_VALOR>'));
        $this->assertNull($this->client->parseResponse('<NUM_VALOR>-1</NUM_VALOR>'));
    }

    public function test_parse_response_returns_null_for_empty_body(): void
    {
        $this->assertNull($this->client->parseResponse(''));
    }

    public function test_parse_sdde_series_response_reads_valor(): void
    {
        $json = [
            'estado' => true,
            'mensaje' => 'Consulta exitosa',
            'datos' => [[
                'codigoIndicador' => '318',
                'nombreIndicador' => 'Tipo cambio venta',
                'series' => [['fecha' => '2026-08-07', 'valorDatoPorPeriodo' => 454.06]],
            ]],
        ];

        $this->assertSame(454.06, $this->client->parseSddeSeriesResponse($json));
    }

    public function test_parse_sdde_series_response_returns_null_when_empty(): void
    {
        $this->assertNull($this->client->parseSddeSeriesResponse(['estado' => true, 'datos' => []]));
        $this->assertNull($this->client->parseSddeSeriesResponse(null));
    }
}
