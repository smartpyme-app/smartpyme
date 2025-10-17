<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DocumentationController extends Controller
{
    /**
     * Mostrar la documentación Swagger UI
     */
    public function index()
    {
        return view('external-api.documentation');
    }

    /**
     * Servir el archivo JSON de especificación OpenAPI
     */
    public function json()
    {
        $spec = [
            "openapi" => "3.0.0",
            "info" => [
                "title" => "SmartPYME External API",
                "description" => "API Externa para proveedores terceros - Acceso a datos de ventas e inventario",
                "version" => "1.0.0",
            ],
            "servers" => [
                [
                    "url" => "/api/external/v1",
                    "description" => "API Externa SmartPYME"
                ]
            ],
            "security" => [
                ["ApiKeyAuth" => []]
            ],
            "components" => [
                "securitySchemes" => [
                    "ApiKeyAuth" => [
                        "type" => "http",
                        "scheme" => "bearer",
                        "description" => "API Key de la empresa en formato Bearer token"
                    ]
                ]
            ],
            "tags" => [
                [
                    "name" => "Ventas",
                    "description" => "Endpoints para consultar información de ventas"
                ],
                [
                    "name" => "Inventario",
                    "description" => "Endpoints para consultar información de inventario"
                ]
            ],
            "paths" => [
                "/sales" => [
                    "get" => [
                        "tags" => ["Ventas"],
                        "summary" => "Listar ventas",
                        "description" => "Obtiene una lista paginada de ventas con filtros opcionales",
                        "security" => [["ApiKeyAuth" => []]],
                        "parameters" => [
                            [
                                "name" => "fecha_inicio",
                                "in" => "query",
                                "description" => "Fecha de inicio (Y-m-d)",
                                "required" => false,
                                "schema" => [
                                    "type" => "string",
                                    "format" => "date",
                                    "example" => "2025-01-01"
                                ]
                            ],
                            [
                                "name" => "fecha_fin",
                                "in" => "query",
                                "description" => "Fecha de fin (Y-m-d)",
                                "required" => false,
                                "schema" => [
                                    "type" => "string",
                                    "format" => "date",
                                    "example" => "2025-01-31"
                                ]
                            ],
                            [
                                "name" => "estado",
                                "in" => "query",
                                "description" => "Estado de la venta",
                                "required" => false,
                                "schema" => [
                                    "type" => "string",
                                    "enum" => ["Completada", "Pendiente", "Anulada", "Cotizacion"]
                                ]
                            ],
                            [
                                "name" => "page",
                                "in" => "query",
                                "description" => "Número de página",
                                "required" => false,
                                "schema" => [
                                    "type" => "integer",
                                    "minimum" => 1,
                                    "default" => 1
                                ]
                            ],
                            [
                                "name" => "per_page",
                                "in" => "query",
                                "description" => "Registros por página",
                                "required" => false,
                                "schema" => [
                                    "type" => "integer",
                                    "minimum" => 1,
                                    "maximum" => 200,
                                    "default" => 100
                                ]
                            ],
                            [
                                "name" => "order_by",
                                "in" => "query",
                                "description" => "Campo de ordenamiento",
                                "required" => false,
                                "schema" => [
                                    "type" => "string",
                                    "enum" => ["fecha", "total", "correlativo", "created_at"],
                                    "default" => "fecha"
                                ]
                            ],
                            [
                                "name" => "order_direction",
                                "in" => "query",
                                "description" => "Dirección del ordenamiento",
                                "required" => false,
                                "schema" => [
                                    "type" => "string",
                                    "enum" => ["asc", "desc"],
                                    "default" => "desc"
                                ]
                            ]
                        ],
                        "responses" => [
                            "200" => [
                                "description" => "Lista de ventas obtenida exitosamente",
                                "content" => [
                                    "application/json" => [
                                        "schema" => [
                                            "type" => "object",
                                            "properties" => [
                                                "success" => [
                                                    "type" => "boolean",
                                                    "example" => true
                                                ],
                                                "data" => [
                                                    "type" => "array",
                                                    "items" => [
                                                        "type" => "object",
                                                        "properties" => [
                                                            "fecha" => [
                                                                "type" => "string",
                                                                "format" => "date",
                                                                "example" => "2025-01-15"
                                                            ],
                                                            "correlativo" => [
                                                                "type" => "string",
                                                                "example" => "FAC-001"
                                                            ],
                                                            "estado" => [
                                                                "type" => "string",
                                                                "example" => "Completada"
                                                            ],
                                                            "total" => [
                                                                "type" => "number",
                                                                "format" => "float",
                                                                "example" => 150.75
                                                            ],
                                                            "nombre_cliente" => [
                                                                "type" => "string",
                                                                "example" => "Juan Pérez"
                                                            ]
                                                        ]
                                                    ]
                                                ],
                                                "pagination" => [
                                                    "type" => "object",
                                                    "properties" => [
                                                        "current_page" => ["type" => "integer", "example" => 1],
                                                        "per_page" => ["type" => "integer", "example" => 100],
                                                        "total" => ["type" => "integer", "example" => 1250]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            "401" => [
                                "description" => "No autorizado"
                            ],
                            "429" => [
                                "description" => "Rate limit excedido"
                            ]
                        ]
                    ]
                ],
                "/sales/{id}" => [
                    "get" => [
                        "tags" => ["Ventas"],
                        "summary" => "Obtener venta específica",
                        "description" => "Obtiene los detalles completos de una venta específica",
                        "security" => [["ApiKeyAuth" => []]],
                        "parameters" => [
                            [
                                "name" => "id",
                                "in" => "path",
                                "required" => true,
                                "description" => "ID de la venta",
                                "schema" => [
                                    "type" => "integer",
                                    "example" => 123
                                ]
                            ]
                        ],
                        "responses" => [
                            "200" => [
                                "description" => "Venta obtenida exitosamente"
                            ],
                            "404" => [
                                "description" => "Venta no encontrada"
                            ]
                        ]
                    ]
                ],
                "/sales/summary" => [
                    "get" => [
                        "tags" => ["Ventas"],
                        "summary" => "Resumen de ventas",
                        "description" => "Obtiene un resumen estadístico de las ventas",
                        "security" => [["ApiKeyAuth" => []]],
                        "parameters" => [
                            [
                                "name" => "fecha_inicio",
                                "in" => "query",
                                "description" => "Fecha de inicio para el resumen",
                                "required" => false,
                                "schema" => [
                                    "type" => "string",
                                    "format" => "date"
                                ]
                            ],
                            [
                                "name" => "fecha_fin",
                                "in" => "query",
                                "description" => "Fecha de fin para el resumen",
                                "required" => false,
                                "schema" => [
                                    "type" => "string",
                                    "format" => "date"
                                ]
                            ]
                        ],
                        "responses" => [
                            "200" => [
                                "description" => "Resumen de ventas obtenido exitosamente"
                            ]
                        ]
                    ]
                ],
                "/inventory" => [
                    "get" => [
                        "tags" => ["Inventario"],
                        "summary" => "Listar productos con inventario",
                        "description" => "Obtiene una lista paginada de productos con información de inventario",
                        "security" => [["ApiKeyAuth" => []]],
                        "parameters" => [
                            [
                                "name" => "codigo",
                                "in" => "query",
                                "description" => "Buscar por código de producto",
                                "required" => false,
                                "schema" => [
                                    "type" => "string",
                                    "example" => "PROD-001"
                                ]
                            ],
                            [
                                "name" => "nombre",
                                "in" => "query",
                                "description" => "Buscar por nombre de producto",
                                "required" => false,
                                "schema" => [
                                    "type" => "string",
                                    "example" => "Samsung"
                                ]
                            ],
                            [
                                "name" => "categoria",
                                "in" => "query",
                                "description" => "Filtrar por categoría",
                                "required" => false,
                                "schema" => [
                                    "type" => "string",
                                    "example" => "Electrónicos"
                                ]
                            ],
                            [
                                "name" => "con_stock",
                                "in" => "query",
                                "description" => "Solo productos con stock disponible",
                                "required" => false,
                                "schema" => [
                                    "type" => "boolean"
                                ]
                            ],
                            [
                                "name" => "stock_minimo",
                                "in" => "query",
                                "description" => "Solo productos con stock bajo el mínimo",
                                "required" => false,
                                "schema" => [
                                    "type" => "boolean"
                                ]
                            ],
                            [
                                "name" => "page",
                                "in" => "query",
                                "description" => "Número de página",
                                "required" => false,
                                "schema" => [
                                    "type" => "integer",
                                    "minimum" => 1,
                                    "default" => 1
                                ]
                            ],
                            [
                                "name" => "per_page",
                                "in" => "query",
                                "description" => "Registros por página",
                                "required" => false,
                                "schema" => [
                                    "type" => "integer",
                                    "minimum" => 1,
                                    "maximum" => 200,
                                    "default" => 100
                                ]
                            ]
                        ],
                        "responses" => [
                            "200" => [
                                "description" => "Lista de productos obtenida exitosamente"
                            ]
                        ]
                    ]
                ],
                "/inventory/{id}" => [
                    "get" => [
                        "tags" => ["Inventario"],
                        "summary" => "Obtener producto específico",
                        "description" => "Obtiene los detalles completos de un producto específico",
                        "security" => [["ApiKeyAuth" => []]],
                        "parameters" => [
                            [
                                "name" => "id",
                                "in" => "path",
                                "required" => true,
                                "description" => "ID del producto",
                                "schema" => [
                                    "type" => "integer",
                                    "example" => 456
                                ]
                            ]
                        ],
                        "responses" => [
                            "200" => [
                                "description" => "Producto obtenido exitosamente"
                            ],
                            "404" => [
                                "description" => "Producto no encontrado"
                            ]
                        ]
                    ]
                ],
                "/inventory/summary" => [
                    "get" => [
                        "tags" => ["Inventario"],
                        "summary" => "Resumen de inventario",
                        "description" => "Obtiene un resumen estadístico del inventario",
                        "security" => [["ApiKeyAuth" => []]],
                        "parameters" => [
                            [
                                "name" => "categoria",
                                "in" => "query",
                                "description" => "Filtrar resumen por categoría",
                                "required" => false,
                                "schema" => [
                                    "type" => "string"
                                ]
                            ]
                        ],
                        "responses" => [
                            "200" => [
                                "description" => "Resumen de inventario obtenido exitosamente"
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return response()->json($spec, 200, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
