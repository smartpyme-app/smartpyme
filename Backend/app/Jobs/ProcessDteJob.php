<?php

namespace App\Jobs;

use App\Events\DteValidated;
use App\Models\DteManagement\DteDocument;
use App\Models\DteManagement\DteTipoMapeo;
use App\Models\DteManagement\UserEmailAccount;
use App\Services\Dte\DteDocumentParseService;
use App\Services\Dte\DteProductSearchService;
use App\Services\Dte\DteValidatorService;
use App\Support\Dte\DteEmailAttachmentHelper;
use App\Support\FacturacionElectronica\XmlRespuestaHaciendaCr;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessDteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        protected UserEmailAccount $account,
        protected string $sourceTempPath,
        protected string $emailMessageId,
        protected string $sourceFormat = DteEmailAttachmentHelper::FORMAT_JSON,
        protected ?string $pdfTempPath = null,
        protected ?string $acuseTempPath = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(
        DteDocumentParseService $parser,
        DteValidatorService $validator,
        DteProductSearchService $productSearch
    ): string {
        $account = $this->account->fresh();
        if (!$account) {
            $this->cleanupTempFiles();

            return 'failed';
        }

        if (DteDocument::withoutGlobalScopes()
            ->where('id_empresa', $account->id_empresa)
            ->where('email_message_id', $this->emailMessageId)
            ->exists()) {
            $this->cleanupTempFiles();

            return 'duplicate';
        }

        $sourceContent = file_get_contents($this->sourceTempPath);

        try {
            $dteData = $parser->parse($sourceContent, $account->empresa);
        } catch (\Throwable $e) {
            $this->cleanupTempFiles();
            $this->saveInvalidDte($account, $sourceContent, $e->getMessage(), 'parse_error');

            return 'created';
        }

        $empresa = $account->empresa;
        $tenantNit = $empresa->nit ?? '';
        $validation = $validator->validate($dteData, $tenantNit);

        $year = Carbon::parse($dteData['emission_date'])->format('Y');
        $month = Carbon::parse($dteData['emission_date'])->format('m');
        $dteUuid = $dteData['dte_uuid'];
        $basePath = "{$account->id_empresa}/{$year}/{$month}/{$dteUuid}";

        $jsonPath = null;
        $xmlPath = null;
        $acusePath = null;
        $acuseEstado = null;

        if ($this->sourceFormat === DteEmailAttachmentHelper::FORMAT_XML) {
            $xmlPath = $basePath.'.xml';
            Storage::disk('dtes')->put($xmlPath, $sourceContent);

            $compatJson = json_encode($dteData['raw'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $jsonPath = $basePath.'.json';
            Storage::disk('dtes')->put($jsonPath, $compatJson);
        } else {
            $jsonPath = $basePath.'.json';
            Storage::disk('dtes')->put($jsonPath, $sourceContent);
        }

        $pdfPath = null;
        if ($this->pdfTempPath && file_exists($this->pdfTempPath)) {
            $pdfPath = $basePath.'.pdf';
            Storage::disk('dtes')->put($pdfPath, file_get_contents($this->pdfTempPath));
        }

        if ($this->acuseTempPath && file_exists($this->acuseTempPath)) {
            $acuseContent = file_get_contents($this->acuseTempPath);
            $acuseContent = XmlRespuestaHaciendaCr::normalizar($acuseContent);
            $acusePath = $basePath.'-acuse.xml';
            Storage::disk('dtes')->put($acusePath, $acuseContent);
            $acuseEstado = DteEmailAttachmentHelper::extractAcuseEstado($acuseContent);
        }

        $this->cleanupTempFiles();

        $processingStatus = 'pending';
        $validationStatus = $validation['valid'] ? 'valid' : 'invalid';
        $codPais = $dteData['pais'] ?? 'SV';
        $mapeo = DteTipoMapeo::getByCodigo($dteData['dte_type'], $codPais);
        $destino = $mapeo?->destino ?? ($codPais === 'CR' ? 'gasto' : 'compra');

        if ($validation['valid']) {
            if ($destino === 'compra') {
                $matchResult = $productSearch->checkItemsMatch($dteData['items'] ?? [], $account->id_empresa);
                if (!$matchResult['all_matched']) {
                    $processingStatus = 'pendiente_clasificacion';
                }
            }
        }

        try {
            $document = DteDocument::withoutGlobalScopes()->create([
                'id_empresa' => $account->id_empresa,
                'pais' => $codPais,
                'user_email_account_id' => $account->id,
                'dte_uuid' => $dteUuid,
                'dte_type' => $dteData['dte_type'],
                'dte_number' => $dteData['dte_number'],
                'emission_date' => $dteData['emission_date'],
                'total_amount' => $dteData['total_amount'],
                'issuer_nit' => $dteData['issuer_nit'],
                'issuer_name' => $dteData['issuer_name'],
                'receiver_nit' => $dteData['receiver_nit'],
                'formato_origen' => $dteData['formato_origen'] ?? $this->sourceFormat,
                'json_path' => $jsonPath,
                'xml_path' => $xmlPath,
                'acuse_xml_path' => $acusePath,
                'acuse_estado' => $acuseEstado,
                'pdf_path' => $pdfPath,
                'validation_status' => $validationStatus,
                'validation_errors' => $validation['valid'] ? null : $validation['errors'],
                'processing_status' => $processingStatus,
                'destino' => $destino,
                'email_message_id' => $this->emailMessageId,
            ]);

            if ($validation['valid']) {
                event(new DteValidated($document));
            }

            return 'created';
        } catch (QueryException $e) {
            $this->cleanupTempFiles();
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'dte_documents_empresa_uuid_unique')) {
                return 'duplicate';
            }
            throw $e;
        }
    }

    protected function saveInvalidDte(UserEmailAccount $account, string $sourceContent, string $error, string $type): void
    {
        try {
            $parser = app(DteDocumentParseService::class);
            $dteData = $parser->parse($sourceContent, $account->empresa);
        } catch (\Throwable) {
            $dteData = [
                'dte_uuid' => 'unknown-'.uniqid(),
                'dte_type' => '00',
                'dte_number' => '',
                'emission_date' => now()->format('Y-m-d'),
                'total_amount' => 0,
                'issuer_nit' => '',
                'issuer_name' => '',
                'receiver_nit' => null,
                'pais' => 'SV',
                'formato_origen' => $this->sourceFormat,
            ];
        }

        DteDocument::withoutGlobalScopes()->create([
            'id_empresa' => $account->id_empresa,
            'pais' => $dteData['pais'] ?? 'SV',
            'user_email_account_id' => $account->id,
            'dte_uuid' => $dteData['dte_uuid'],
            'dte_type' => $dteData['dte_type'] ?? '00',
            'dte_number' => $dteData['dte_number'] ?? '',
            'emission_date' => $dteData['emission_date'] ?? now(),
            'total_amount' => $dteData['total_amount'] ?? 0,
            'issuer_nit' => $dteData['issuer_nit'] ?? '',
            'issuer_name' => $dteData['issuer_name'] ?? '',
            'receiver_nit' => $dteData['receiver_nit'] ?? null,
            'formato_origen' => $dteData['formato_origen'] ?? $this->sourceFormat,
            'json_path' => null,
            'xml_path' => null,
            'pdf_path' => null,
            'validation_status' => 'invalid',
            'validation_errors' => [$error],
            'processing_status' => 'failed',
            'processing_errors' => "Error tipo: {$type} - {$error}",
            'email_message_id' => $this->emailMessageId,
        ]);
    }

    protected function cleanupTempFiles(): void
    {
        if (file_exists($this->sourceTempPath)) {
            @unlink($this->sourceTempPath);
        }
        if ($this->pdfTempPath && file_exists($this->pdfTempPath)) {
            @unlink($this->pdfTempPath);
        }
        if ($this->acuseTempPath && file_exists($this->acuseTempPath)) {
            @unlink($this->acuseTempPath);
        }
    }
}
