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
        // Evitar cache del navegador para que se actualice inmediatamente
        return response()->json($this->getOpenApiSpec())
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT')
            ->header('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
    }
    
    /**
     * Obtener la especificación OpenAPI
     */
    private function getOpenApiSpec()
    {
        $spec = [
            "openapi" => "3.0.0",
            "info" => [
                "title" => "SmartPYME External API",
                "description" => "API Externa para proveedores terceros - Consulta de ventas, inventario, devoluciones e importación de paquetes. Límites: 1000/2000 requests por hora.",
                "version" => "1.3.0-" . time(),
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
                    "name" => "Sistema",
                    "description" => "Endpoints del sistema para monitoreo y estado"
                ],
                [
                    "name" => "Ventas",
                    "description" => "Endpoints para consultar información de ventas"
                ],
                [
                    "name" => "Inventario",
                    "description" => "Endpoints para consultar información de inventario"
                ],
                [
                    "name" => "Devoluciones",
                    "description" => "Endpoints para consultar información de devoluciones de ventas"
                ],
                [
                    "name" => "Paquetes",
                    "description" => "Importación masiva de paquetes desde sistemas externos"
                ]
            ],
            "paths" => [
                "/system/rate-limit" => [
                    "get" => [
                        "tags" => ["Sistema"],
                        "summary" => "Verificar estado del rate limit",
                        "description" => "Obtiene información sobre el uso actual del rate limit",
                        "security" => [["ApiKeyAuth" => []]],
                        "responses" => [
                            "200" => [
                                "description" => "Estado del rate limit obtenido exitosamente",
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
                                                    "type" => "object",
                                                    "properties" => [
                                                        "requests_used" => [
                                                            "type" => "integer",
                                                            "example" => 25,
                                                            "description" => "Número de requests utilizados en la ventana actual"
                                                        ],
                                                        "max_requests" => [
                                                            "type" => "integer",
                                                            "example" => 1000,
                                                            "description" => "Límite máximo de requests para la ventana actual"
                                                        ],
                                                        "remaining_requests" => [
                                                            "type" => "integer",
                                                            "example" => 975,
                                                            "description" => "Requests restantes en la ventana actual"
                                                        ],
                                                        "reset_time" => [
                                                            "type" => "string",
                                                            "format" => "date-time",
                                                            "example" => "2024-01-01T15:00:00Z",
                                                            "description" => "Tiempo cuando se resetea el límite"
                                                        ],
                                                        "window_minutes" => [
                                                            "type" => "integer",
                                                            "example" => 60,
                                                            "description" => "Duración de la ventana en minutos"
                                                        ],
                                                        "limits" => [
                                                            "type" => "object",
                                                            "properties" => [
                                                                "standard" => [
                                                                    "type" => "integer",
                                                                    "example" => 1000,
                                                                    "description" => "Límite para consultas estándar"
                                                                ],
                                                                "with_date_filters" => [
                                                                    "type" => "integer",
                                                                    "example" => 2000,
                                                                    "description" => "Límite cuando se usan filtros de fecha"
                                                                ]
                                                            ]
                                                        ],
                                                        "current_limit_type" => [
                                                            "type" => "string",
                                                            "enum" => ["standard", "with_date_filters"],
                                                            "example" => "standard",
                                                            "description" => "Tipo de límite actualmente aplicado"
                                                        ],
                                                        "empresa_id" => [
                                                            "type" => "integer",
                                                            "example" => 324
                                                        ],
                                                        "empresa_nombre" => [
                                                            "type" => "string",
                                                            "example" => "Mi Empresa"
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            "401" => [
                                "description" => "No autorizado - API key inválido",
                                "content" => [
                                    "application/json" => [
                                        "schema" => [
                                            "type" => "object",
                                            "properties" => [
                                                "success" => ["type" => "boolean", "example" => false],
                                                "error" => ["type" => "string", "example" => "API key inválido"],
                                                "code" => ["type" => "integer", "example" => 401]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            "500" => [
                                "description" => "Error interno del servidor",
                                "content" => [
                                    "application/json" => [
                                        "schema" => [
                                            "type" => "object",
                                            "properties" => [
                                                "success" => ["type" => "boolean", "example" => false],
                                                "error" => ["type" => "string", "example" => "Error interno del servidor"],
                                                "code" => ["type" => "integer", "example" => 500]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
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
                ],
                "/returns" => [
                    "get" => [
                        "tags" => ["Devoluciones"],
                        "summary" => "Listar devoluciones de ventas",
                        "description" => "Obtiene una lista paginada de devoluciones con filtros opcionales",
                        "security" => [["ApiKeyAuth" => []]],
                        "parameters" => [
                            [
                                "name" => "fecha_inicio",
                                "in" => "query",
                                "description" => "Fecha de inicio (YYYY-MM-DD)",
                                "required" => false,
                                "schema" => [
                                    "type" => "string",
                                    "format" => "date",
                                    "example" => "2024-01-01"
                                ]
                            ],
                            [
                                "name" => "fecha_fin",
                                "in" => "query",
                                "description" => "Fecha de fin (YYYY-MM-DD)",
                                "required" => false,
                                "schema" => [
                                    "type" => "string",
                                    "format" => "date",
                                    "example" => "2024-12-31"
                                ]
                            ],
                            [
                                "name" => "id_venta",
                                "in" => "query",
                                "description" => "ID de la venta original",
                                "required" => false,
                                "schema" => [
                                    "type" => "integer",
                                    "example" => 123
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
                                    "default" => 1,
                                    "example" => 1
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
                                    "default" => 100,
                                    "example" => 100
                                ]
                            ]
                        ],
                        "responses" => [
                            "200" => [
                                "description" => "Lista de devoluciones obtenida exitosamente",
                                "content" => [
                                    "application/json" => [
                                        "schema" => [
                                            "type" => "object",
                                            "properties" => [
                                                "success" => ["type" => "boolean", "example" => true],
                                                "data" => [
                                                    "type" => "array",
                                                    "items" => [
                                                        "type" => "object",
                                                        "properties" => [
                                                            "id" => ["type" => "integer", "example" => 789],
                                                            "fecha" => ["type" => "string", "format" => "date", "example" => "2024-10-21"],
                                                            "correlativo" => ["type" => "string", "example" => "DEV-001234"],
                                                            "total" => ["type" => "number", "example" => 149.50],
                                                            "id_venta" => ["type" => "integer", "example" => 12345],
                                                            "nombre_cliente" => ["type" => "string", "example" => "Juan Pérez"]
                                                        ]
                                                    ]
                                                ],
                                                "pagination" => [
                                                    "type" => "object",
                                                    "properties" => [
                                                        "current_page" => ["type" => "integer", "example" => 1],
                                                        "per_page" => ["type" => "integer", "example" => 100],
                                                        "total" => ["type" => "integer", "example" => 25]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                "/returns/{id}" => [
                    "get" => [
                        "tags" => ["Devoluciones"],
                        "summary" => "Obtener devolución específica",
                        "description" => "Obtiene los detalles completos de una devolución específica",
                        "security" => [["ApiKeyAuth" => []]],
                        "parameters" => [
                            [
                                "name" => "id",
                                "in" => "path",
                                "description" => "ID de la devolución",
                                "required" => true,
                                "schema" => [
                                    "type" => "integer",
                                    "example" => 789
                                ]
                            ]
                        ],
                        "responses" => [
                            "200" => [
                                "description" => "Devolución obtenida exitosamente",
                                "content" => [
                                    "application/json" => [
                                        "schema" => [
                                            "type" => "object",
                                            "properties" => [
                                                "success" => ["type" => "boolean", "example" => true],
                                                "data" => [
                                                    "type" => "object",
                                                    "properties" => [
                                                        "id" => ["type" => "integer", "example" => 789],
                                                        "fecha" => ["type" => "string", "format" => "date", "example" => "2024-10-21"],
                                                        "total" => ["type" => "number", "example" => 149.50],
                                                        "observaciones" => ["type" => "string", "example" => "Producto defectuoso"],
                                                        "detalles" => [
                                                            "type" => "array",
                                                            "items" => [
                                                                "type" => "object",
                                                                "properties" => [
                                                                    "nombre_producto" => ["type" => "string", "example" => "Laptop Dell"],
                                                                    "codigo_producto" => ["type" => "string", "example" => "LAP-DELL-001"],
                                                                    "marca_producto" => ["type" => "string", "example" => "Dell"],
                                                                    "cantidad" => ["type" => "number", "example" => 1.0],
                                                                    "precio" => ["type" => "number", "example" => 750.00],
                                                                    "total" => ["type" => "number", "example" => 700.00]
                                                                ]
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                "/returns/summary" => [
                    "get" => [
                        "tags" => ["Devoluciones"],
                        "summary" => "Resumen de devoluciones",
                        "description" => "Obtiene un resumen estadístico de las devoluciones",
                        "security" => [["ApiKeyAuth" => []]],
                        "parameters" => [
                            [
                                "name" => "fecha_inicio",
                                "in" => "query",
                                "description" => "Fecha de inicio (YYYY-MM-DD)",
                                "required" => false,
                                "schema" => [
                                    "type" => "string",
                                    "format" => "date",
                                    "example" => "2024-01-01"
                                ]
                            ],
                            [
                                "name" => "fecha_fin",
                                "in" => "query",
                                "description" => "Fecha de fin (YYYY-MM-DD)",
                                "required" => false,
                                "schema" => [
                                    "type" => "string",
                                    "format" => "date",
                                    "example" => "2024-12-31"
                                ]
                            ]
                        ],
                        "responses" => [
                            "200" => [
                                "description" => "Resumen de devoluciones obtenido exitosamente",
                                "content" => [
                                    "application/json" => [
                                        "schema" => [
                                            "type" => "object",
                                            "properties" => [
                                                "success" => ["type" => "boolean", "example" => true],
                                                "data" => [
                                                    "type" => "object",
                                                    "properties" => [
                                                        "total_devoluciones" => ["type" => "integer", "example" => 25],
                                                        "total_monto" => ["type" => "number", "example" => 5250.75],
                                                        "promedio_devolucion" => ["type" => "number", "example" => 210.03]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                "/packages/import" => [
                    "post" => [
                        "tags" => ["Paquetes"],
                        "summary" => "Importar paquetes en lote",
                        "description" => "Crea paquetes en estado \"En bodega\" (misma lógica que la importación por Excel). Por cada ítem debe enviarse **id_sucursal** o **sucursal** (nombre exacto en base de datos, sin distinguir mayúsculas). Si ya existe un paquete con el mismo **wr** en la empresa, el ítem se omite (skipped). Máximo 500 ítems por solicitud. El usuario registrador se resuelve automáticamente (administrador o supervisor activo de la empresa).",
                        "security" => [["ApiKeyAuth" => []]],
                        "requestBody" => [
                            "required" => true,
                            "content" => [
                                "application/json" => [
                                    "schema" => [
                                        "type" => "object",
                                        "required" => ["packages"],
                                        "properties" => [
                                            "packages" => [
                                                "type" => "array",
                                                "minItems" => 1,
                                                "maxItems" => 500,
                                                "items" => [
                                                    "type" => "object",
                                                    "required" => [
                                                        "cliente",
                                                        "codigo_asesor",
                                                        "wr",
                                                        "guia",
                                                        "piezas",
                                                        "embalaje",
                                                        "peso",
                                                        "precio",
                                                        "cuenta_a_terceros",
                                                        "otros",
                                                        "total"
                                                    ],
                                                    "properties" => [
                                                        "id_sucursal" => [
                                                            "type" => "integer",
                                                            "description" => "ID de sucursal de la empresa. Si se envía, tiene prioridad sobre sucursal.",
                                                            "example" => 3
                                                        ],
                                                        "sucursal" => [
                                                            "type" => "string",
                                                            "description" => "Nombre de la sucursal (activa). Obligatorio si no se envía id_sucursal.",
                                                            "example" => "Sucursal Centro"
                                                        ],
                                                        "cliente" => [
                                                            "type" => "string",
                                                            "description" => "Nombre del cliente; si no existe se crea.",
                                                            "example" => "Importadora ABC"
                                                        ],
                                                        "codigo_asesor" => [
                                                            "type" => "string",
                                                            "description" => "Código interno del usuario asesor (campo codigo en usuarios).",
                                                            "example" => "A01"
                                                        ],
                                                        "wr" => [
                                                            "type" => "string",
                                                            "description" => "Identificador único del paquete por empresa (duplicado = skipped).",
                                                            "example" => "WR-2025-0001"
                                                        ],
                                                        "guia" => [
                                                            "type" => "string",
                                                            "description" => "Número de guía",
                                                            "example" => "GUIA-998877"
                                                        ],
                                                        "piezas" => ["type" => "number", "example" => 2],
                                                        "embalaje" => ["type" => "string", "example" => "Caja"],
                                                        "peso" => ["type" => "number", "example" => 12.5],
                                                        "precio" => ["type" => "number", "example" => 150.0],
                                                        "cuenta_a_terceros" => ["type" => "number", "example" => 0],
                                                        "otros" => ["type" => "number", "example" => 0],
                                                        "total" => ["type" => "number", "example" => 150.0],
                                                        "transportista" => ["type" => "string", "nullable" => true],
                                                        "consignatario" => ["type" => "string", "nullable" => true],
                                                        "transportador" => ["type" => "string", "nullable" => true],
                                                        "seguimiento" => ["type" => "string", "nullable" => true, "description" => "Número de seguimiento"],
                                                        "volumen" => ["type" => "number", "nullable" => true],
                                                        "nota" => ["type" => "string", "nullable" => true]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ],
                                    "example" => [
                                        "packages" => [
                                            [
                                                "sucursal" => "Sucursal Centro",
                                                "cliente" => "Cliente API",
                                                "codigo_asesor" => "A01",
                                                "wr" => "WR-EJEMPLO-001",
                                                "guia" => "G-10001",
                                                "seguimiento" => "TRACK-556677",
                                                "piezas" => 1,
                                                "embalaje" => "Caja",
                                                "peso" => 10,
                                                "precio" => 100,
                                                "cuenta_a_terceros" => 0,
                                                "otros" => 0,
                                                "total" => 100
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        "responses" => [
                            "200" => [
                                "description" => "Procesamiento completado (revisar errors por ítem si hubo fallos parciales)",
                                "content" => [
                                    "application/json" => [
                                        "schema" => [
                                            "type" => "object",
                                            "properties" => [
                                                "success" => [
                                                    "type" => "boolean",
                                                    "example" => true
                                                ],
                                                "created" => [
                                                    "type" => "integer",
                                                    "description" => "Paquetes nuevos guardados",
                                                    "example" => 5
                                                ],
                                                "skipped" => [
                                                    "type" => "integer",
                                                    "description" => "Ítems omitidos (WR ya existente)",
                                                    "example" => 1
                                                ],
                                                "errors" => [
                                                    "type" => "array",
                                                    "items" => [
                                                        "type" => "object",
                                                        "properties" => [
                                                            "index" => [
                                                                "type" => "integer",
                                                                "description" => "Índice en el arreglo packages"
                                                            ],
                                                            "wr" => [
                                                                "type" => "string",
                                                                "nullable" => true
                                                            ],
                                                            "message" => ["type" => "string"]
                                                        ]
                                                    ]
                                                ],
                                                "items" => [
                                                    "type" => "array",
                                                    "description" => "Detalle por ítem procesado",
                                                    "items" => [
                                                        "type" => "object",
                                                        "properties" => [
                                                            "index" => ["type" => "integer"],
                                                            "wr" => ["type" => "string", "nullable" => true],
                                                            "status" => [
                                                                "type" => "string",
                                                                "enum" => ["created", "skipped"]
                                                            ],
                                                            "id" => [
                                                                "type" => "integer",
                                                                "nullable" => true,
                                                                "description" => "ID del paquete en SmartPYME"
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            "400" => [
                                "description" => "Solicitud inválida (ej. packages vacío o más de 500 ítems)",
                                "content" => [
                                    "application/json" => [
                                        "schema" => [
                                            "type" => "object",
                                            "properties" => [
                                                "success" => ["type" => "boolean", "example" => false],
                                                "error" => ["type" => "string", "example" => "Solicitud inválida"],
                                                "details" => ["type" => "object"]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            "401" => [
                                "description" => "No autorizado - API key inválido o empresa inactiva"
                            ],
                            "422" => [
                                "description" => "La empresa no tiene usuario activo para registrar paquetes",
                                "content" => [
                                    "application/json" => [
                                        "schema" => [
                                            "type" => "object",
                                            "properties" => [
                                                "success" => ["type" => "boolean", "example" => false],
                                                "error" => ["type" => "string"]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            "429" => [
                                "description" => "Rate limit excedido"
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return $spec;
    }
}
