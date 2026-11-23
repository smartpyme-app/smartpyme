<?php

namespace Tests\Unit\Constants;

use App\Constants\DocumentoConstants;
use PHPUnit\Framework\TestCase;

class DocumentoConstantsNotasTest extends TestCase
{
    public function test_nombres_nota_credito_debito_incluye_sv_y_cr(): void
    {
        $nombres = DocumentoConstants::NOMBRES_NOTA_CREDITO_DEBITO;

        $this->assertContains('Nota de crédito', $nombres);
        $this->assertContains('Nota de débito', $nombres);
        $this->assertContains('Nota de Crédito Electrónica', $nombres);
        $this->assertContains('Nota de Débito Electrónica', $nombres);
    }
}
