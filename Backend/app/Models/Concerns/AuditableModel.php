<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

abstract class AuditableModel extends Model implements AuditableContract
{
    use AuditableForEmpresa;
}
