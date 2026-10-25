<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class ReporteService
{
    public const FIELDS = [
        'paciente_codigo'=>['p.codigo_paciente','Paciente · Código'], 'ci'=>['pp.ci','Paciente · CI'],
        'nombres'=>['pp.nombres','Paciente · Nombres'], 'apellidos'=>['pp.apellidos','Paciente · Apellidos'],
        'edad'=>['extract(year from age(current_date,p.fecha_nacimiento))::int','Paciente · Edad'],
        'sexo'=>['p.sexo','Paciente · Sexo'],'correo'=>['u.correo','Paciente · Correo'],'telefono'=>['pp.telefono','Paciente · Teléfono'],
        'medico_codigo'=>['m.codigo_medico','Médico · Código'],'medico'=>["pm.nombres || ' ' || pm.apellidos",'Médico · Nombre'],
        'matricula'=>['m.nro_matricula','Médico · Matrícula'],'especialidad'=>['e.nombre','Especialidad · Nombre'],
        'codigo'=>['c.codigo_cita','Cita · Código'],'fecha'=>['c.fecha_cita','Cita · Fecha'],'hora'=>['c.hora_inicio','Cita · Hora'],
        'estado'=>['c.estado','Cita · Estado'],'creacion'=>['c.fecha_registro','Cita · Creación'],
        'monto'=>['pg.monto','Pago · Monto'],'estado_pago'=>['pg.estado_pago','Pago · Estado'],
    ];
    public const TYPES=['fecha'=>'Citas por fecha','medico'=>'Citas por médico','especialidad'=>'Citas por especialidad','estado'=>'Citas por estado','pacientes'=>'Padrón demográfico','consolidado'=>'Consolidado de citas','agenda'=>'Agenda diaria'];
    public function query()
    {
        return DB::table('cita_medica as c')->join('paciente as p','p.id_paciente','=','c.id_paciente')->join('persona as pp','pp.id_persona','=','p.id_paciente')->join('medico as m','m.id_medico','=','c.id_medico')->join('persona as pm','pm.id_persona','=','m.id_medico')->join('especialidad as e','e.id_especialidad','=','c.id_especialidad')->leftJoin('pago as pg','pg.id_cita','=','c.id_cita')->leftJoin('usuario as u','u.id_persona','=','p.id_paciente');
    }
    public function qbe(array $d,?int $doctor=null): array
    {
        $fields=$d['campos']??[];
        if(!$fields || array_diff($fields,array_keys(self::FIELDS))) throw ValidationException::withMessages(['campos'=>'Seleccione al menos una columna permitida.']);
        $q=$this->query();if($doctor) $q->where('c.id_medico',$doctor);
        foreach($fields as $field) $q->selectRaw(self::FIELDS[$field][0].' AS "'.$field.'"');
        foreach($d['filtros']??[] as $filter) {
            if(($filter['valor']??'')==='') continue;
            $col=$filter['campo']??'';$op=$filter['operador']??'=';
            if(!isset(self::FIELDS[$col]) || !in_array($op,['=','!=','>','>=','<','<=','ilike'],true)) throw ValidationException::withMessages(['filtros'=>'Filtro no permitido.']);
            $value=$filter['valor'];
            if(!is_scalar($value) || mb_strlen((string)$value)>200) throw ValidationException::withMessages(['filtros'=>'Valor de filtro inválido.']);
            if(in_array($col,['edad','monto']) && (!is_numeric($value) || $op==='ilike')) throw ValidationException::withMessages(['filtros'=>'Edad y monto requieren valores numéricos.']);
            if(in_array($col,['fecha','creacion']) && !strtotime((string)$value)) throw ValidationException::withMessages(['filtros'=>'Ingrese una fecha válida.']);
            $q->whereRaw(self::FIELDS[$col][0].' '.$op.' ?',[$op==='ilike'?'%'.$value.'%':$value]);
        }
        $order=$d['orden']??'fecha';if(!isset(self::FIELDS[$order])) throw ValidationException::withMessages(['orden'=>'Orden no permitido.']);
        $q->orderByRaw(self::FIELDS[$order][0].' ASC')->limit(1001);
        $sql=$q->toSql();$bindings=$q->getBindings();
        $ownsTransaction=DB::transactionLevel()===0;
        $rows=DB::transaction(function() use($sql,$bindings,$ownsTransaction) {
            // Do not change the mode of a caller's existing transaction.
            if($ownsTransaction) DB::statement('SET TRANSACTION READ ONLY');
            DB::statement("SET LOCAL statement_timeout = '5000ms'");
            return DB::select($sql,$bindings);
        });
        if(count($rows)>1000) throw ValidationException::withMessages(['filtros'=>'El informe supera 1000 filas. Añada filtros para acotar el resultado.']);
        return ['columns'=>array_combine($fields,array_map(fn($f)=>self::FIELDS[$f][1],$fields)),'rows'=>$rows,'sql'=>$sql,'bindings'=>$bindings,'totals'=>[]];
    }
    public function static(array $d): array
    {
        $type=$d['tipo'];$q=$this->query();
        $q->whereBetween('c.fecha_cita',[$d['desde'],$d['hasta']]);
        if(!empty($d['id_medico'])) $q->where('c.id_medico',$d['id_medico']);
        if(!empty($d['id_especialidad'])) $q->where('c.id_especialidad',$d['id_especialidad']);
        if(!empty($d['estado'])) $q->where('c.estado',$d['estado']);
        if($type==='pacientes') {
            $q=DB::table('paciente as p')->join('persona as pp','pp.id_persona','=','p.id_paciente')->whereBetween(DB::raw('p.fecha_registro::date'),[$d['desde'],$d['hasta']]);
            if(!empty($d['id_medico']) || !empty($d['id_especialidad']) || !empty($d['estado'])) $q->whereExists(function($sub) use($d) {$sub->selectRaw('1')->from('cita_medica as c')->whereColumn('c.id_paciente','p.id_paciente');foreach(['id_medico','id_especialidad','estado'] as $k) if(!empty($d[$k])) $sub->where('c.'.$k,$d[$k]);});
            $rows=$q->selectRaw("p.codigo_paciente AS codigo, pp.ci, pp.nombres || ' ' || pp.apellidos AS paciente, p.sexo, extract(year from age(current_date,p.fecha_nacimiento))::int AS edad, p.estado")->orderBy('pp.apellidos')->limit(1001)->get()->all();
        } elseif($type==='consolidado' && empty($d['id_medico']) && empty($d['id_especialidad']) && empty($d['estado'])) $rows=DB::select('SELECT * FROM fn_reporte_consolidado_citas(?::date, ?::date)',[$d['desde'],$d['hasta']]);
        elseif(in_array($type,['medico','especialidad','estado','consolidado'])) {
            $group=match($type){'medico'=>"pm.nombres || ' ' || pm.apellidos",'estado'=>'c.estado',default=>'e.nombre'};
            $q->selectRaw($group.' AS grupo, count(*) AS total, count(*) FILTER (WHERE c.estado = ?) AS atendidas, count(*) FILTER (WHERE c.estado = ?) AS canceladas, count(*) FILTER (WHERE c.estado = ?) AS inasistencias, coalesce(sum(pg.monto) FILTER (WHERE pg.estado_pago = ?),0) AS recaudado',['Atendida','Cancelada','Inasistencia','Pagado']);
            $rows=$q->groupByRaw($group)->orderBy('grupo')->limit(1001)->get()->all();
        } else {
            if($type==='fecha') $q->where('c.estado','Programada');
            $rows=$q->selectRaw("c.codigo_cita AS codigo,c.fecha_cita AS fecha,c.hora_inicio AS hora,pp.nombres || ' ' || pp.apellidos AS paciente,pm.nombres || ' ' || pm.apellidos AS medico,e.nombre AS especialidad,c.estado")->orderBy('c.fecha_cita')->orderBy('c.hora_inicio')->limit(1001)->get()->all();
        }
        if(count($rows)>1000) throw ValidationException::withMessages(['desde'=>'El informe supera 1000 filas. Reduzca el período.']);
        $columns=$rows?array_combine(array_keys((array)$rows[0]),array_map(fn($k)=>ucfirst(str_replace('_',' ',$k)),array_keys((array)$rows[0]))):['resultado'=>'Resultado'];
        $totals=[];foreach(array_keys($columns) as $k) if(str_starts_with($k,'total') || in_array($k,['atendidas','canceladas','inasistencias','recaudado'])) $totals[$k]=collect($rows)->sum($k);
        return compact('columns','rows','totals');
    }
}
