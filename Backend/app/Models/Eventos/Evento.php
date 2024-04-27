<?php

namespace App\Models\Eventos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Builder;
use App\Mail\CitaMail;
use App\Models\WhatsappApi;
use Auth;

class Evento extends Model
{
    use HasFactory;
    protected $table = 'eventos';
    protected $fillable = [
        'descripcion',
        'detalles',
        'tipo',
        'estado',
        'inicio',
        'fin',
        'frecuencia',
        'frecuencia_fin',
        'duracion',
        'id_servicio',
        'id_venta',
        'id_usuario',
        'id_cliente',
        'id_sucursal',
        'id_empresa',
    ];
    protected $appends = ['nombre_usuario', 'nombre_cliente', 'nombre_servicio'];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function notificar(){
        try {
            $validator = Validator::make(['correo' => $this->cliente()->pluck('correo')->first()],[
              'correo' => 'required|email'
            ]);

            if($validator->passes() && $this->tipo == 'Confirmado'){
                Mail::to($this->cliente()->pluck('correo')->first())->send(new CitaMail($this));
                // if ($this->cliente()->pluck('celular')->first()) {
                //     $wp = new WhatsappApi;
                //     $response = $wp->send('text',$this->cliente()->pluck('celular')->first(), 'Hola, confirmación de la cita.');
                // }
            }

            $validator = Validator::make(['email' => $this->usuario()->pluck('email')->first()],[
              'email' => 'required|email'
            ]);

            if($validator->passes() && $this->tipo == 'Confirmado'){
                Mail::to($this->usuario()->pluck('email')->first())->send(new CitaMail($this));
                // if ($this->usuario()->pluck('celular')->first()) {
                //     $wp = new WhatsappApi;
                //     $response = $wp->send('text',$this->usuario()->pluck('celular')->first(), 'Hola, confirmación de la cita.');
                // }
            }


            return true;

        } catch (Exception $e) {
           throw new \ErrorException('No se pudo enviar el correo');
        }
    }

    public function estadoVerificarFrecuencia($estado){
        if ($this->frecuencia) {
            $nuevoEvento = $this->replicate();
            if ($this->frecuencia == 'DAILY') {
                $nuevoEvento->inicio = \Carbon\Carbon::parse($this->inicio)->addDay();
                $nuevoEvento->fin = \Carbon\Carbon::parse($this->fin)->addDay();
            }
            if ($this->frecuencia == 'WEEKLY') {
                $nuevoEvento->inicio = \Carbon\Carbon::parse($this->inicio)->addWeek();
                $nuevoEvento->fin = \Carbon\Carbon::parse($this->fin)->addWeek();
            }
            if ($this->frecuencia == 'MONTHLY') {
                $nuevoEvento->inicio = \Carbon\Carbon::parse($this->inicio)->addMonth();
                $nuevoEvento->fin = \Carbon\Carbon::parse($this->fin)->addMonth();
            }
            if ($this->frecuencia == 'YEARLY') {
                $nuevoEvento->inicio = \Carbon\Carbon::parse($this->inicio)->addYear();
                $nuevoEvento->fin = \Carbon\Carbon::parse($this->fin)->addYear();
            }
            if ($this->frecuencia_fin > $nuevoEvento->fin) {
                $nuevoEvento->save();
            }
        }
        $this->tipo = $estado;
        $this->frecuencia = '';
        $this->save();
    }

    public function getNombreUsuarioAttribute(){
        return $this->usuario()->pluck('name')->first();
    }
    
    public function getNombreServicioAttribute(){
        return $this->servicio()->pluck('nombre')->first();
    }

    public function getNombreClienteAttribute()
    {   $cliente = $this->cliente()->first();
        if ($cliente) {
            return $cliente->tipo == 'Empresa' ? $cliente->nombre_empresa : $cliente->nombre . ' ' . $cliente->apellido;
        }
        return 'Consumidor Final';
    }

    public function cliente(){
       return $this->belongsTo('App\Models\Ventas\Clientes\Cliente', 'id_cliente');
    }

    public function surcursal(){
       return $this->belongsTo('App\Models\Admin\Surcursal', 'id_sucursal');
    }

    public function empresa(){
       return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function servicio(){
       return $this->belongsTo('App\Models\Inventario\Producto', 'id_servicio');
    }

    public function usuario(){
       return $this->belongsTo('App\Models\User', 'id_usuario');
    }

}
