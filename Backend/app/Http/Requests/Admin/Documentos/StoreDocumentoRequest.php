<?php

namespace App\Http\Requests\Admin\Documentos;

use App\Models\Admin\Empresa;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentoRequest extends FormRequest
{
    /** @var list<string> */
    private const NOMBRES_FISCALES_HN = [
        'Factura con RTN',
        'Factura sin RTN',
        'Ticket',
        'Boleta de compra',
        'Nota de crédito',
        'Nota de débito',
        'Recibo por honorarios profesionales',
        'Guía de remisión',
        'Comprobante de retención',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $numeroEmisionRule = ['nullable', 'string'];

        $empresa = Empresa::find($this->input('id_empresa'));
        $nombre = $this->input('nombre');

        if (
            $empresa !== null
            && FacturacionElectronicaCountryResolver::resolveCodigoPaisFe($empresa) === FacturacionElectronicaCountryResolver::CODIGO_HONDURAS
            && in_array($nombre, self::NOMBRES_FISCALES_HN, true)
        ) {
            $numeroEmisionRule = ['required', 'string', Rule::in(self::numerosEmisionHn())];
        }

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'correlativo' => ['required', 'string', 'max:255'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
            'id_sucursal' => ['required', 'integer', 'exists:sucursales,id'],
            'id' => ['nullable', 'integer', 'exists:documentos,id'],
            'rangos' => ['nullable', 'string', 'max:255'],
            'numero_autorizacion' => ['nullable', 'string', 'max:255'],
            'resolucion' => ['nullable', 'string', 'max:255'],
            'nota' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'nuevaResolucion' => ['nullable', 'boolean'],
            'predeterminado' => ['nullable', 'boolean'],
            'prefijo' => ['nullable', 'string'],
            'inicial' => ['nullable', 'string'],
            'final' => ['nullable', 'string'],
            'fecha' => ['nullable', 'date'],
            'caja_id' => ['nullable', 'integer', 'exists:cajas,id'],
            'change' => ['nullable', 'boolean'],
            'numero_emision' => $numeroEmisionRule,
        ];
    }

    /** @return list<string> */
    private static function numerosEmisionHn(): array
    {
        return array_map(
            static fn (int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            range(1, 20)
        );
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es requerido.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'correlativo.required' => 'El correlativo es requerido.',
            'correlativo.max' => 'El correlativo no puede exceder 255 caracteres.',
            'id_empresa.required' => 'La empresa es requerida.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id_sucursal.required' => 'La sucursal es requerida.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
            'id.exists' => 'El documento seleccionado no existe.',
            'rangos.max' => 'Los rangos no pueden exceder 255 caracteres.',
            'numero_autorizacion.max' => 'El número de autorización no puede exceder 255 caracteres.',
            'resolucion.max' => 'La resolución no puede exceder 255 caracteres.',
            'nota.max' => 'La nota no puede exceder 1000 caracteres.',
            'fecha.date' => 'La fecha debe ser una fecha válida.',
            'caja_id.exists' => 'La caja seleccionada no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar strings
        if ($this->has('nombre')) {
            $this->merge(['nombre' => trim($this->nombre)]);
        }

        if ($this->has('correlativo')) {
            $this->merge(['correlativo' => trim($this->correlativo)]);
        }

        if ($this->has('rangos')) {
            $this->merge(['rangos' => trim($this->rangos)]);
        }

        if ($this->has('numero_autorizacion')) {
            $this->merge(['numero_autorizacion' => trim($this->numero_autorizacion)]);
        }

        if ($this->has('resolucion')) {
            $this->merge(['resolucion' => trim($this->resolucion)]);
        }

        if ($this->has('nota')) {
            $this->merge(['nota' => trim($this->nota)]);
        }
    }
}

