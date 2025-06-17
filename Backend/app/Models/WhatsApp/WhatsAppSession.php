<?php

namespace App\Models\WhatsApp;

use Illuminate\Database\Eloquent\Model;
use App\Models\Admin\Empresa;
use App\Models\User;

class WhatsAppSession extends Model
{
    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'whatsapp_number',
        'id_empresa',
        'id_usuario',
        'status',
        'code_attempts',
        'user_attempts',
        'last_message_at',
        'session_data',
        'disconnected_by',
        'disconnected_at'
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'session_data' => 'array',
        'disconnected_at' => 'datetime'
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

    public function messages()
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_number', 'whatsapp_number');
    }

    // Métodos principales
    public static function findOrCreateByNumber($whatsappNumber)
    {
        return self::firstOrCreate(
            ['whatsapp_number' => $whatsappNumber],
            [
                'status' => 'pending_code',
                'last_message_at' => now()
            ]
        );
    }

    public function connectToCompany($companyCode)
    {
        $empresa = Empresa::where('codigo', $companyCode)->first();

        if ($empresa) {
            $this->update([
                'id_empresa' => $empresa->id,
                'status' => 'pending_user',
                'last_message_at' => now(),
                'code_attempts' => 0
            ]);
            return $empresa;
        }

        $this->increment('code_attempts');
        $this->touch('last_message_at');
        return false;
    }

    public function connectToUser($email)
    {
        if (!$this->id_empresa) {
            return false;
        }

        $usuario = User::where('email', $email)
                      ->where('id_empresa', $this->id_empresa)
                      ->where('enable', true)
                      ->first();

        if ($usuario) {
            $this->update([
                'id_usuario' => $usuario->id,
                'status' => 'connected',
                'last_message_at' => now(),
                'user_attempts' => 0
            ]);
            return $usuario;
        }

        $this->increment('user_attempts');
        $this->touch('last_message_at');
        return false;
    }

    public function resetConnection()
    {
        $this->update([
            'id_empresa' => null,
            'id_usuario' => null,
            'status' => 'pending_code',
            'code_attempts' => 0,
            'user_attempts' => 0,
            'session_data' => null,
            'last_message_at' => now()
        ]);
    }

    public function updateSessionData($key, $value)
    {
        $sessionData = $this->session_data ?? [];
        $sessionData[$key] = $value;
        
        $this->update([
            'session_data' => $sessionData,
            'last_message_at' => now()
        ]);
    }

    public function getSessionData($key, $default = null)
    {
        return $this->session_data[$key] ?? $default;
    }

    // Verificadores de estado
    public function isPendingCode()
    {
        return $this->status === 'pending_code';
    }

    public function isPendingUser()
    {
        return $this->status === 'pending_user';
    }

    public function isConnected()
    {
        return $this->status === 'connected';
    }

    public function shouldBlockForTooManyAttempts()
    {
        return $this->code_attempts >= 5 || $this->user_attempts >= 5;
    }
}