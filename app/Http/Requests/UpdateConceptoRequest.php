<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConceptoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $conceptoRouteParam = $this->route('concepto');
        $conceptoId = is_object($conceptoRouteParam) ? (int) $conceptoRouteParam->getKey() : (int) $conceptoRouteParam;

        return [
            'codigo' => [
                'required',
                'string',
                'max:10',
                Rule::unique('conceptos', 'codigo')->ignore($conceptoId),
            ],
            'nombre' => ['required', 'string', 'max:150'],
            'cuenta' => ['nullable', 'string', 'max:30'],
            'activo' => ['nullable', 'boolean'],
            'form_mode' => ['nullable', 'in:create,edit'],
            'concepto_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique' => 'Ya existe un concepto con ese código.',
            'codigo.max' => 'El código no puede superar 10 caracteres.',
            'nombre.required' => 'El nombre es obligatorio.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => mb_strtoupper(trim((string) $this->input('codigo'))),
            'nombre' => trim((string) $this->input('nombre')),
            'cuenta' => ($cuenta = trim((string) $this->input('cuenta'))) !== '' ? $cuenta : null,
            'activo' => $this->boolean('activo'),
        ]);
    }
}
