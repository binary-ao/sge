<?php

namespace App\Http\Requests;

use App\Models\Escola;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EscolaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $escola = $this->route('escola');

        return $this->user()->can('escolas.gerir')
            && (! $escola || app(\App\Support\ContextoEscola::class)->podeVerEscola($escola));
    }

    protected function prepareForValidation(): void
    {
        $normalizar = fn ($v) => is_string($v) ? str_replace(',', '.', trim($v)) : $v;
        $this->merge([
            'codigo' => strtoupper(trim((string) $this->codigo)),
            'latitude' => $normalizar($this->latitude),
            'longitude' => $normalizar($this->longitude),
            'coordenadas_demonstrativas' => $this->boolean('coordenadas_demonstrativas'),
        ]);
    }

    public function rules(): array
    {
        $escola = $this->route('escola');
        $anoActual = (int) date('Y');

        return [
            'nome' => ['required', 'string', 'max:200'],
            'codigo' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('escolas', 'codigo')->ignore($escola)],
            'municipio_id' => array_values(array_filter(['required', 'integer', Rule::exists('municipios', 'id')->where('activo', true),
                // Jurisdição: a Direcção Municipal só cadastra escolas no seu município.
                $this->user()->perfil?->ambito === 'municipio' ? Rule::in([(int) $this->user()->municipio_id]) : null])),
            'tipologia_id' => ['required', 'integer', Rule::exists('tipologias_escola', 'id')->where('activo', true)],
            'natureza' => ['required', Rule::in(array_keys(Escola::NATUREZAS))],
            'niveis' => ['required', 'array', 'min:1'],
            'niveis.*' => ['integer', 'distinct', Rule::exists('niveis_ensino', 'id')->where('activo', true)],
            'comuna' => ['nullable', 'string', 'max:100'],
            'bairro' => ['nullable', 'string', 'max:150'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'coordenadas_demonstrativas' => ['boolean'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'telefone_alternativo' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'director_nome' => ['nullable', 'string', 'max:150'],
            'director_telefone' => ['nullable', 'string', 'max:30'],
            'estado_funcionamento' => ['required', Rule::in(array_keys(Escola::ESTADOS))],
            'ano_inicio_actividade' => ['nullable', 'integer', 'min:1800', 'max:'.$anoActual],
            'numero_salas' => ['nullable', 'integer', 'min:0', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'niveis.required' => 'Seleccione pelo menos um nível de ensino ministrado.',
            'municipio_id.in' => 'Só pode cadastrar escolas no município da sua jurisdição.',
            'codigo.unique' => 'Já existe uma escola com este código institucional.',
            'codigo.regex' => 'O código só pode conter letras, números e hífenes (ex.: BGO-DAN-0101).',
        ];
    }
}
