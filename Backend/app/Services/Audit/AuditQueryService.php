<?php

namespace App\Services\Audit;

use App\Models\Audit\Audit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditQueryService
{
    public function paginate(array $filters, bool $crossTenant = false): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? $filters['paginate'] ?? 25), 50);

        $query = Audit::query()
            ->with(['user:id,name', 'empresa:id,nombre'])
            ->when($crossTenant, fn ($q) => $q->withoutGlobalScope('empresa'))
            ->when(! empty($filters['id_empresa']), fn ($q) => $q->where('id_empresa', $filters['id_empresa']))
            ->when(! empty($filters['module']), fn ($q) => $q->where('module', $filters['module']))
            ->when(! empty($filters['user_id']), fn ($q) => $q->where('user_id', $filters['user_id']))
            ->when(! empty($filters['fecha_inicio']), fn ($q) => $q->whereDate('created_at', '>=', $filters['fecha_inicio']))
            ->when(! empty($filters['fecha_fin']), fn ($q) => $q->whereDate('created_at', '<=', $filters['fecha_fin']))
            ->orderByDesc('created_at');

        return $query->paginate($perPage);
    }
}
