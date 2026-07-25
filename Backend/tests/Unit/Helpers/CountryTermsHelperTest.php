<?php

namespace Tests\Unit\Helpers;

use App\Helpers\CountryTermsHelper;
use App\Models\Admin\Empresa;
use PHPUnit\Framework\TestCase;

final class CountryTermsHelperTest extends TestCase
{
    public function test_tax_label_is_isv_for_honduras(): void
    {
        $empresa = new Empresa(['pais' => 'Honduras', 'cod_pais' => 'HN']);

        $this->assertSame('ISV', CountryTermsHelper::tax('taxLabel', $empresa));
        $this->assertSame('ISV:', CountryTermsHelper::tax('taxLabelColon', $empresa));
    }

    public function test_tax_label_is_iva_for_el_salvador(): void
    {
        $empresa = new Empresa(['pais' => 'El Salvador', 'cod_pais' => 'SV']);

        $this->assertSame('IVA', CountryTermsHelper::tax('taxLabel', $empresa));
    }

    public function test_tax_label_is_impuesto_for_costa_rica(): void
    {
        $empresa = new Empresa(['pais' => 'Costa Rica', 'cod_pais' => 'CR']);

        $this->assertSame('Impuesto', CountryTermsHelper::tax('taxLabel', $empresa));
    }

    public function test_tax_with_rate_replaces_placeholder(): void
    {
        $empresa = new Empresa(['pais' => 'Honduras', 'cod_pais' => 'HN']);

        $this->assertSame('ISV (15%)', CountryTermsHelper::tax('taxRateLabel', $empresa, ['rate' => 15]));
        $this->assertSame('Total sin ISV', CountryTermsHelper::tax('totalWithoutTax', $empresa));
    }
}
