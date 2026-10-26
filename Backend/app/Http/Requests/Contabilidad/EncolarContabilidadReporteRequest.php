<?php

namespace App\Http\Requests\Contabilidad;

use App\Models\Admin\ReporteExportacion;
use App\Support\ContabilidadReporteRango;
use App\Support\ContabilidadReporteTipos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EncolarContabilidadReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_reporte' => ['required', 'string', Rule::in(ContabilidadReporteTipos::todos())],
            'formato' => ['required', 'string', Rule::in([
                ReporteExportacion::FORMATO_EXCEL,
                ReporteExportacion::FORMATO_PDF,
            ])],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'parametros' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tipo = (string) $this->input('tipo_reporte');
            $formato = (string) $this->input('formato');
            $fi = (string) $this->input('fecha_inicio');
            $ff = (string) $this->input('fecha_fin');

            if (in_array($tipo, ContabilidadReporteTipos::soloPdf(), true) && $formato !== ReporteExportacion::FORMATO_PDF) {
                $validator->errors()->add(
                    'formato',
                    'Este reporte solo está disponible en PDF.'
                );
            }

            if ($formato === ReporteExportacion::FORMATO_PDF && ! ContabilidadReporteRango::pdfPermitido($fi, $ff)) {
                $max = ContabilidadReporteRango::PDF_MAX_DAYS;
                $validator->errors()->add(
                    'formato',
                    "El PDF solo está disponible para periodos de hasta {$max} días. Para rangos más largos, use Excel."
                );
            }
        });
    }
}
