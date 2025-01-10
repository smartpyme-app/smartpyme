<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

class Module extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'status'
    ];

    public function submodules()
    {
        return $this->hasMany(Submodule::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'module_permissions');
    }
}