<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Services\AIService;
use App\Services\ContextService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    protected $aiService;
    protected $contextService;

    /**
     * Constructor
     * 
     * @param AIService $aiService
     */
    public function __construct(AIService $aiService, ContextService $contextService)
    {
        $this->aiService = $aiService;
        $this->contextService = $contextService;
    }

    /**
     * Procesa las solicitudes de chat usando Bedrock
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bedrockChat(Request $request)
    {
        try {
            // Validar la solicitud
            $validated = $request->validate([
                'prompt' => 'required|string',
                'history' => 'nullable|array',
                'conversationId' => 'nullable|integer',
                'maxTokens' => 'nullable|integer|min:1|max:4000',
                'temperature' => 'nullable|numeric|min:0|max:1',
                'topP' => 'nullable|numeric|min:0|max:1',
                'topK' => 'nullable|integer|min:0',
            ]);

            // Obtener o verificar la conversación si existe
            $conversationId = $validated['conversationId'] ?? null;
            if ($conversationId) {
                $conversation = Conversation::findOrFail($conversationId);
            }

            // Configurar el tipo de modelo si se especifica
            if (isset($validated['modelType'])) {
                $this->aiService->useModel($validated['modelType']);
            }

            // Opciones adicionales para la generación
            $options = array_filter([
                'maxTokens' => $validated['maxTokens'] ?? null,
                'temperature' => $validated['temperature'] ?? null,
                'topP' => $validated['topP'] ?? null,
                'topK' => $validated['topK'] ?? null
            ]);


            $empresaId = $request->user()->id_empresa ?? null;
            if ($empresaId) {
                session(['id_empresa' => $empresaId]);
            }
            
            $empresa = null;
            $metricas = null;
            
            if ($empresaId) {
                $empresa = $this->contextService->obtenerInformacionEmpresa($empresaId);
                $metricas = $this->contextService->obtenerMetricasRecientes($empresaId);
            }

            $basePrompt = config('bedrock.system_prompt_haiku');
            $systemPrompt = $this->contextService->generateSystemPrompt($empresa, $metricas, $basePrompt);
            $systemPrompt = $this->contextService->enrichContextWithQueryData($systemPrompt, $empresa, $validated['prompt']);
            
            $this->aiService->setSystemPrompt($systemPrompt);

            $botResponse = $this->aiService->generateResponse(
                $validated['prompt'],
                $validated['history'] ?? [],
                $options
            );

            // Si tenemos una conversación, guardar mensajes
            if ($conversationId && isset($conversation)) {
                // Guardar mensaje del usuario
                $userMessage = new Message([
                    'conversation_id' => $conversationId,
                    'sender' => 'user',
                    'content' => $validated['prompt'],
                    'metadata' => []
                ]);
                $userMessage->save();

                // Guardar respuesta del bot
                $botMessage = new Message([
                    'conversation_id' => $conversationId,
                    'sender' => 'bot',
                    'content' => $botResponse,
                    'metadata' => []
                ]);
                $botMessage->save();
            }

            // Devolver respuesta
            return response()->json([
                'message' => $botResponse,
                'conversationId' => $conversationId ?? null,
                'modelUsed' => config('bedrock.model_id_haiku')
            ]);
        } catch (\Exception $e) {
            Log::error('Error en procesamiento de chat:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al procesar la solicitud',
                'message' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    private function generateSystemPrompt($empresa, $metricas)
    {
        $basePrompt = config('bedrock.system_prompt_haiku');

        if (!$empresa) {
            return $basePrompt;
        }

        $contextInfo = "Información sobre la empresa:
                        Nombre: {$empresa->nombre}
                        Industria: {$empresa->industria}
                        ";

        if ($metricas && count($metricas) > 0) {
            $contextInfo .= "\nMétricas de la empresa:\n";

            foreach ($metricas as $index => $metrica) {
                $fecha = Carbon::parse($metrica->fecha)->format('Y-m');
                $contextInfo .= "- Período {$fecha}:\n";
                $contextInfo .= "  * Ventas: $" . number_format($metrica->ventas_con_iva, 2) . "\n";
                $contextInfo .= "  * Egresos: $" . number_format($metrica->egresos_con_iva, 2) . "\n";
                $contextInfo .= "  * Rentabilidad: " . number_format($metrica->rentabilidad_porcentaje, 2) . "%\n";

                // Limitar a los últimos 3 meses para no sobrecargar el prompt
                if ($index >= 2) break;
            }
        }

        // Añadir instrucciones específicas para el asistente
        $customPrompt = $basePrompt . "\n\nTienes acceso a la siguiente información contextual sobre la empresa del usuario. Utiliza esta información para proporcionar respuestas más precisas y personalizadas sobre su situación financiera:\n\n" . $contextInfo;

        // Añadir instrucción para no revelar directamente los datos a menos que se soliciten
        $customPrompt .= "\n\nNo menciones explícitamente que tienes esta información a menos que el usuario la solicite. Usa estos datos para contextualizar tus respuestas y dar mejores consejos financieros.";

        return $customPrompt;
    }

    /**
     * Crea una nueva conversación
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function newConversation(Request $request)
    {
        try {
            // Validar la solicitud
            $validated = $request->validate([
                'title' => 'nullable|string|max:255',
            ]);

            // Generar un título si no se proporcionó uno
            $title = $validated['title'] ?? 'Nueva conversación - ' . now()->format('d/m/Y H:i');

            // Crear registro de conversación en la base de datos
            $conversation = new Conversation();
            $conversation->title = $title;
            $conversation->user_id = $request->user()->id ?? null; // Si tienes autenticación
            $conversation->created_at = now();
            $conversation->save();

            // Guardar mensaje inicial del bot si lo deseas
            $welcomeMessage = new Message([
                'conversation_id' => $conversation->id,
                'sender' => 'bot',
                'content' => '¡Hola! ¿En qué puedo ayudarte hoy?',
                'metadata' => []
            ]);
            $welcomeMessage->save();

            return response()->json([
                'id' => $conversation->id,
                'title' => $conversation->title,
                'created_at' => $conversation->created_at,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al crear nueva conversación:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al crear la conversación',
                'message' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtiene el historial de conversaciones
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConversationHistory(Request $request)
    {
        try {
            // Si tienes autenticación, filtrar por usuario
            $userId = $request->user()->id ?? null;

            $conversations = Conversation::when($userId, function ($query, $userId) {
                return $query->where('user_id', $userId);
            })
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json($conversations);
        } catch (\Exception $e) {
            Log::error('Error al obtener historial de conversaciones:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al obtener el historial',
                'message' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtiene los mensajes de una conversación específica
     *
     * @param Request $request
     * @param int $id ID de la conversación
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConversation(Request $request, $id)
    {
        try {
            $conversation = Conversation::findOrFail($id);

            // Verificar permisos si es necesario
            if ($request->user() && $conversation->user_id !== $request->user()->id) {
                // Verificar si el usuario tiene permiso para ver esta conversación
                // ...
            }

            // Cargar los mensajes
            $messages = $conversation->messages()
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'conversation' => $conversation,
                'messages' => $messages
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Conversación no encontrada'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al obtener conversación:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'conversation_id' => $id
            ]);

            return response()->json([
                'error' => 'Error al obtener la conversación',
                'message' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    public function adminDashboard(Request $request)
    {
        // Estadísticas de uso del chat
        $stats = [
            'total_conversations' => Conversation::count(),
            'total_messages' => Message::count(),
            'active_today' => Conversation::whereDate('created_at', today())->count(),
            // Más estadísticas según necesites
        ];

        return view('chat.admin', compact('stats'));
    }

    /**
     * Vista para historial de conversaciones
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function viewHistory(Request $request)
    {
        $conversations = Conversation::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('chat.history', compact('conversations'));
    }

    /**
     * Vista para una conversación específica
     *
     * @param Request $request
     * @param int $id ID de la conversación
     * @return \Illuminate\View\View
     */
    public function viewConversation(Request $request, $id)
    {
        $conversation = Conversation::findOrFail($id);

        // Verificar permisos
        if ($conversation->user_id !== $request->user()->id) {
            abort(403, 'No tienes permiso para ver esta conversación');
        }

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get();

        return view('chat.view', compact('conversation', 'messages'));
    }
}
