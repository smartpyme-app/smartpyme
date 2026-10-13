<?php

namespace App\Services;

class ShopifyInventarioCantidad
{
    public const API_VERSION = '2026-10';

    public function leer(ShopifyApiClient $client, int $inventoryItemId, int $locationId): ?array
    {
        $data = $client->graphql(
            'query Nivel($id: ID!, $locationId: ID!) {
                inventoryItem(id: $id) {
                    inventoryLevel(locationId: $locationId) {
                        quantities(names: ["on_hand", "available", "committed"]) { name quantity }
                    }
                }
            }',
            [
                'id' => "gid://shopify/InventoryItem/{$inventoryItemId}",
                'locationId' => "gid://shopify/Location/{$locationId}",
            ],
            self::API_VERSION
        );

        $filas = $data['inventoryItem']['inventoryLevel']['quantities'] ?? null;
        if (!is_array($filas)) {
            return null;
        }

        $out = ['on_hand' => 0, 'available' => 0, 'committed' => 0];
        foreach ($filas as $fila) {
            $nombre = $fila['name'] ?? '';
            if (array_key_exists($nombre, $out)) {
                $out[$nombre] = (int) $fila['quantity'];
            }
        }

        return $out;
    }

    public function fijarOnHand(ShopifyApiClient $client, int $inventoryItemId, int $locationId, int $onHand): bool
    {
        $actual = $this->leer($client, $inventoryItemId, $locationId);
        if ($actual === null) {
            return false;
        }
        if ($actual['on_hand'] === $onHand) {
            return true;
        }

        $errores = $this->mutarOnHand($client, $inventoryItemId, $locationId, $onHand, $actual['on_hand']);
        if ($this->esErrorDeSeguimiento($errores)) {
            $client->put("inventory_items/{$inventoryItemId}.json", [
                'inventory_item' => [
                    'id' => $inventoryItemId,
                    'tracked' => true,
                ],
            ]);
            $errores = $this->mutarOnHand($client, $inventoryItemId, $locationId, $onHand, $actual['on_hand']);
        }

        return $errores === [];
    }

    private function mutarOnHand(ShopifyApiClient $client, int $inventoryItemId, int $locationId, int $onHand, int $changeFromQuantity): array
    {
        $data = $client->graphql(
            'mutation Fijar($input: InventorySetQuantitiesInput!) {
                inventorySetQuantities(input: $input) {
                    userErrors { field message code }
                }
            }',
            [
                'input' => [
                    'name' => 'on_hand',
                    'reason' => 'correction',
                    'referenceDocumentUri' => "smartpyme://inventario/{$inventoryItemId}/{$locationId}",
                    'quantities' => [[
                        'inventoryItemId' => "gid://shopify/InventoryItem/{$inventoryItemId}",
                        'locationId' => "gid://shopify/Location/{$locationId}",
                        'quantity' => $onHand,
                        'changeFromQuantity' => $changeFromQuantity,
                    ]],
                ],
            ],
            self::API_VERSION
        );

        $errores = $data['inventorySetQuantities']['userErrors'] ?? null;

        return is_array($errores) ? $errores : [['message' => 'respuesta sin userErrors']];
    }

    private function esErrorDeSeguimiento(array $errores): bool
    {
        foreach ($errores as $error) {
            $mensaje = strtolower((string) ($error['message'] ?? ''));
            if (str_contains($mensaje, 'track')) {
                return true;
            }
        }

        return false;
    }
}
