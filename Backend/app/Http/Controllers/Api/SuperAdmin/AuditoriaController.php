<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditResource;
use App\Services\Audit\AuditPresentationService;
use App\Services\Audit\AuditQueryService;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    public function index(Request $request, AuditQueryService $service, AuditPresentationService $presentation)
    {
        $paginator = $service->paginate(
            $request->only([
                'id_empresa',
                'module',
                'user_id',
                'fecha_inicio',
                'fecha_fin',
                'page',
                'per_page',
                'paginate',
            ]),
            crossTenant: true
        );

        $presentation->setProductNames($service->productNamesForPage($paginator));
        $presentation->setDocumentReferences($service->documentReferencesForPage($paginator));
        $request->attributes->set('audit_presentation', $presentation);

        return AuditResource::collection($paginator);
    }
}
