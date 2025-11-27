<?php

namespace App\Helpers;

class Currency
{
    /**
     * Format currency amount
     * Compatible with magarrent/laravel-currency-formatter API
     * 
     * @param string $currencyCode Currency code (USD, EUR, etc.)
     * @return self
     */
    public static function currency($currencyCode = 'USD')
    {
        return new self($currencyCode);
    }

    protected $currencyCode;

    public function __construct($currencyCode = 'USD')
    {
        $this->currencyCode = $currencyCode;
    }

    /**
     * Format the amount as currency
     * 
     * @param float $amount Amount to format
     * @return string Formatted currency string
     */
    public function format($amount)
    {
        // Use PHP's NumberFormatter for proper currency formatting
        $formatter = new \NumberFormatter('en_US', \NumberFormatter::CURRENCY);
        
        // Map common currency codes
        $currencyMap = [
            'USD' => 'USD',
            'EUR' => 'EUR',
            'GBP' => 'GBP',
            'GTQ' => 'GTQ', // Guatemalan Quetzal
            'HNL' => 'HNL', // Honduran Lempira
            'NIO' => 'NIO', // Nicaraguan Córdoba
            'CRC' => 'CRC', // Costa Rican Colón
            'PAB' => 'PAB', // Panamanian Balboa
            'BZD' => 'BZD', // Belize Dollar
            'SVC' => 'SVC', // Salvadoran Colón (deprecated, but might be used)
        ];

        $currency = $currencyMap[strtoupper($this->currencyCode)] ?? 'USD';
        
        try {
            $formatter->setTextAttribute(\NumberFormatter::CURRENCY_CODE, $currency);
            return $formatter->formatCurrency((float) $amount, $currency);
        } catch (\Exception $e) {
            // Fallback to simple formatting
            return '$' . number_format((float) $amount, 2, '.', ',');
        }
    }
}

