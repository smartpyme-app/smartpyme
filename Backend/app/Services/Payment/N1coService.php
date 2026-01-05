<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class N1coService
{
    /**
     * Obtiene token de API de N1co
     *
     * @return string
     */
    public function obtenerTokenApi(): string
    {
        try {
            $baseUrl = env('N1CO_SANDBOX_URL', 'https://api-sandbox.n1co.shop/api/v2');

            $response = Http::post($baseUrl . '/Token', [
                'clientId' => env('CLIENT_ID'),
                'clientSecret' => env('CLIENT_SECRET')
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['accessToken'];
            }

            Log::error('Error obteniendo token N1co', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return '';
        } catch (\Exception $e) {
            Log::error('Exception obteniendo token N1co', [
                'message' => $e->getMessage()
            ]);
            return '';
        }
    }

    /**
     * Crea un enlace de pago en N1co
     *
     * @param array $data
     * @return array
     */
    public function crearEnlacePago(array $data): array
    {
        try {
            $baseUrl = 'https://api-pay-sandbox.n1co.shop/api/v2';
            $apiToken = $this->obtenerTokenApi();

            if (empty($apiToken)) {
                return [
                    'success' => false,
                    'error' => 'No se pudo obtener el token de autenticación'
                ];
            }

            $payload = [
                'orderName' => $data['name'] ?? 'Orden de SmartPyme',
                'orderDescription' => $data['description'] ?? null,
                'amount' => floatval($data['amount']),
                'successUrl' => url('/api/pago-completado'),
                'cancelUrl' => url('/api/pago-cancelado'),
                'metadata' => [
                    [
                        'name' => 'userId',
                        'value' => (string)$data['user_id']
                    ],
                    [
                        'name' => 'plan',
                        'value' => $data['plan']
                    ]
                ]
            ];

            Log::info('N1co Request Payload', $payload);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($baseUrl . '/paymentlink/checkout', $payload);

            if ($response->successful()) {
                $responseData = $response->json();

                $this->guardarOrdenPago($data, $responseData);

                Log::info('Orden creada', [
                    'response' => $responseData,
                    'payload' => $payload
                ]);

                return [
                    'success' => true,
                    'paymentLinkUrl' => $responseData['paymentLinkUrl']
                ];
            }

            Log::error('N1co Payment Link Error', [
                'status' => $response->status(),
                'response' => $response->body(),
                'request_data' => $payload,
                'url' => $baseUrl . '/paymentlink/checkout'
            ]);

            return [
                'success' => false,
                'error' => 'Error al crear enlace de pago: ' . ($response->json()['title'] ?? 'Error desconocido')
            ];
        } catch (\Exception $e) {
            Log::error('N1co Payment Link Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Guarda orden de pago en la base de datos
     *
     * @param array $data
     * @param array $responseData
     * @return void
     */
    private function guardarOrdenPago(array $data, array $responseData): void
    {
        DB::table('ordenes_pagos')->insert([
            'id_orden' => $responseData['orderId'],
            'order_code' => $responseData['orderCode'],
            'id_usuario' => $data['user_id'],
            'plan' => $data['plan'],
            'monto' => number_format($data['amount'], 2, '.', ''),
            'estado' => 'pendiente',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
