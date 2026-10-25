<?php
return [
    'Administrador'=>[['admin.dashboard','Inicio','grid'],['admin.usuarios.index','Usuarios','people'],['admin.roles','Roles y permisos','shield-lock'],['admin.especialidades.index','Especialidades','heart-pulse'],['admin.medicos.index','Médicos','person-badge'],['admin.horarios','Horarios','calendar-week'],['admin.bitacora','Bitácora','journal-text'],['admin.reportes','Reportes','bar-chart'],['admin.qbe','Constructor QBE','table'],['admin.voz','Reportes por voz','mic']],
    'Recepcionista'=>[['recepcion.dashboard','Inicio','grid'],['recepcion.pacientes.index','Pacientes','people'],['recepcion.citas.create','Nueva cita','calendar-plus'],['recepcion.citas','Kardex de citas','journal-medical'],['recepcion.agenda','Agenda general','calendar-week'],['recepcion.contingencias','Contingencias','exclamation-circle'],['recepcion.reportes','Reportes','bar-chart']],
    'Medico'=>[['medico.dashboard','Mi consultorio','heart-pulse'],['medico.agenda','Mi agenda','calendar-week'],['medico.contingencias','Contingencias','exclamation-circle'],['medico.voz','Reportes por voz','mic']],
    'Paciente'=>[['paciente.dashboard','Mi portal','grid'],['paciente.citas.create','Reservar cita','calendar-plus'],['paciente.citas','Mis citas e historial','journal-medical'],['paciente.perfil','Mi perfil clínico','person-heart']],
];
