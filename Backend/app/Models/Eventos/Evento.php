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
        'duracion',
        'id_servicio',
        'id_venta',
        'id_usuario',
        'id_cliente',
        'id_empresa',
    ];
    protected $appends = ['nombre_cliente'];

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

    public function getNombreUsuarioAttribute(){
        return $this->usuario()->pluck('name')->first();
    }

    public function getNombreClienteAttribute(){
        if($this->id_cliente){
            return $this->cliente()->pluck('nombre')->first()  
                    .' ' .$this->cliente()->pluck('apellido')->first();
        }
        else{
            return 'Sin datos';
        }
    }

    public function cliente(){
       return $this->belongsTo('App\Models\Ventas\Clientes\Cliente', 'id_cliente');
    }

    public function empresa(){
       return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function servicio(){
       return $this->belongsTo('App\Models\Producto', 'id_servicio');
    }

    public function usuario(){
       return $this->belongsTo('App\Models\User', 'id_usuario');
    }

}
