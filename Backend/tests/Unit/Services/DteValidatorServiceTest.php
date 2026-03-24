<?php

namespace Tests\Unit\Services;

use App\Services\Dte\DteValidatorService;
use PHPUnit\Framework\TestCase;

class DteValidatorServiceTest extends TestCase
{
    protected DteValidatorService $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new DteValidatorService();
    }

    protected function validDteData(): array
    {
        return [
            'dte_uuid' => 'uuid-123',
            'dte_type' => '01',
            'issuer_nit' => '06141234567890',
            'emission_date' => now()->format('Y-m-d'),
            'receiver_nit' => '06149876543210',
            'raw' => [
                'selloRecibido' => 'ABC123-sello-mh',
            ],
        ];
    }

    public function testValidatePassesWhenAllConditionsMet(): void
    {
        $dteData = $this->validDteData();
        $tenantNit = '0614-987654-321-0';

        $result = $this->validator->validate($dteData, $tenantNit);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateFailsWhenNitMismatch(): void
    {
        $dteData = $this->validDteData();
        $dteData['receiver_nit'] = '06141111111111';
        $tenantNit = '06149876543210';

        $result = $this->validator->validate($dteData, $tenantNit);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('NIT receptor', $result['errors'][0]);
        $this->assertStringContainsString('no coincide', $result['errors'][0]);
    }

    public function testValidatePassesWhenNitMatchesWithDifferentFormatting(): void
    {
        $dteData = $this->validDteData();
        $dteData['receiver_nit'] = '0614-987654-321-0';
        $tenantNit = '06149876543210';

        $result = $this->validator->validate($dteData, $tenantNit);

        $this->assertTrue($result['valid']);
    }

    public function testValidateFailsWhenSelloMissing(): void
    {
        $dteData = $this->validDteData();
        $dteData['raw'] = [];

        $result = $this->validator->validate($dteData, '06149876543210');

        $this->assertFalse($result['valid']);
        $this->assertContains('Falta sello de recepción del MH', $result['errors']);
    }

    public function testValidateAcceptsSelloRecibidoOrSello(): void
    {
        $dteData = $this->validDteData();
        $dteData['raw'] = ['sello' => 'sello-alternativo'];

        $result = $this->validator->validate($dteData, '06149876543210');

        $this->assertTrue($result['valid']);
    }

    public function testValidateFailsWhenEmissionDateTooOld(): void
    {
        $dteData = $this->validDteData();
        $dteData['emission_date'] = now()->subYears(2)->format('Y-m-d');

        $result = $this->validator->validate($dteData, '06149876543210');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('excede el rango permitido', $result['errors'][0]);
    }

    public function testValidateFailsWhenStructureInvalid(): void
    {
        $dteData = [
            'dte_uuid' => '',
            'dte_type' => '01',
            'issuer_nit' => '123',
            'emission_date' => '2025-01-01',
        ];

        $result = $this->validator->validate($dteData, '123');

        $this->assertFalse($result['valid']);
        $this->assertContains('Falta codigoGeneracion (dte_uuid)', $result['errors']);
    }

    public function testValidatePassesWhenReceiverNitEmpty(): void
    {
        $dteData = $this->validDteData();
        $dteData['receiver_nit'] = null;

        $result = $this->validator->validate($dteData, '06149876543210');

        $this->assertTrue($result['valid']);
    }
}
