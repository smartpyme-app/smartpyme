<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

class ModulePermission extends Model
{
    protected $fillable = [
        'module_id',
        'submodule_id',
        'permission_id',
        'permission_type'
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function submodule()
    {
        return $this->belongsTo(Submodule::class);
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }
}