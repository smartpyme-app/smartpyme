<?php

namespace Tests\Unit\Support\Dte;

use App\Support\Dte\DteEmailAttachmentHelper;
use PHPUnit\Framework\TestCase;

class DteEmailAttachmentHelperTest extends TestCase
{
    public function test_group_attachments_costa_rica_email(): void
    {
        $comprobante = file_get_contents('/Users/macbook/Downloads/50611052600310174405500176401010000007844111052601.xml');
        $acuse = file_get_contents('/Users/macbook/Downloads/50611052600310174405500176401010000007844111052601_respuesta.xml');
        $pdf = '%PDF-sample';

        $groups = DteEmailAttachmentHelper::groupAttachments('msg-123', [
            ['filename' => '50611052600310174405500176401010000007844111052601.xml', 'content' => $comprobante],
            ['filename' => '50611052600310174405500176401010000007844111052601_respuesta.xml', 'content' => $acuse],
            ['filename' => '50611052600310174405500176401010000007844111052601.pdf', 'content' => $pdf],
        ]);

        $this->assertCount(1, $groups);
        $this->assertSame(DteEmailAttachmentHelper::FORMAT_XML, $groups[0]['source_format']);
        $this->assertSame('50611052600310174405500176401010000007844111052601', $groups[0]['clave']);
        $this->assertNotEmpty($groups[0]['source_content']);
        $this->assertSame($pdf, $groups[0]['pdf_content']);
        $this->assertSame($acuse, $groups[0]['acuse_content']);
    }

    public function test_group_attachments_acuse_prefixed_filename(): void
    {
        $comprobante = file_get_contents('/Users/macbook/Downloads/50606032600310100718610500004010000034880144304440.xml');
        $acuse = file_get_contents('/Users/macbook/Downloads/Acuse 50606032600310100718610500004010000034880144304440.xml');

        $groups = DteEmailAttachmentHelper::groupAttachments('msg-456', [
            ['filename' => '50606032600310100718610500004010000034880144304440.xml', 'content' => $comprobante],
            ['filename' => 'Acuse 50606032600310100718610500004010000034880144304440.xml', 'content' => $acuse],
        ]);

        $this->assertCount(1, $groups);
        $this->assertSame($acuse, $groups[0]['acuse_content']);
        $this->assertSame('Aceptado', DteEmailAttachmentHelper::extractAcuseEstado($acuse));
    }

    public function test_group_json_el_salvador(): void
    {
        $json = '{"identificacion":{"codigoGeneracion":"abc"}}';
        $groups = DteEmailAttachmentHelper::groupAttachments('msg-sv', [
            ['filename' => 'dte.json', 'content' => $json],
        ]);

        $this->assertCount(1, $groups);
        $this->assertSame(DteEmailAttachmentHelper::FORMAT_JSON, $groups[0]['source_format']);
        $this->assertSame('msg-sv', $groups[0]['email_message_id']);
    }
}
