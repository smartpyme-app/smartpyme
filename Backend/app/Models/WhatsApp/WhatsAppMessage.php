<?php

namespace App\Models\WhatsApp;

use Illuminate\Database\Eloquent\Model;
use App\Models\Admin\Empresa;
use App\Models\User;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'whatsapp_number',
        'id_empresa',
        'id_usuario',
        'message_type',
        'message_content',
        'is_bot_response',
        'metadata'
    ];

    protected $casts = [
        'is_bot_response' => 'boolean',
        'metadata' => 'array'
    ];

    // Relaciones
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function session()
    {
        return $this->belongsTo(WhatsAppSession::class, 'whatsapp_number', 'whatsapp_number');
    }

    // Métodos estáticos para crear mensajes
    public static function createIncoming($whatsappNumber, $content, $empresa = null, $usuario = null)
    {
        return self::create([
            'whatsapp_number' => $whatsappNumber,
            'id_empresa' => $empresa->id ?? null,
            'id_usuario' => $usuario->id ?? null,
            'message_type' => 'incoming',
            'message_content' => $content,
            'is_bot_response' => false
        ]);
    }

    public static function createOutgoing($whatsappNumber, $content, $empresa = null, $usuario = null, $metadata = null)
    {
        return self::create([
            'whatsapp_number' => $whatsappNumber,
            'id_empresa' => $empresa->id ?? null,
            'id_usuario' => $usuario->id ?? null,
            'message_type' => 'outgoing',
            'message_content' => $content,
            'is_bot_response' => true,
            'metadata' => $metadata
        ]);
    }

    // Scopes para consultas
    public function scopeIncoming($query)
    {
        return $query->where('message_type', 'incoming');
    }

    public function scopeOutgoing($query)
    {
        return $query->where('message_type', 'outgoing');
    }

    public function scopeBotResponses($query)
    {
        return $query->where('is_bot_response', true);
    }

    public function scopeByNumber($query, $whatsappNumber)
    {
        return $query->where('whatsapp_number', $whatsappNumber);
    }

    public function scopeByEmpresa($query, $empresaId)
    {
        return $query->where('id_empresa', $empresaId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    // Verificadores
    public function isIncoming()
    {
        return $this->message_type === 'incoming';
    }

    public function isOutgoing()
    {
        return $this->message_type === 'outgoing';
    }

    public function isBotResponse()
    {
        return $this->is_bot_response;
    }
}