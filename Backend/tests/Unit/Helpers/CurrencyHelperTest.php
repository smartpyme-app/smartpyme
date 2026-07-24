<?php

namespace Tests\Unit\Helpers;

use App\Helpers\CurrencyHelper;
use App\Models\Admin\Empresa;
use App\Models\Currency;
use PHPUnit\Framework\TestCase;

final class CurrencyHelperTest extends TestCase
{
    public function test_symbol_uses_currency_relation_when_present(): void
    {
        $currency = new Currency(['currency_code' => 'CRC', 'currency_symbol' => '₡']);
        $empresa = new Empresa(['moneda' => 'CRC']);
        $empresa->setRelation('currency', $currency);

        $this->assertSame('₡', CurrencyHelper::symbol($empresa));
    }

    public function test_symbol_falls_back_by_moneda_code_when_currency_missing(): void
    {
        $empresa = new Empresa(['moneda' => 'CRC']);
        $empresa->setRelation('currency', null);

        $this->assertSame('₡', CurrencyHelper::symbol($empresa));
    }

    public function test_symbol_falls_back_to_dollar_when_unknown(): void
    {
        $empresa = new Empresa(['moneda' => 'XYZ']);
        $empresa->setRelation('currency', null);

        $this->assertSame('$', CurrencyHelper::symbol($empresa));
    }
}
