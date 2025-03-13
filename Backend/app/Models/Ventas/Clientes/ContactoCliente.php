<?php

namespace App\Models\Ventas\Clientes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactoCliente extends Model
{


    protected $table = 'contactos_cliente';

    public $timestamps = true;

    protected $fillable = [
        'id_cliente',
        'nombre',
        'apellido',
        'correo',
        'telefono',
        'cargo',
        'sexo',
        'red_social',
        'fecha_nacimiento',
        'nota',
        'estado'
    ];

    protected $casts = [
        'id_cliente' => 'integer',
        'sexo' => 'string',
        'estado' => 'boolean'
    ];


    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }


    public function scopeActivo($query)
    {
        return $query->where('estado', 1);
    }
    public function scopeBuscar($query, $termino)
    {
        if ($termino) {
            return $query->where(function ($q) use ($termino) {
                $q->where('nombre', 'LIKE', "%{$termino}%")
                    ->orWhere('apellido', 'LIKE', "%{$termino}%")
                    ->orWhere('correo', 'LIKE', "%{$termino}%");
            });
        }
    }


    public function getNombreCompletoAttribute()
    {
        return "{$this->nombre} {$this->apellido}";
    }

    public function setCorreoAttribute($value)
    {
        $this->attributes['correo'] = strtolower($value);
    }


    public function setTelefonoAttribute($value)
    {
        $this->attributes['telefono'] = preg_replace('/[^0-9]/', '', $value);
    }

    protected static function boot()
    {
        parent::boot();

        // Cuando se crea un nuevo contacto
        static::creating(function ($contacto) {
            if (!$contacto->estado) {
                $contacto->estado = true;
            }
        });
    }
}
