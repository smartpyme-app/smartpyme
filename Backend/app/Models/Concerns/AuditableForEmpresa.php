<?php

namespace App\Models\Concerns;

use OwenIt\Auditing\Auditable as AuditableTrait;

trait AuditableForEmpresa
{
    use AuditableTrait;

    abstract protected static function auditModule(): string;

    public function transformAudit(array $data): array
    {
        $data['id_empresa'] = $this->id_empresa ?? auth()->user()?->id_empresa;
        $data['module'] = static::auditModule();

        return $data;
    }

    public function getAuditExclude(): array
    {
        return array_merge($this->auditExclude ?? [], ['updated_at', 'created_at']);
    }
}
