<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AuthController,UsuarioController,RolController,BitacoraController,EspecialidadController,MedicoController,HorarioController,PacienteController,CitaController,HistorialCitaController,EmergenciaController,PerfilClinicoController,AgendaMedicaController,RecetaController,DerivacionController,PagoController,ChatbotController,ReporteController,QbeController,VozReporteController};

Route::redirect('/','/login');
Route::get('/login',[AuthController::class,'showLogin'])->name('login');
Route::post('/login',[AuthController::class,'login'])->middleware('throttle:10,1')->name('login.store');
Route::post('/logout',[AuthController::class,'logout'])->name('logout');
Route::get('/recuperar',[AuthController::class,'showRecuperar'])->name('password.request');
Route::post('/recuperar',[AuthController::class,'enviarTokenRecuperacion'])->middleware('throttle:3,1')->name('password.email');
Route::get('/restablecer/{token}',[AuthController::class,'reset'])->name('password.reset');
Route::post('/restablecer',[AuthController::class,'cambiarPasswordConToken'])->middleware('throttle:5,1')->name('password.update');
Route::get('/registro',[PacienteController::class,'create'])->name('registro');
Route::post('/registro',[PacienteController::class,'store'])->middleware('throttle:5,1')->name('registro.store');
Route::post('/stripe/webhook',[PagoController::class,'webhookStripe'])->name('stripe.webhook');
Route::post('/api/chatbot/mensaje',[ChatbotController::class,'procesarMensaje'])->middleware('throttle:30,1')->name('chatbot');
Route::get('/receta/verificar/{codigo}',[RecetaController::class,'verificar'])->middleware('throttle:30,1')->name('recetas.verificar');

Route::prefix('admin')->name('admin.')->middleware('rol:Administrador')->group(function() {
    Route::view('/','dashboard')->name('dashboard');
    Route::resource('usuarios',UsuarioController::class)->except('show')->middleware('permiso:usuarios');
    Route::get('roles',[RolController::class,'index'])->name('roles')->middleware('permiso:usuarios');
    Route::put('roles/{rol}',[RolController::class,'update'])->name('roles.update')->middleware('permiso:usuarios');
    Route::post('modulos',[RolController::class,'modulo'])->name('modulos.store')->middleware('permiso:usuarios');
    Route::post('permisos',[RolController::class,'permiso'])->name('permisos.store')->middleware('permiso:usuarios');
    Route::get('bitacora',[BitacoraController::class,'index'])->name('bitacora')->middleware('permiso:auditoria');
    Route::resource('especialidades',EspecialidadController::class)->only(['index','store','update'])->parameters(['especialidades'=>'especialidad'])->middleware('permiso:catalogos');
    Route::resource('medicos',MedicoController::class)->only(['index','store','update','destroy'])->middleware('permiso:catalogos');
    Route::get('horarios',[HorarioController::class,'index'])->name('horarios')->middleware('permiso:catalogos');
    Route::post('horarios',[HorarioController::class,'store'])->name('horarios.store')->middleware('permiso:catalogos');
    Route::delete('horarios/{horario}',[HorarioController::class,'destroy'])->name('horarios.destroy')->middleware('permiso:catalogos');
    Route::post('bloqueos',[HorarioController::class,'bloqueo'])->name('bloqueos.store')->middleware('permiso:catalogos');
    Route::post('consultorios',[HorarioController::class,'consultorio'])->name('consultorios.store')->middleware('permiso:catalogos');
    Route::get('reportes',[ReporteController::class,'index'])->name('reportes')->middleware('permiso:reportes');
    Route::get('qbe',[QbeController::class,'index'])->name('qbe')->middleware('permiso:qbe');
    Route::get('voz',[VozReporteController::class,'index'])->name('voz')->middleware('permiso:voz');
});
Route::prefix('recepcion')->name('recepcion.')->middleware('rol:Recepcionista')->group(function() {
    Route::view('/','dashboard')->name('dashboard');
    Route::get('pacientes/buscar',[PacienteController::class,'buscarPorCi'])->name('pacientes.buscar')->middleware('permiso:pacientes');
    Route::resource('pacientes',PacienteController::class)->except('show')->middleware('permiso:pacientes');
    Route::get('citas',[HistorialCitaController::class,'index'])->name('citas')->middleware('permiso:citas');
    Route::get('citas/nueva',[CitaController::class,'create'])->name('citas.create')->middleware('permiso:citas');
    Route::get('agenda',[AgendaMedicaController::class,'index'])->name('agenda')->middleware('permiso:agenda');
    Route::get('contingencias',[EmergenciaController::class,'index'])->name('contingencias')->middleware('permiso:contingencias');
    Route::get('reportes',[ReporteController::class,'index'])->name('reportes')->middleware('permiso:reportes');
});
Route::prefix('medico')->name('medico.')->middleware('rol:Medico')->group(function() {
    Route::get('/',[AgendaMedicaController::class,'index'])->name('dashboard');
    Route::get('agenda',[AgendaMedicaController::class,'index'])->name('agenda')->middleware('permiso:agenda');
    Route::get('contingencias',[EmergenciaController::class,'index'])->name('contingencias')->middleware('permiso:contingencias');
    Route::get('voz',[VozReporteController::class,'index'])->name('voz')->middleware('permiso:voz');
    Route::get('citas/{cita}/receta',[RecetaController::class,'create'])->name('receta')->middleware('permiso:clinico');
    Route::post('citas/{cita}/receta',[RecetaController::class,'store'])->name('receta.store')->middleware('permiso:clinico');
    Route::get('citas/{cita}/derivacion',[DerivacionController::class,'create'])->name('derivacion')->middleware('permiso:clinico');
    Route::post('citas/{cita}/derivacion',[DerivacionController::class,'store'])->name('derivacion.store')->middleware('permiso:clinico');
});
Route::prefix('paciente')->name('paciente.')->middleware('rol:Paciente')->group(function() {
    Route::view('/','dashboard')->name('dashboard');
    Route::get('citas',[HistorialCitaController::class,'index'])->name('citas')->middleware('permiso:citas');
    Route::get('citas/nueva',[CitaController::class,'create'])->name('citas.create')->middleware('permiso:citas');
    Route::get('perfil',fn()=>redirect()->route('perfil.show',session('id_persona')))->name('perfil')->middleware('permiso:clinico');
});
Route::middleware(['rol:Recepcionista,Paciente','permiso:citas'])->group(function() {
    Route::get('/citas/medicos',[CitaController::class,'medicos'])->name('citas.medicos');
    Route::get('/citas/bloques',[CitaController::class,'getBloquesDisponibles'])->name('citas.bloques');
    Route::post('/citas',[CitaController::class,'store'])->name('citas.store');
    Route::post('/citas/{cita}/cancelar',[CitaController::class,'cancelar'])->name('citas.cancelar');
    Route::post('/citas/{cita}/reprogramar',[CitaController::class,'reprogramar'])->name('citas.reprogramar');
});
Route::post('/agenda/{cita}/estado',[AgendaMedicaController::class,'estado'])->middleware(['rol:Recepcionista,Medico','permiso:agenda'])->name('agenda.estado');
Route::post('/contingencias',[EmergenciaController::class,'store'])->middleware(['rol:Recepcionista,Medico','permiso:contingencias'])->name('contingencias.store');
Route::middleware(['rol:Medico,Paciente','permiso:clinico'])->group(function() {
    Route::get('/perfil/{paciente}',[PerfilClinicoController::class,'show'])->name('perfil.show');
    Route::put('/perfil/{paciente}',[PerfilClinicoController::class,'update'])->name('perfil.update');
    Route::get('/recetas/{receta}/pdf',[RecetaController::class,'pdf'])->name('recetas.pdf');
});
Route::middleware(['rol:Recepcionista,Paciente','permiso:pagos'])->group(function() {
    Route::get('/pagos/{cita}',[PagoController::class,'checkout'])->name('pagos.checkout');
    Route::post('/pagos/{cita}/stripe',[PagoController::class,'iniciarPago'])->name('pagos.stripe');
    Route::post('/pagos/{cita}/presencial',[PagoController::class,'presencial'])->name('pagos.presencial');
    Route::get('/comprobantes/{pago}',[PagoController::class,'comprobante'])->name('pagos.comprobante');
});
Route::post('/reportes/voz',[VozReporteController::class,'procesarVoz'])->middleware(['rol:Administrador,Medico','permiso:voz','throttle:10,1'])->name('voz.procesar');
Route::get('/reportes/voz/exportar',[VozReporteController::class,'export'])->middleware(['rol:Administrador,Medico','permiso:voz'])->name('voz.export');
