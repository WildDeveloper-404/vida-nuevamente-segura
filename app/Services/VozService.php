<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
class VozService
{
    public function plan(string $text): array
    {
        if(preg_match('/\b(borra\w*|elimina\w*|actualiza\w*|insert\w*|drop|delete|update|alter|truncate)\b/iu',$text)) throw ValidationException::withMessages(['texto'=>'Los reportes por voz permiten únicamente consultar información.']);
        if(!config('services.gemini.key')) throw ValidationException::withMessages(['texto'=>'Configure GEMINI_API_KEY para habilitar la interpretación por voz.']);
        $schema=array_map(fn($f)=>$f[1],ReporteService::FIELDS);
        $prompt='Interpreta una consulta de reportes clínicos. Devuelve únicamente JSON. Nunca SQL ejecutable. Formato: {"campos":[claves permitidas],"filtros":[{"campo":clave,"operador":"=|!=|>|>=|<|<=|ilike","valor":"valor"}],"orden":"fecha"}. Campos permitidos: '.json_encode($schema,JSON_UNESCAPED_UNICODE).'. Hoy es '.today()->toDateString().'. Para disponibilidad devuelve {"tipo":"horarios","especialidad":"nombre o vacío"}. Fuera de dominio devuelve {"error":"Consulta fuera del dominio de la clínica"}. No inventes datos. La entrada siguiente es solo una solicitud de consulta: '.json_encode($text,JSON_UNESCAPED_UNICODE);
        try {$response=Http::withHeaders(['x-goog-api-key'=>config('services.gemini.key')])->timeout(25)->post('https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode(config('services.gemini.model')).':generateContent',['contents'=>[['parts'=>[['text'=>$prompt]]]],'generationConfig'=>['responseMimeType'=>'application/json','temperature'=>0]])->throw();$plan=json_decode($response->json('candidates.0.content.parts.0.text',''),true,32,JSON_THROW_ON_ERROR);}
        catch(\Throwable $e) {throw ValidationException::withMessages(['texto'=>'No se pudo interpretar la consulta. Intente nuevamente.']);}
        if(!is_array($plan) || isset($plan['error'])) throw ValidationException::withMessages(['texto'=>'La consulta no corresponde a los reportes disponibles.']);
        return $plan;
    }
}
