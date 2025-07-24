<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inventario\CustomFields\CustomField;
use App\Models\Inventario\CustomFields\CustomFieldValue;
use Illuminate\Support\Facades\Log;

class CustomFieldsController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $empresa = $user->empresa;
        if ($request->bandera) {
            $customFields = CustomField::with('values.productCustomFields', 'productCustomFields')->where('empresa_id', $empresa->id)->get();

            // Crear un nuevo array para almacenar los campos válidos
            $validCustomFields = [];
            foreach ($customFields as $customField) {
                if ($customField->field_type !== 'select' || !$customField->values->isEmpty()) {
                    $validCustomFields[] = $customField;
                }
            }

            // Asignar el nuevo array de campos válidos
            $customFields = $validCustomFields;

            return response()->json(['data' => $customFields]);
        } else {
            $customFields = CustomField::with('values.productCustomFields', 'productCustomFields')
                ->where('empresa_id', $empresa->id)
                ->where('name', 'like', '%' . $request->buscador . '%')
                ->paginate($request->paginate);
        }


        return response()->json($customFields);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'field_type' => 'required|in:select,text,number',
            'is_required' => 'required|boolean',
        ]);

        if ($request->field_type === 'select') {
            $request->validate([
                'values' => 'required|array|min:1',
                'values.*.value' => 'required|string|max:255'
            ]);
        }
        $user = auth()->user();
        $empresa = $user->empresa;


        try {
            $customField = CustomField::create([
                'name' => $request->name,
                'field_type' => $request->field_type,
                'is_required' => $request->is_required,
                'empresa_id' => $empresa->id
            ]);

            if ($request->field_type === 'select') {
                foreach ($request->values as $value) {
                    CustomFieldValue::create([
                        'custom_field_id' => $customField->id,
                        'value' => $value['value']
                    ]);
                }
            }

            $customField->load('values');

            return response()->json($customField, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el campo personalizado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $customField = CustomField::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'field_type' => 'required|in:select,text,number',
            'is_required' => 'required|boolean',
        ]);

        if ($request->field_type === 'select') {
            $request->validate([
                'values' => 'required|array|min:1',
                'values.*.value' => 'required|string|max:255'
            ]);
        }

        try {
            $customField->update([
                'name' => $request->name,
                'field_type' => $request->field_type,
                'is_required' => $request->is_required
            ]);

            if ($request->field_type === 'select') {
                $requestValueIds = collect($request->values)->pluck('id')->filter()->all();

        
                CustomFieldValue::where('custom_field_id', $customField->id)
                    ->whereNotIn('id', $requestValueIds)
                    ->delete();
                foreach ($request->values as $value) {
                    if (!empty($value['id'])) {
                        CustomFieldValue::where('id', $value['id'])
                            ->update(['value' => $value['value']]);
                    } else {
                        CustomFieldValue::create([
                            'custom_field_id' => $customField->id,
                            'value' => $value['value']
                        ]);
                    }
                }
            }

            $customField->load('values');
            return response()->json($customField);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el campo personalizado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function usage($id)
    {
        $customField = CustomField::with('values.productCustomFields', 'productCustomFields')->findOrFail($id);
        return response()->json($customField);
    }

    public function destroy($id)
    {
        $customField = CustomField::findOrFail($id);
        $customField->delete();
        return response()->json(['message' => 'Campo personalizado eliminado exitosamente']);
    }
}
