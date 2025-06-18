<?php

namespace App\Http\Controllers\Api\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\WhatsApp\WhatsAppMessage;
use App\Models\WhatsApp\WhatsAppSession;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Http\JsonResponse;

class WebhookController extends Controller
{
    protected $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    public function verify(Request $request)
    {
        $mode = $request->get('hub_mode');
        $token = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

        $verifyToken = config('services.whatsapp.verify_token', 'smartpyme_verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('WhatsApp webhook verificado correctamente');
            return response($challenge, 200);
        }

        Log::warning('Falló la verificación del webhook WhatsApp', [
            'mode' => $mode,
            'token' => $token
        ]);

        return response('Forbidden', 403);
    }

    public function handle(Request $request)
    {
        Log::info('Manejando webhook de WhatsApp', ['payload' => $request->all()]);

        try {
           // if (config('app.env') === 'production') {
             if (config('services.whatsapp.use_whatsapp_business', false)) {
                Log::info('WhatsApp webhook recibido', ['payload' => $request->all()]);
            } else {
                Log::info('📱 WhatsApp webhook recibido [DEV]', [
                    'from' => $request->input('entry.0.changes.0.value.messages.0.from'),
                    'message' => $request->input('entry.0.changes.0.value.messages.0.text.body'),
                    'timestamp' => $request->input('entry.0.changes.0.value.messages.0.timestamp')
                ]);
            }

            if (!$this->isValidWebhook($request)) {
                Log::warning('Webhook inválido recibido');
                return response()->json(['status' => 'invalid_webhook'], 400);
            }
            $result = $this->whatsAppService->processIncomingMessage($request->all());

            if ($result['success']) {
                Log::info('✅ Mensaje procesado exitosamente', [
                    'success' => $result['success'],
                    'has_response' => isset($result['response'])
                ]);
            } else {
                Log::error('❌ Error procesando mensaje', [
                    'error' => $result['error'] ?? 'Error desconocido'
                ]);
            }

            return response()->json(['status' => 'received'], 200);
        } catch (Exception $e) {
            Log::error('Error en webhook WhatsApp', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['status' => 'error'], 200);
        }
    }

    private function isValidWebhook(Request $request): bool
    {
        $data = $request->all();

        return isset($data['entry'])
            && is_array($data['entry'])
            && count($data['entry']) > 0
            && isset($data['entry'][0]['changes'])
            && is_array($data['entry'][0]['changes'])
            && count($data['entry'][0]['changes']) > 0;
    }

    public function getStats(Request $request)
    {
        try {
            $days = $request->get('days', 30);
            $empresaId = $request->get('empresa_id');

            $stats = [
                'sessions' => [
                    'total' => WhatsAppSession::count(),
                    'active' => WhatsAppSession::where('last_message_at', '>=', now()->subHours(24))->count(),
                    'connected' => WhatsAppSession::where('status', 'connected')->count(),
                    'pending' => WhatsAppSession::whereIn('status', ['pending_code', 'pending_user'])->count(),
                ],
                'messages' => [
                    'total' => WhatsAppMessage::where('created_at', '>=', now()->subDays($days))->count(),
                    'incoming' => WhatsAppMessage::where('message_type', 'incoming')
                        ->where('created_at', '>=', now()->subDays($days))->count(),
                    'outgoing' => WhatsAppMessage::where('message_type', 'outgoing')
                        ->where('created_at', '>=', now()->subDays($days))->count(),
                    'ai_generated' => WhatsAppMessage::whereJsonContains('metadata->ai_generated', true)
                        ->where('created_at', '>=', now()->subDays($days))->count(),
                ],
                'companies' => [
                    'total_with_sessions' => WhatsAppSession::distinct('id_empresa')->count(),
                    'active_today' => WhatsAppSession::where('last_message_at', '>=', now()->startOfDay())
                        ->distinct('id_empresa')->count(),
                ]
            ];

            if ($empresaId) {
                $stats['empresa_specific'] = $this->getEmpresaStats($empresaId, $days);
            }

            $stats['daily_activity'] = WhatsAppMessage::selectRaw('DATE(created_at) as date, COUNT(*) as messages')
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('date')
                ->orderBy('date')
                ->get();


            $stats['top_companies'] = WhatsAppSession::join('empresas', 'whatsapp_sessions.id_empresa', '=', 'empresas.id')
                ->selectRaw('empresas.nombre, empresas.id, COUNT(whatsapp_sessions.id) as session_count')
                ->where('whatsapp_sessions.created_at', '>=', now()->subDays($days))
                ->groupBy('empresas.id', 'empresas.nombre')
                ->orderByDesc('session_count')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $stats,
                'period_days' => $days,
                'generated_at' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo estadísticas WhatsApp', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo estadísticas',
                'message' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }


    public function getSessions(Request $request)
    {
        try {
           
            $perPage = $request->get('per_page', 15);
            $paginate = $request->get('paginate', $perPage); 
            $page = $request->get('page', 1);

            $status = $request->get('status');
            $empresaId = $request->get('empresa_id') ?: $request->get('id_empresa'); 
            $usuarioId = $request->get('id_usuario');
            $search = $request->get('search') ?: $request->get('buscador'); 
            $whatsappNumber = $request->get('whatsapp_number');

            $fechaInicio = $request->get('inicio');
            $fechaFin = $request->get('fin');

            $conMensajes = $request->get('con_mensajes');
            $sesionActiva = $request->get('activa');

            $orden = $request->get('orden', 'created_at');
            $direccion = $request->get('direccion', 'desc');


            $query = WhatsAppSession::with(['empresa', 'usuario']);

            $validOrders = ['created_at', 'last_message_at', 'message_count', 'whatsapp_number', 'status'];
            if (in_array($orden, $validOrders)) {
                if ($orden === 'message_count') {
                    $query->withCount(['messages as message_count']);
                    $query->orderBy('message_count', $direccion);
                } else {
                    $query->orderBy($orden, $direccion);
                }
            } else {
                $query->orderByDesc('last_message_at');
            }

 
            if ($status) {
                $query->where('status', $status);
            }

       

            if ($usuarioId) {
                $query->where('id_usuario', $usuarioId);
            }

            if ($whatsappNumber) {
                $query->where('whatsapp_number', 'like', "%{$whatsappNumber}%");
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('whatsapp_number', 'like', "%{$search}%")
                        ->orWhereHas('empresa', function ($eq) use ($search) {
                            $eq->where('nombre', 'like', "%{$search}%")
                                ->orWhere('codigo', 'like', "%{$search}%");
                        })
                        ->orWhereHas('usuario', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }


            if ($fechaInicio) {
                $query->whereDate('created_at', '>=', $fechaInicio);
            }

            if ($fechaFin) {
                $query->whereDate('created_at', '<=', $fechaFin);
            }

            $itemsPerPage = $paginate ?: $perPage;
            $sessions = $query->paginate($itemsPerPage, ['*'], 'page', $page);

      
            $sessions->getCollection()->transform(function ($session) {
                $messageCount = $session->message_count ??
                    WhatsAppMessage::where('whatsapp_number', $session->whatsapp_number)->count();

                return [
                    'id' => $session->id,
                    'whatsapp_number' => $session->whatsapp_number,
                    'status' => $session->status,
                    'empresa' => $session->empresa ? [
                        'id' => $session->empresa->id,
                        'nombre' => $session->empresa->nombre,
                        'codigo' => $session->empresa->codigo ?? null
                    ] : null,
                    'usuario' => $session->usuario ? [
                        'id' => $session->usuario->id,
                        'name' => $session->usuario->name,
                        'email' => $session->usuario->email,
                        'tipo' => $session->usuario->tipo ?? null
                    ] : null,
                    'created_at' => $session->created_at,
                    'last_message_at' => $session->last_message_at,
                    'code_attempts' => $session->code_attempts ?? 0,
                    'user_attempts' => $session->user_attempts ?? 0,
                    'is_active' => $session->last_message_at && $session->last_message_at >= now()->subHours(24),
                    'message_count' => $messageCount,
                    'days_since_created' => $session->created_at ? $session->created_at->diffInDays(now()) : 0,
                    'hours_since_last_activity' => $session->last_message_at ?
                        $session->last_message_at->diffInHours(now()) : null,
                    'status_label' => $this->getStatusLabel($session->status),
                    'can_disconnect' => $session->status === 'connected',
                    'can_unblock' => $session->status === 'blocked'
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $sessions,
                'filters_applied' => array_filter([
                    'status' => $status,
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'search' => $search,
                    'whatsapp_number' => $whatsappNumber,
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaFin,
                    'con_mensajes' => $conMensajes,
                    'sesion_activa' => $sesionActiva,
                    'orden' => $orden,
                    'direccion' => $direccion
                ]),
                'pagination_info' => [
                    'current_page' => $sessions->currentPage(),
                    'per_page' => $sessions->perPage(),
                    'total' => $sessions->total(),
                    'last_page' => $sessions->lastPage(),
                    'from' => $sessions->firstItem(),
                    'to' => $sessions->lastItem()
                ],
                'summary' => [
                    'total_filtered' => $sessions->total(),
                    'connected' => $sessions->getCollection()->where('status', 'connected')->count(),
                    'active_24h' => $sessions->getCollection()->where('is_active', true)->count(),
                    'with_messages' => $sessions->getCollection()->where('message_count', '>', 0)->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo sesiones WhatsApp', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo sesiones',
                'message' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    private function getStatusLabel(string $status): string
    {
        $labels = [
            'connected' => 'Conectado',
            'pending_code' => 'Esperando código',
            'pending_user' => 'Esperando usuario',
            'pending_verification' => 'Esperando verificación',
            'blocked' => 'Bloqueado',
            'disconnected' => 'Desconectado'
        ];

        return $labels[$status] ?? ucfirst($status);
    }

    private function getEmpresaStats(int $empresaId, int $days): array
    {
        return [
            'sessions' => [
                'total' => WhatsAppSession::where('id_empresa', $empresaId)->count(),
                'active' => WhatsAppSession::where('id_empresa', $empresaId)
                    ->where('last_message_at', '>=', now()->subHours(24))->count(),
                'connected' => WhatsAppSession::where('id_empresa', $empresaId)
                    ->where('status', 'connected')->count(),
            ],
            'messages' => [
                'total' => WhatsAppMessage::where('id_empresa', $empresaId)
                    ->where('created_at', '>=', now()->subDays($days))->count(),
                'incoming' => WhatsAppMessage::where('id_empresa', $empresaId)
                    ->where('message_type', 'incoming')
                    ->where('created_at', '>=', now()->subDays($days))->count(),
                'outgoing' => WhatsAppMessage::where('id_empresa', $empresaId)
                    ->where('message_type', 'outgoing')
                    ->where('created_at', '>=', now()->subDays($days))->count(),
                'ai_generated' => WhatsAppMessage::where('id_empresa', $empresaId)
                    ->whereJsonContains('metadata->ai_generated', true)
                    ->where('created_at', '>=', now()->subDays($days))->count(),
            ],
            'users' => [
                'unique_users' => WhatsAppSession::where('id_empresa', $empresaId)
                    ->whereNotNull('id_usuario')
                    ->distinct('id_usuario')->count(),
                'unique_numbers' => WhatsAppSession::where('id_empresa', $empresaId)
                    ->distinct('whatsapp_number')->count(),
            ]
        ];
    }






    public function disconnectSession(int $sessionId): JsonResponse
    {
        try {
            $session = WhatsAppSession::findOrFail($sessionId);


            $session->update([
                'status' => 'disconnected',
                'disconnected_at' => now(),
                'disconnected_by' => auth()->id()
            ]);


            $this->whatsAppService->disconnectSession($session->whatsapp_number);

            Log::info('Sesión WhatsApp desconectada', [
                'session_id' => $sessionId,
                'whatsapp_number' => $session->whatsapp_number,
                'disconnected_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sesión desconectada correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error desconectando sesión WhatsApp', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al desconectar sesión'
            ], 500);
        }
    }



    public function getSessionMessages(int $sessionId, Request $request): JsonResponse
    {
        try {
            $session = WhatsAppSession::findOrFail($sessionId);
            $perPage = $request->get('per_page', 20);

            $messages = WhatsAppMessage::where('whatsapp_number', $session->whatsapp_number)
                ->orderByDesc('created_at')
                ->paginate($perPage);

            $messages->getCollection()->transform(function ($message) {
                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'message_type' => $message->message_type,
                    'status' => $message->status,
                    'created_at' => $message->created_at,
                    'metadata' => $message->metadata,
                    'is_ai_generated' => $message->metadata['ai_generated'] ?? false,
                    'sent_by' => $message->metadata['sent_by_user'] ?? null
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $messages,
                'session' => [
                    'id' => $session->id,
                    'whatsapp_number' => $session->whatsapp_number,
                    'status' => $session->status,
                    'empresa' => $session->empresa->nombre
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo mensajes de sesión', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo mensajes'
            ], 500);
        }
    }


    public function getExecutiveSummary(Request $request): JsonResponse
    {
        try {
            $days = $request->get('days', 7);
            $empresaId = $request->get('empresa_id');


            $baseQuery = $empresaId ? ['id_empresa' => $empresaId] : [];

            $summary = [
                'period' => $days,
                'overview' => [
                    'total_sessions' => WhatsAppSession::when($empresaId, function ($query) use ($empresaId) {
                        return $query->where('id_empresa', $empresaId);
                    })->count(),

                    'active_sessions_today' => WhatsAppSession::when($empresaId, function ($query) use ($empresaId) {
                        return $query->where('id_empresa', $empresaId);
                    })->where('last_message_at', '>=', now()->startOfDay())->count(),

                    'messages_today' => WhatsAppMessage::when($empresaId, function ($query) use ($empresaId) {
                        return $query->where('id_empresa', $empresaId);
                    })->whereDate('created_at', today())->count(),

                    'companies_using' => $empresaId ? 1 : WhatsAppSession::distinct('id_empresa')->count()
                ],
                'trends' => [
                    'messages_growth' => $this->calculateGrowthRate('messages', $days, $empresaId),
                    'sessions_growth' => $this->calculateGrowthRate('sessions', $days, $empresaId),
                    'engagement_rate' => $this->calculateEngagementRate($days, $empresaId)
                ],
                'top_metrics' => [
                    'busiest_hour' => $this->getBusiestHour($days, $empresaId),
                    'most_active_company' => $empresaId ? null : $this->getMostActiveCompany($days),
                    'avg_response_time' => $this->getAverageResponseTime($days, $empresaId),
                    'peak_day' => $this->getPeakDay($days, $empresaId)
                ],
                'insights' => $this->generateInsights($days, $empresaId),
                'recommendations' => $this->generateRecommendations($days, $empresaId)
            ];

            return response()->json([
                'success' => true,
                'data' => $summary,
                'generated_at' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Error generando resumen ejecutivo WhatsApp', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error generando resumen ejecutivo',
                'message' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }



    private function calculateGrowthRate(string $type, int $days, ?int $empresaId = null): float
    {
        $baseQuery = function ($query) use ($empresaId) {
            if ($empresaId) {
                return $query->where('id_empresa', $empresaId);
            }
            return $query;
        };

        if ($type === 'messages') {
            $currentPeriod = WhatsAppMessage::where($baseQuery)
                ->where('created_at', '>=', now()->subDays($days))->count();

            $previousPeriod = WhatsAppMessage::where($baseQuery)
                ->whereBetween('created_at', [now()->subDays($days * 2), now()->subDays($days)])->count();
        } else {
            $currentPeriod = WhatsAppSession::where($baseQuery)
                ->where('created_at', '>=', now()->subDays($days))->count();

            $previousPeriod = WhatsAppSession::where($baseQuery)
                ->whereBetween('created_at', [now()->subDays($days * 2), now()->subDays($days)])->count();
        }

        if ($previousPeriod === 0) return $currentPeriod > 0 ? 100 : 0;
        return round((($currentPeriod - $previousPeriod) / $previousPeriod) * 100, 2);
    }

    private function calculateEngagementRate(int $days, ?int $empresaId = null): float
    {
        $query = WhatsAppSession::when($empresaId, function ($q) use ($empresaId) {
            return $q->where('id_empresa', $empresaId);
        });

        $totalSessions = $query->where('created_at', '>=', now()->subDays($days))->count();
        $activeSessions = $query->where('last_message_at', '>=', now()->subDays($days))->count();

        if ($totalSessions === 0) return 0;
        return round(($activeSessions / $totalSessions) * 100, 2);
    }

    private function getBusiestHour(int $days, ?int $empresaId = null): int
    {
        $messages = WhatsAppMessage::when($empresaId, function ($query) use ($empresaId) {
            return $query->where('id_empresa', $empresaId);
        })
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();

        return $messages->hour ?? 12;
    }

    private function getMostActiveCompany(int $days): ?array
    {
        $company = WhatsAppSession::join('empresas', 'whatsapp_sessions.id_empresa', '=', 'empresas.id')
            ->where('whatsapp_sessions.created_at', '>=', now()->subDays($days))
            ->selectRaw('empresas.nombre, empresas.id, COUNT(whatsapp_sessions.id) as session_count')
            ->groupBy('empresas.id', 'empresas.nombre')
            ->orderByDesc('session_count')
            ->first();

        return $company ? [
            'id' => $company->id,
            'nombre' => $company->nombre,
            'session_count' => $company->session_count
        ] : null;
    }

    private function getAverageResponseTime(int $days, ?int $empresaId = null): float
    {
        try {
            $conversations = WhatsAppMessage::when($empresaId, function ($query) use ($empresaId) {
                return $query->where('id_empresa', $empresaId);
            })
                ->where('created_at', '>=', now()->subDays($days))
                ->orderBy('whatsapp_number')
                ->orderBy('created_at')
                ->get(['whatsapp_number', 'message_type', 'created_at']);

            $responseTimes = [];
            $currentConversation = null;
            $lastIncomingTime = null;

            foreach ($conversations as $message) {
                if ($currentConversation !== $message->whatsapp_number) {
                    $currentConversation = $message->whatsapp_number;
                    $lastIncomingTime = null;
                }

                if ($message->message_type === 'incoming') {
                    $lastIncomingTime = $message->created_at;
                } elseif ($message->message_type === 'outgoing' && $lastIncomingTime) {
                    $diffMinutes = $lastIncomingTime->diffInMinutes($message->created_at);
                    if ($diffMinutes <= 120) { // Solo respuestas dentro de 2 horas
                        $responseTimes[] = $diffMinutes;
                    }
                    $lastIncomingTime = null;
                }
            }

            return count($responseTimes) > 0 ? round(array_sum($responseTimes) / count($responseTimes), 1) : 0;
        } catch (\Exception $e) {
            Log::warning('Error calculando tiempo de respuesta promedio', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    private function getPeakDay(int $days, ?int $empresaId = null): ?string
    {
        $peakDay = WhatsAppMessage::when($empresaId, function ($query) use ($empresaId) {
            return $query->where('id_empresa', $empresaId);
        })
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as message_count')
            ->groupBy('date')
            ->orderByDesc('message_count')
            ->first();

        return $peakDay->date;
    }

    private function generateInsights(int $days, ?int $empresaId = null): array
    {
        $insights = [];

        try {
     
            $busiestHour = $this->getBusiestHour($days, $empresaId);
            if ($busiestHour >= 9 && $busiestHour <= 17) {
                $insights[] = [
                    'type' => 'info',
                    'message' => 'La mayor actividad ocurre durante horario laboral (' . $busiestHour . ':00)',
                    'icon' => 'fas fa-clock'
                ];
            } else {
                $insights[] = [
                    'type' => 'warning',
                    'message' => 'Hay actividad significativa fuera del horario laboral (' . $busiestHour . ':00)',
                    'icon' => 'fas fa-moon'
                ];
            }

            $messageGrowth = $this->calculateGrowthRate('messages', $days, $empresaId);
            if ($messageGrowth > 20) {
                $insights[] = [
                    'type' => 'success',
                    'message' => 'Excelente crecimiento en mensajes: +' . $messageGrowth . '%',
                    'icon' => 'fas fa-arrow-up'
                ];
            } elseif ($messageGrowth < -10) {
                $insights[] = [
                    'type' => 'warning',
                    'message' => 'Disminución en actividad de mensajes: ' . $messageGrowth . '%',
                    'icon' => 'fas fa-arrow-down'
                ];
            }

            $engagementRate = $this->calculateEngagementRate($days, $empresaId);
            if ($engagementRate > 80) {
                $insights[] = [
                    'type' => 'success',
                    'message' => 'Excelente tasa de engagement: ' . $engagementRate . '%',
                    'icon' => 'fas fa-heart'
                ];
            } elseif ($engagementRate < 50) {
                $insights[] = [
                    'type' => 'warning',
                    'message' => 'La tasa de engagement puede mejorarse: ' . $engagementRate . '%',
                    'icon' => 'fas fa-chart-line'
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Error generando insights', ['error' => $e->getMessage()]);
        }

        return $insights;
    }

    private function generateRecommendations(int $days, ?int $empresaId = null): array
    {
        $recommendations = [];

        try {
            $avgResponseTime = $this->getAverageResponseTime($days, $empresaId);
            if ($avgResponseTime > 30) {
                $recommendations[] = [
                    'priority' => 'high',
                    'message' => 'Mejorar tiempo de respuesta (actual: ' . $avgResponseTime . ' min)',
                    'action' => 'Considerar implementar respuestas automáticas o aumentar personal'
                ];
            }

            
            $busiestHour = $this->getBusiestHour($days, $empresaId);
            if ($busiestHour < 9 || $busiestHour > 17) {
                $recommendations[] = [
                    'priority' => 'medium',
                    'message' => 'Evaluar extender horarios de atención',
                    'action' => 'La mayor actividad ocurre a las ' . $busiestHour . ':00'
                ];
            }

            
            $engagementRate = $this->calculateEngagementRate($days, $empresaId);
            if ($engagementRate < 60) {
                $recommendations[] = [
                    'priority' => 'medium',
                    'message' => 'Implementar estrategias para aumentar engagement',
                    'action' => 'Crear contenido más interactivo y personalizado'
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Error generando recomendaciones', ['error' => $e->getMessage()]);
        }

        return $recommendations;
    }

    public function connectSession(int $sessionId): JsonResponse
    {
        try {
            $session = WhatsAppSession::findOrFail($sessionId);

            $this->whatsAppService->connectSession($session->whatsapp_number);

            Log::info('Sesión WhatsApp conectada', [
                'sessionId' => $sessionId,
                'whatsapp_number' => $session->whatsapp_number
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sesión conectada correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error conectando sesión WhatsApp', [
                'sessionId' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al conectar sesión'
            ], 500);
        }
    }

    public function deleteSession(int $sessionId): JsonResponse
    {
        try {
            $session = WhatsAppSession::findOrFail($sessionId);

            $session->delete();

            Log::info('Sesión WhatsApp eliminada', [
                'sessionId' => $sessionId,
                'whatsapp_number' => $session->whatsapp_number
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sesión eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error eliminando sesión WhatsApp', [
                'sessionId' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar sesión'
            ], 500);
        }
    }
}
