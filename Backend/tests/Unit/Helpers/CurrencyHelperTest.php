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

    public function test_code_returns_moneda_or_usd(): void
    {
        $empresa = new Empresa(['moneda' => 'hnl']);

        $this->assertSame('HNL', CurrencyHelper::code($empresa));
    }

    public function test_label_uses_currency_name_when_present(): void
    {
        $currency = new Currency(['currency_code' => 'CRC', 'currency_name' => 'Colón costarricense']);
        $empresa = new Empresa(['moneda' => 'CRC']);
        $empresa->setRelation('currency', $currency);

        $this->assertSame('Colón costarricense (CRC)', CurrencyHelper::label($empresa));
    }
}
