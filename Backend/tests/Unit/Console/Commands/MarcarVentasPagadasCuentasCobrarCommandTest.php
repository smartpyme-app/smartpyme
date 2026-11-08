<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\MarcarVentasPagadasCuentasCobrarCommand;
use PHPUnit\Framework\TestCase;

final class MarcarVentasPagadasCuentasCobrarCommandTest extends TestCase
{
  public function test_nombre_hoja_mes(): void
  {
    $this->assertSame('ENE.2026', MarcarVentasPagadasCuentasCobrarCommand::nombreHojaMes(1, 2026));
    $this->assertSame('DIC.2025', MarcarVentasPagadasCuentasCobrarCommand::nombreHojaMes(12, 2025));
  }

  public function test_numero_control_coincide_formatos_corto_y_largo(): void
  {
    $corto = 'DTE-01-261-0051';
    $largo = 'DTE-01-26100001-000000000000051';

    $this->assertTrue(MarcarVentasPagadasCuentasCobrarCommand::numeroControlCoincide($largo, $corto));
    $this->assertTrue(MarcarVentasPagadasCuentasCobrarCommand::numeroControlCoincide($corto, $corto));
    $this->assertFalse(MarcarVentasPagadasCuentasCobrarCommand::numeroControlCoincide($largo, 'DTE-01-261-0099'));
    $this->assertTrue(MarcarVentasPagadasCuentasCobrarCommand::correlativoCoincideConDoc(51, $corto));
    $this->assertFalse(MarcarVentasPagadasCuentasCobrarCommand::correlativoCoincideConDoc(26, $corto));
  }

  public function test_normalizar_nombre_y_total(): void
  {
    $this->assertSame(
      'FUNDES INTERNACIONAL S.A.',
      MarcarVentasPagadasCuentasCobrarCommand::normalizarNombre("  FUNDES   INTERNACIONAL  S.A.  \n")
    );
    $this->assertTrue(MarcarVentasPagadasCuentasCobrarCommand::totalesCoinciden(2260.0, 2260.01));
    $this->assertFalse(MarcarVentasPagadasCuentasCobrarCommand::totalesCoinciden(2260.0, 2260.05));
  }
}
