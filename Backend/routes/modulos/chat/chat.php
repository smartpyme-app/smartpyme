<?php 

use App\Http\Controllers\Api\Chat\ChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rutas para el chat con Bedrock
Route::post('/chat/bedrock', [ChatController::class, 'bedrockChat']);

// Ruta para iniciar una nueva conversación
Route::post('/chat/new', [ChatController::class, 'newConversation']);

// Ruta para obtener el historial de conversaciones
Route::get('/chat/history', [ChatController::class, 'getConversationHistory']);

// Ruta para obtener los mensajes de una conversación específica
Route::get('/chat/conversation/{id}', [ChatController::class, 'getConversation']);