<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Aws\Bedrock\BedrockClient;
use Aws\Credentials\CredentialProvider;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Credentials\Credentials;
use Aws\Exception\AwsException;

class ChatController extends Controller
{
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

            // Obtener o crear la conversación (placeholder para integración de BD)
            $conversationId = $validated['conversationId'] ?? null;
            $inferenceProfileArn = config('services.bedrock.inference_profile_arn');


            // Crear cliente de BedrockRuntime
            $client = new BedrockRuntimeClient([
                'version' => 'latest',
                'region' => config('services.bedrock.region', 'us-east-2'),
                'credentials' => [
                    'key'    => config('services.bedrock.key'),
                    'secret' => config('services.bedrock.secret'),
                ],
            ]);

            Log::debug('Usando región de Bedrock:', ['region' => $client->getRegion()]);

            // Obtener el modelo desde la configuración
            $modelId = config('services.bedrock.model_id', 'anthropic.claude-3-5-haiku-20241022-v1:0');
            
            // Preparar mensajes en el formato correcto para Claude 3.5
            $formattedMessages = [];
            
            // Si hay historial, procesarlo
            if (!empty($validated['history'])) {
                foreach ($validated['history'] as $message) {
                    $formattedMessages[] = [
                        'role' => $message['role'],
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $message['content']
                            ]
                        ]
                    ];
                }
            }
            
            // Añadir el mensaje actual del usuario
            $formattedMessages[] = [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $validated['prompt']
                    ]
                ]
            ];
            
            // Configurar los parámetros de generación
            $maxTokens = $validated['maxTokens'] ?? config('services.bedrock.max_tokens', 500);
            $temperature = $validated['temperature'] ?? config('services.bedrock.temperature', 0.7);
            $topP = $validated['topP'] ?? config('services.bedrock.top_p', 0.9);
            $topK = $validated['topK'] ?? config('services.bedrock.top_k', 250);
            
            // Crear cuerpo de la solicitud para Claude 3.5
            $requestBody = [
                'anthropic_version' => 'bedrock-2023-05-31',
                'max_tokens' => (int)$maxTokens,         // Convertir a entero
                'messages' => $formattedMessages,
                'temperature' => (float)$temperature,     // Convertir a decimal
                'top_p' => (float)$topP,                 // Convertir a decimal
                'top_k' => (int)$topK,                   // Convertir a entero
                'system' => config('services.bedrock.system_prompt')
            ];
            
            // Para debug, guardar la solicitud completa
            Log::debug('Solicitud a Bedrock:', [
                'modelId' => $modelId,
                'body' => $requestBody
            ]);

            // Invocar al modelo
            $response = $client->invokeModel([
                'body' => json_encode($requestBody),
                'contentType' => 'application/json',
                'accept' => 'application/json',
                'modelId' => $inferenceProfileArn, // Usa el ARN del perfil de inferencia aquí
            ]);

            // Procesar la respuesta
            $result = json_decode($response->get('body')->getContents(), true);
            Log::debug('Respuesta de Bedrock:', ['result' => $result]);
            
            // Extraer el texto de la respuesta de Claude 3.5
            $botResponse = '';
            
            if (isset($result['content']) && is_array($result['content'])) {
                foreach ($result['content'] as $content) {
                    if ($content['type'] === 'text') {
                        $botResponse .= $content['text'];
                    }
                }
            }
            
            if (empty($botResponse) && isset($result['error'])) {
                throw new \Exception('Error en la respuesta del modelo: ' . $result['error']);
            }
            
            // Si hay un error al extraer el texto, mostrar toda la respuesta para debug
            if (empty($botResponse)) {
                Log::warning('No se pudo extraer texto de la respuesta:', ['response' => $result]);
                $botResponse = 'No se pudo obtener una respuesta clara. Por favor, intenta de nuevo.';
            }
            
            // Aquí puedes guardar los mensajes en la base de datos si lo necesitas
            
            return response()->json([
                'message' => $botResponse,
                'conversationId' => $conversationId ?? 1, // Placeholder para demo
                'modelUsed' => $modelId
            ]);
            
        } catch (AwsException $e) {
            // Registrar errores específicos de AWS
            Log::error('Error en AWS Bedrock:', [
                'message' => $e->getMessage(),
                'awsErrorType' => $e->getAwsErrorType(),
                'awsErrorCode' => $e->getAwsErrorCode(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error en el servicio de AWS Bedrock',
                'message' => config('app.debug') ? $e->getMessage() : 'Error al conectar con el servicio de IA'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error en Bedrock API:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error al procesar la solicitud',
                'message' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    public function newConversation(Request $request)
    {
        try {
            // Validar la solicitud
            $validated = $request->validate([
                'title' => 'nullable|string|max:255',
                'userId' => 'nullable|string', // Si tienes autenticación
            ]);

            // Generar un título si no se proporcionó uno
            $title = $validated['title'] ?? 'Nueva conversación - ' . now()->format('d/m/Y H:i');

            // Crear registro de conversación en la base de datos
            $conversation = new Conversation();
            $conversation->title = $title;
            $conversation->user_id = $request->user()->id ?? null; // Si tienes autenticación
            $conversation->created_at = now();
            $conversation->save();

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

    /**
     * Vista para administración del chat (opcional)
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
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
