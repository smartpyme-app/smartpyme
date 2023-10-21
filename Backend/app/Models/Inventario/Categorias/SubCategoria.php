<?php

namespace App\Models\Inventario\Categorias;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubCategoria extends Model
{
    use SoftDeletes;
    protected $table = 'categoria_subcategorias';
    protected $fillable = array(
        'nombre',
        'img',
        'descripcion',
        'categoria_id'
    );

    protected $appends = ['total_productos', 'nombre_categoria'];

    public function getNombreCategoriaAttribute()
    {
        return $this->categoria()->pluck('nombre')->first();
    }

    public function getTotalProductosAttribute(){
        return $this->TotalProductos();
    }

    public function TotalProductos(){
        return $this->productos()->count();
    }

    public function productos(){
        // return $this->hasManyThrough(
        //     'App\Models\Inventario\Producto',
        //     'App\Models\Inventario\Categorias\Tipo',
        //     'subcategoria_id',
        //     'categoria_id',
        //     'id',
        //     'id'
        // );
        return $this->hasMany('App\Models\Inventario\Producto', 'subcategoria_id');
    }

    public function categoria(){
        return $this->belongsTo('App\Models\Inventario\Categorias\Categoria', 'categoria_id');
    }
}
