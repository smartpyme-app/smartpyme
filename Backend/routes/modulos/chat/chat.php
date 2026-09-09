<?php 

use App\Http\Controllers\Api\Chat\ChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rutas para el chat con Bedrock
Route::post('/chat/bedrock', [ChatController::class, 'bedrockChat']);

// Proxy directo a Lucas (punto único de entrada para el chat web)
Route::post('/chat', [ChatController::class, 'chat']);

// Conversaciones en Lucas
Route::get('/chat/conversations', [ChatController::class, 'conversations']);
Route::post('/chat/conversations/new', [ChatController::class, 'startConversation']);
Route::get('/chat/conversations/{id}/messages', [ChatController::class, 'conversationMessages']);

// Ruta para iniciar una nueva conversación
Route::post('/chat/new', [ChatController::class, 'newConversation']);

// Ruta para obtener el historial de conversaciones
Route::get('/chat/history', [ChatController::class, 'getConversationHistory']);

// Ruta para obtener los mensajes de una conversación específica
Route::get('/chat/conversation/{id}', [ChatController::class, 'getConversation']);