<?php

namespace App\Models\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthorizationType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'display_name', 'description', 'conditions', 
        'expiration_hours', 'active'
    ];

    protected $casts = [
        'conditions' => 'array',
        'active' => 'boolean'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_authorization_types');
    }

    public function authorizations()
    {
        return $this->hasMany(Authorization::class);
    }

    // public function evaluateConditions($data)
    // {
    //     if (!$this->conditions) return true;
    
    //     foreach ($this->conditions as $key => $value) {
    //         if ($key === 'amount_threshold') {
    //             // Buscar en múltiples campos posibles
    //             $amount = $data['amount'] ?? $data['total'] ?? $data['sub_total'] ?? 0;
    //             return $amount > $value;
    //         }
    //         if ($key === 'discount_threshold' && isset($data['discount'])) {
    //             return $data['discount'] > $value;
    //         }
    //     }
    
    //     return false;
    // }

    public function evaluateConditions($data)
{
    if (!$this->conditions) return true;

    foreach ($this->conditions as $key => $value) {
        if ($key === 'exclude_roles' && auth()->user()) {
            $userRoles = auth()->user()->roles->pluck('name')->toArray();
            if (array_intersect($userRoles, $value)) {
                return false; // Usuario tiene rol excluido, NO necesita autorización
            }
        }
        if ($key === 'amount_threshold') {
            $amount = $data['amount'] ?? $data['total'] ?? $data['sub_total'] ?? 0;
            return $amount > $value;
        }
        if ($key === 'discount_threshold' && isset($data['discount'])) {
            return $data['discount'] > $value;
        }
    }

    return false;
}
}
