<?php

namespace App\Http\Controllers\Api\DteManagement;

use App\Http\Controllers\Controller;
use App\Models\DteManagement\EmailInbox;
use App\Services\MailIngest\EmailInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailInboxController extends Controller
{
    public function __construct(
        protected EmailInboxService $inboxes
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $inbox = $this->inboxes->currentForEmpresa((int) $request->user()->id_empresa);

        return response()->json([
            'inbox' => $inbox ? $this->inboxes->toApi($inbox) : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $this->inboxes->currentForEmpresa((int) $user->id_empresa);
        if ($current && $current->status === EmailInbox::STATUS_PAUSED) {
            $inbox = $this->inboxes->resume($current);
        } else {
            $inbox = $this->inboxes->createForEmpresa((int) $user->id_empresa, (int) $user->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reenvío automático activado',
            'inbox' => $this->inboxes->toApi($inbox),
        ]);
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        $inbox = $this->inboxOfEmpresa($request, $id);
        if (!$inbox) {
            return response()->json(['error' => 'Bandeja no encontrada'], 404);
        }

        return response()->json([
            'success' => true,
            'inbox' => $this->inboxes->toApi($this->inboxes->pause($inbox)),
        ]);
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        $inbox = $this->inboxOfEmpresa($request, $id);
        if (!$inbox) {
            return response()->json(['error' => 'Bandeja no encontrada'], 404);
        }

        return response()->json([
            'success' => true,
            'inbox' => $this->inboxes->toApi($this->inboxes->resume($inbox)),
        ]);
    }

    public function regenerate(Request $request, int $id): JsonResponse
    {
        $inbox = $this->inboxOfEmpresa($request, $id);
        if (!$inbox) {
            return response()->json(['error' => 'Bandeja no encontrada'], 404);
        }

        $new = $this->inboxes->regenerate($inbox, (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Se generó una dirección nueva. Actualiza la regla de reenvío.',
            'inbox' => $this->inboxes->toApi($new),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $inbox = $this->inboxOfEmpresa($request, $id);
        if (!$inbox) {
            return response()->json(['error' => 'Bandeja no encontrada'], 404);
        }

        $this->inboxes->disable($inbox);

        return response()->json([
            'success' => true,
            'message' => 'Reenvío automático desactivado',
        ]);
    }

    private function inboxOfEmpresa(Request $request, int $id): ?EmailInbox
    {
        return EmailInbox::query()
            ->where('id', $id)
            ->where('id_empresa', $request->user()->id_empresa)
            ->where('status', '!=', EmailInbox::STATUS_DISABLED)
            ->first();
    }
}
