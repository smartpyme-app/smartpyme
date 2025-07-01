<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

class Submodule extends Model
{
    protected $fillable = [
        'module_id',
        'name',
        'display_name',
        'description',
        'status'
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    // public function permissions()
    // {
    //     return $this->belongsToMany(Permission::class, 'module_permissions');
    // }

    public function permissions()
    {
        //ir a permissions
        return $this->hasMany(ModulePermission::class, 'submodule_id')->with('permission');
    }
}