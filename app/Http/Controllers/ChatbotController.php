<?php
namespace App\Http\Controllers;
use App\Models\{Especialidad,HorarioAtencion,CitaMedica,ConsultaAsistenteIA,Usuario};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class ChatbotController extends Controller
{
    private const ORIENTATION = [
        'Consulta dental' => 'Odontología',
        'Consulta de la vista' => 'Oftalmología',
        'Consulta infantil' => 'Pediatría',
        'Control ginecológico' => 'Ginecología',
        'Consulta general o no sé' => 'Medicina General',
    ];

    public function procesarMensaje(Request $r)
    {
        $d=$r->validate(['mensaje'=>'required|string|max:500']);$text=trim(Str::lower(Str::ascii($d['mensaje'])));
        if (preg_match('/\b(?:dolor (?:en el |de )?pecho|me duele el pecho|(?:no puedo|no puede) respirar|dificultad (?:para|al) respirar|perdida de conciencia|sangrado (?:abundante|intenso))\b/', $text)) {
            return response()->json([
                'respuesta' => 'Si esto ocurre ahora, contacte al servicio de emergencias de su localidad o busque atención urgente. Evite esperar una cita rutinaria.',
            ]);
        }
        $u=session('id_usuario')?Usuario::with('rol')->find(session('id_usuario')):null;
        $patient=$u && $u->estado==='Activo' && $u->rol->nombre_rol==='Paciente';
        if (in_array(trim($text, "¿?!. "), ['ayudame a elegir una especialidad', 'ayudeme a elegir una especialidad', 'que especialidad me recomiendas'], true)) {
            return response()->json([
                'respuesta' => '¿Qué tipo de consulta desea agendar? Elija una opción general. Para orientación personalizada, consulte con Recepción.',
                'opciones' => array_keys(self::ORIENTATION),
            ]);
        }
        foreach (self::ORIENTATION as $option => $name) {
            if ($text === Str::lower(Str::ascii($option))) {
                return response()->json($this->orientation($name, (bool) $patient));
            }
        }

        if(str_contains($text,'cita') || preg_match('/^[\w-]*\d[\w-]*$/',$text)) {
            if(!$patient) $answer='Para consultar citas personales, inicie sesión con su correo y contraseña. El CI por sí solo no permite acceder a su historial.';
            elseif(preg_match('/^[\w-]*\d[\w-]*$/',$text) && $text!==Str::lower($u->persona->ci)) $answer='Solo puede consultar las citas vinculadas a su propia cuenta.';
            else {
                $rows=CitaMedica::with('medico.persona','especialidad')->where('id_paciente',$u->id_persona)->whereIn('estado',['Programada','Confirmada'])->whereDate('fecha_cita','>=',today())->orderBy('fecha_cita')->limit(20)->get();
                $answer=$rows->isEmpty()?'No tiene citas activas. Puede reservar desde Nueva cita.':$rows->map(fn($c)=>$c->codigo_cita.' · '.$c->especialidad->nombre.' · '.$c->medico->persona->nombre_completo.' · '.$c->fecha_cita.' '.$c->hora_inicio.' · '.$c->estado)->implode("\n");
            }
        } elseif(str_contains($text,'especial')) $answer=Especialidad::where('estado','Activo')->orderBy('nombre')->get()->map(fn($e)=>$e->nombre.': '.$e->arancel_consulta.' '.strtoupper(config('services.billing.currency')).' · '.$e->edad_minima.'–'.$e->edad_maxima.' años')->implode("\n");
        elseif(str_contains($text,'horario') || str_contains($text,'medico')) $answer=HorarioAtencion::with('medico.persona')->where('estado','Activo')->whereHas('medico',fn($q)=>$q->where('estado','Activo'))->limit(30)->get()->map(fn($h)=>$h->medico->persona->nombre_completo.': '.$h->dia_semana.' '.$h->hora_inicio.'–'.$h->hora_fin)->implode("\n") ?: 'No hay horarios publicados todavía.';
        elseif(str_contains($text,'requisito')) $answer='Presente su documento de identidad y código de reserva. Puede gestionar sus antecedentes desde Mi perfil clínico.';
        else $answer='Soy Angélica. Puedo ayudarle con especialidades, horarios, requisitos y sus citas. Elija una de las opciones del chat.';
        ConsultaAsistenteIA::create(['id_usuario'=>$u?->id_usuario,'canal'=>'Chatbot_Angelica','texto_entrada'=>$d['mensaje'],'respuesta_emitida'=>$answer]);
        return response()->json(['respuesta'=>$answer]);
    }

    private function orientation(string $name, bool $patient): array
    {
        $specialty = Especialidad::where('nombre', $name)->where('estado', 'Activo')->first();
        if (!$specialty) {
            return ['respuesta' => 'Esa especialidad no está disponible en el catálogo. Consulte con Recepción para elegir dónde pedir cita.'];
        }

        $currency = strtoupper(config('services.billing.currency'));
        $response = "Puede consultar {$specialty->nombre}. Arancel: {$specialty->arancel_consulta} {$currency}. Edades atendidas: {$specialty->edad_minima} a {$specialty->edad_maxima} años.";
        $response .= ' Revise los horarios al reservar. Si necesita ayuda para elegir, consulte con Recepción.';

        return [
            'respuesta' => $response,
            'enlace' => [
                'url' => route($patient ? 'paciente.citas.create' : 'login', [], false),
                'texto' => $patient ? 'Nueva cita' : 'Iniciar sesión para reservar',
            ],
        ];
    }
}
