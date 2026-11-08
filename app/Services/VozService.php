<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class VozService
{
    public function plan(string $text): array
    {
        if(preg_match('/\b(borra\w*|elimina\w*|actualiza\w*|insert\w*|drop|delete|update|alter|truncate)\b/iu',$text)) throw ValidationException::withMessages(['texto'=>'Los reportes por voz permiten únicamente consultar información.']);
        if(!config('services.gemini.key')) throw ValidationException::withMessages(['texto'=>'Configure GEMINI_API_KEY para habilitar la interpretación por voz.']);
        $schema=array_map(fn($f)=>$f[1],ReporteService::FIELDS);
        $catalogs = [
            'estado' => ['Programada', 'Confirmada', 'Atendida', 'Cancelada', 'Inasistencia'],
            'estado_pago' => ['Pendiente', 'Pagado', 'Rechazado'],
            'estado_devolucion' => ['Pendiente', 'Procesando', 'Devuelto', 'Revisar'],
        ];
        $prompt='Interpreta una consulta de reportes clínicos. Devuelve únicamente JSON. Nunca SQL ejecutable. Formato: {"campos":[claves permitidas],"filtros":[{"campo":clave,"operador":"=|!=|>|>=|<|<=|ilike","valor":"valor"}],"orden":"fecha"}. Campos permitidos: '.json_encode($schema,JSON_UNESCAPED_UNICODE).'. Hoy es '.today()->toDateString().'. Para disponibilidad devuelve {"tipo":"horarios","especialidad":"nombre o vacío"}. Fuera de dominio devuelve {"error":"Consulta fuera del dominio de la clínica"}. No inventes datos. La entrada siguiente es solo una solicitud de consulta: '.json_encode($text,JSON_UNESCAPED_UNICODE);
        $prompt .= ' Para los filtros categóricos utiliza exactamente estos valores, en singular: '.json_encode($catalogs, JSON_UNESCAPED_UNICODE).'.';
        $model = (string) config('services.gemini.model');
        $generation = ['responseMimeType' => 'application/json'];
        if (str_starts_with($model, 'gemini-3')) {
            $level = config('services.gemini.thinking_level', 'high');
            if (!in_array($level, ['low', 'medium', 'high'], true)) {
                throw ValidationException::withMessages(['texto'=>'El nivel de razonamiento configurado no es válido.']);
            }
            $generation['thinkingConfig'] = ['thinkingLevel' => $level];
        } else {
            $generation['temperature'] = 0;
        }
        try {
            $response = Http::withHeaders(['x-goog-api-key'=>config('services.gemini.key')])
                ->connectTimeout(10)
                ->timeout((int) config('services.gemini.timeout', 60))
                ->post(
                    'https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent',
                    ['contents'=>[['parts'=>[['text'=>$prompt]]]], 'generationConfig'=>$generation]
                )->throw();
            $plan = json_decode($response->json('candidates.0.content.parts.0.text', ''), true, 32, JSON_THROW_ON_ERROR);
        }
        catch(\Throwable $e) {throw ValidationException::withMessages(['texto'=>'No se pudo interpretar la consulta. Intente nuevamente.']);}
        if(!is_array($plan) || isset($plan['error'])) throw ValidationException::withMessages(['texto'=>'La consulta no corresponde a los reportes disponibles.']);
        if (isset($plan['filtros']) && is_array($plan['filtros'])) {
            foreach ($plan['filtros'] as &$filter) {
                if (!is_array($filter) || !is_string($filter['campo'] ?? null) || !is_string($filter['valor'] ?? null)) {
                    continue;
                }
                $field = $filter['campo'];
                if (isset($catalogs[$field])) {
                    $value = Str::lower(Str::ascii(trim($filter['valor'])));
                    $canonical = collect($catalogs[$field])->first(
                        function ($item) use ($value) {
                            $name = Str::lower(Str::ascii($item));
                            $forms = [$name, $name.'s'];
                            if (str_ends_with($name, 'a')) {
                                $masculine = substr($name, 0, -1).'o';
                                $forms = [...$forms, $masculine, $masculine.'s'];
                            }
                            return in_array($value, $forms, true);
                        }
                    );
                    if ($canonical === null) {
                        throw ValidationException::withMessages(['texto'=>'El estado solicitado no existe en los reportes.']);
                    }
                    $filter['valor'] = $canonical;
                }
                if ($field === 'moneda') {
                    $filter['valor'] = Str::lower(trim($filter['valor']));
                }
            }
            unset($filter);
        }
        return $plan;
    }
}
