<?php

namespace App\Models\DteManagement;

use App\Models\Admin\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class DteDocument extends Model
{
    protected $table = 'dte_documents';

    protected $fillable = [
        'id_empresa',
        'user_email_account_id',
        'dte_uuid',
        'dte_type',
        'dte_number',
        'emission_date',
        'total_amount',
        'issuer_nit',
        'issuer_name',
        'receiver_nit',
        'json_path',
        'pdf_path',
        'validation_status',
        'validation_errors',
        'processing_status',
        'processing_errors',
        'destino',
        'id_proyecto',
        'id_categoria',
        'tipo_gasto',
        'tipo_costo_gasto',
        'email_message_id',
    ];

    protected $casts = [
        'emission_date' => 'date',
        'total_amount' => 'decimal:2',
        'validation_errors' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    /**
     * Relationships
     */
    public function userEmailAccount()
    {
        return $this->belongsTo(UserEmailAccount::class, 'user_email_account_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('processing_status', 'pending');
    }

    public function scopePendingClassification($query)
    {
        return $query->where('processing_status', 'pendiente_clasificacion');
    }

    public function scopeValid($query)
    {
        return $query->where('validation_status', 'valid');
    }

    public function scopeForEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }
}
