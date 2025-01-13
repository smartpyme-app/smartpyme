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

    protected $appends = ['submodules_count'];

    public function submodules()
    {
        return $this->hasMany(Submodule::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'module_permissions');
    }

    //submodules_count
    public function getSubmodulesCountAttribute()
    {
        return $this->submodules()->count();
    }
    //custom_permissions
    public function custom_permissions()
    {
        return $this->hasMany(ModulePermission::class, 'module_id', 'id')->where('permission_type', 'custom');
    }


}