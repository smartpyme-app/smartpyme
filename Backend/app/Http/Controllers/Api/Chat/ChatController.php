<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\User;
use App\Services\AIService;
use App\Services\ChatFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Chat\BedrockChatRequest;
use App\Http\Requests\Chat\NewConversationRequest;

class ChatController extends Controller
{
    protected $aiService;

    /**
     * Constructor
     * 
     * @param AIService $aiService
     */
    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Procesa las solicitudes de chat usando Bedrock
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bedrockChat(BedrockChatRequest $request, $source = 'Web')
    {
        try {
            // Datos ya validados por el FormRequest
            $validated = $request->validated();

            $user = User::findOrFail($validated['user_id'] ?? $request->user()->id);

            $empresaId = $request->user()->id_empresa ?? null;
            if ($empresaId) {
                session(['id_empresa' => $empresaId]);
            }

            // Identidad requerida por el servicio de IA (Lucas). Se resuelve del
            // lado servidor a partir del usuario autenticado (límite de confianza).
            $options = [
                'user_id' => $user->id,
                'empresa_id' => $user->id_empresa ?? $empresaId,
                'user_type' => $user->tipo ?? 'Usuario',
                'source' => $source,
            ];

            // El contexto lo gestiona Lucas con su propio conversation_id
            if (!empty($validated['conversationId'])) {
                $options['conversation_id'] = $validated['conversationId'];
            }

            $botResponse = $this->aiService->generateResponse(
                $validated['prompt'],
                $validated['history'] ?? [],
                $options
            );

            // Convertir el marcado estilo WhatsApp a HTML para la Web; para
            // WhatsApp se conserva el texto plano.
            $formatter = app(ChatFormatter::class);
            $botResponse = $formatter->format($botResponse, $source);

            $suggestions = $this->aiService->getSuggestions();
            $lastResponse = $this->aiService->getLastResponse();

            return response()->json([
                'message' => $botResponse,
                'suggestions' => $suggestions,
                'conversationId' => $lastResponse['conversation_id'] ?? null,
                'modelUsed' => $lastResponse['modelUsed'] ?? config('bedrock.model_id_haiku')
            ]);
        } catch (\Exception $e) {
            Log::error('Error en procesamiento de chat:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al procesar la solicitud',
                'message' => config('app.debug') ? $e->getMessage() : '<p>Error interno del servidor</p>'
            ], 500);
        }
    }

    /**
     * Crea una nueva conversación
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function newConversation(NewConversationRequest $request)
    {
        try {
            // Datos ya validados por el FormRequest
            $validated = $request->validated();

            // Generar un título si no se proporcionó uno
            $title = $validated['title'] ?? 'Nueva conversación - ' . now()->format('d/m/Y H:i');

            // Crear registro de conversación en la base de datos
            $conversation = new Conversation();
            $conversation->title = $title;
            $conversation->id_user = $request->user()->id ?? null; // Si tienes autenticación
            $conversation->id_empresa = $request->user()->id_empresa ?? null;
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
                return $query->where('id_user', $userId);
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
            if ($request->user() && $conversation->id_user !== $request->user()->id) {
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
        $conversations = Conversation::where('id_user', $request->user()->id)
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
        if ($conversation->id_user !== $request->user()->id) {
            abort(403, 'No tienes permiso para ver esta conversación');
        }

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get();

        return view('chat.view', compact('conversation', 'messages'));
    }
}
