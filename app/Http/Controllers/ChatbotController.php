<?php
namespace App\Http\Controllers;
use App\Models\{Especialidad,HorarioAtencion,CitaMedica,ConsultaAsistenteIA,Usuario};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class ChatbotController extends Controller
{
    public function procesarMensaje(Request $r)
    {
        $d=$r->validate(['mensaje'=>'required|string|max:500']);$text=Str::lower(Str::ascii($d['mensaje']));
        $u=session('id_usuario')?Usuario::with('rol')->find(session('id_usuario')):null;
        $patient=$u && $u->estado==='Activo' && $u->rol->nombre_rol==='Paciente';
        if(str_contains($text,'cita') || preg_match('/^[\w-]*\d[\w-]*$/',$text)) {
            if(!$patient) $answer='Para consultar citas personales, inicie sesión con su correo y contraseña. El CI por sí solo no permite acceder a su historial.';
            elseif(preg_match('/^[\w-]*\d[\w-]*$/',$text) && $text!==Str::lower($u->persona->ci)) $answer='Solo puede consultar las citas vinculadas a su propia cuenta.';
            else {
                $rows=CitaMedica::with('medico.persona','especialidad')->where('id_paciente',$u->id_persona)->whereIn('estado',['Programada','Confirmada'])->whereDate('fecha_cita','>=',today())->orderBy('fecha_cita')->limit(20)->get();
                $answer=$rows->isEmpty()?'No tiene citas activas. Puede reservar desde Nueva cita.':$rows->map(fn($c)=>$c->codigo_cita.' · '.$c->especialidad->nombre.' · '.$c->medico->persona->nombre_completo.' · '.$c->fecha_cita.' '.$c->hora_inicio.' · '.$c->estado)->implode("\n");
            }
        } elseif(str_contains($text,'especial')) $answer=Especialidad::where('estado','Activo')->orderBy('nombre')->get()->map(fn($e)=>$e->nombre.': '.$e->arancel_consulta.' '.strtoupper(config('services.stripe.currency')).' · '.$e->edad_minima.'–'.$e->edad_maxima.' años')->implode("\n");
        elseif(str_contains($text,'horario') || str_contains($text,'medico')) $answer=HorarioAtencion::with('medico.persona')->where('estado','Activo')->whereHas('medico',fn($q)=>$q->where('estado','Activo'))->limit(30)->get()->map(fn($h)=>$h->medico->persona->nombre_completo.': '.$h->dia_semana.' '.$h->hora_inicio.'–'.$h->hora_fin)->implode("\n") ?: 'No hay horarios publicados todavía.';
        elseif(str_contains($text,'requisito')) $answer='Presente su documento de identidad y código de reserva. Puede gestionar sus antecedentes desde Mi perfil clínico.';
        else $answer='Soy Angélica. Puedo ayudarle con especialidades, horarios, requisitos y sus citas. Elija una de las opciones del chat.';
        ConsultaAsistenteIA::create(['id_usuario'=>$u?->id_usuario,'canal'=>'Chatbot_Angelica','texto_entrada'=>$d['mensaje'],'respuesta_emitida'=>$answer]);
        return response()->json(['respuesta'=>$answer]);
    }
}
