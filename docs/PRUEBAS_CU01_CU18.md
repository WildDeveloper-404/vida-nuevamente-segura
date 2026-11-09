# Pruebas de los 18 casos de uso

Fecha: 8 de noviembre de 2026.

El ensayo posterior en Railway completó Stripe Test con un pago de 100 BOB,
comprobante PDF y devolución automática. Brevo confirmó una entrega del
recordatorio de demostración. El usuario verificó la captura física y el reporte
en Edge y Brave. La [evidencia del cierre](evidencias/cierre-railway-20261108.json)
complementa los resultados y pendientes de la ejecución inicial descritos abajo.

**Cambio posterior solicitado para CU12:** la receta se consulta desde el perfil
clínico y se descarga en PDF sin QR. El escaneo deja de ser un requisito de
aceptación. El paciente ve todas sus recetas; el médico ve las de sus propias
atenciones. Se agregó `tests/Feature/RecetasPerfilTest.php` para verificar
contenido, descarga, ausencia de QR, permisos y perfil sin recetas. La ejecución
posterior se conserva en [recetas-perfil-20261108.xml](evidencias/recetas-perfil-20261108.xml).
Las referencias al QR en la matriz y evidencia inicial describen el comportamiento
anterior; el verificador legado se conserva por compatibilidad.

Se ejecutaron pruebas sobre la implementación actual de CU01 a CU18. Los resultados
aprobados corresponden a los escenarios enumerados; no equivalen a cobertura total
del código ni a conformidad con todas las variantes del documento original.

## Entornos y alcance

- Rama: `despliegue-supabase`; base de Git: `7932eca`, con los cambios locales de
  política de contraseña y las pruebas nuevas. No se hizo commit ni push.
- Pruebas locales: PHP 8.3.36, PHPUnit 11.5.57 y PostgreSQL 18 en el puerto 55432.
  Base aislada: `clinica_documentacion_20261108_test`. No se ejecutó la suite
  destructiva sobre Supabase ni sobre la base habitual de la aplicación.
- Cada prueba de PHPUnit inicia una transacción y revierte sus escrituras.
  La prueba de concurrencia usa procesos distintos y deja sus datos ficticios
  únicamente en la base de pruebas. Las secuencias PostgreSQL pueden avanzar
  aunque se reviertan las transacciones.
- Pruebas públicas: [aplicación Railway](https://vida-nuevamente-segura-production.up.railway.app),
  conectada a Supabase, con los cuatro perfiles autorizados de presentación.
  Se comprobaron sesiones, páginas, controles de acceso, consultas y descargas.
  Los inicios de sesión y las consultas del asistente producen sus registros
  habituales; no se modificaron roles, citas o pagos de la demo pública.
- Stripe, Brevo y Gemini se simulan donde corresponde en PHPUnit. Adicionalmente
  se ejecutó una consulta real de Gemini desde el dominio público. El cobro
  completo con tarjeta y la entrega de correo no se repitieron en esta ejecución.

## Resultados de ejecución

| Comprobación | Resultado | Evidencia |
|---|---|---|
| Suite PHP de los flujos y reglas de negocio | 67 pruebas, 442 aserciones; cero errores, fallos y omitidas | [JUnit](evidencias/cu18-20261108-phpunit.xml), [salida detallada](evidencias/cu18-20261108-phpunit.txt) |
| JavaScript de captura de voz | 6 pruebas aprobadas | [TAP](evidencias/cu18-20261108-voz-js.txt) |
| Dos reservas simultáneas del mismo bloque | Una creada, una rechazada; una sola cita persistida | [Concurrencia](evidencias/cu18-20261108-concurrencia.txt) |
| Sondas públicas de Railway | 60 comprobaciones aprobadas tras corregir dos entradas de la sonda | [Resultados finales](evidencias/cu18-20261108-nube.json) |
| Compilación de vistas Blade | `artisan view:cache` aprobado | Ejecución local |
| Espacios y formato del diff | `git diff --check` sin errores | Ejecución local |

Las ocho pruebas nuevas están en
[CasosDeUsoAceptacionTest.php](../tests/Feature/CasosDeUsoAceptacionTest.php).
Amplían la cobertura de permisos por HTTP, bitácora filtrada, catálogo, turnos,
inasistencia, derivación completada, totales y QBE.

## Matriz CU01–CU18

En la columna «Resultado» se distingue la aprobación automatizada local de la
comprobación pública. «Local aprobado» incluye la base PostgreSQL real de pruebas;
no significa que todos los proveedores externos se hayan llamado realmente.

| Caso de uso y actor principal | Escenarios comprobados y resultado esperado | Resultado |
|---|---|---|
| **CU01. Acceso y recuperación**. Los cuatro perfiles | Acceso válido; sesión obligatoria; cuenta inactiva; bloqueo progresivo cada tres fallos con máximo de 16 horas; creación Argon2id y migración desde bcrypt; token almacenado como hash y de un solo uso; contraseña de ocho caracteres con las cuatro categorías obligatorias. Contraseñas débiles no deben cambiar el hash. | Local aprobado. Los cuatro perfiles ingresaron a Railway y abrieron sus paneles. Anónimo redirigido a login. Recuperación por Brevo usa un transporte simulado en esta suite. |
| **CU02. Usuarios, roles, módulos y permisos**. Administrador | Alta de usuario administrativo; alta médica con cuenta; baja lógica; revocar y restituir permisos por la ruta administrativa; denegación inmediata tras revocar; impedir vaciar los permisos del Administrador. | Local aprobado. Usuarios y roles abren en nube; Recepción, Médico y Paciente reciben 403 al intentar administrar usuarios. |
| **CU03. Bitácora**. Administrador | Registrar actor y valores anteriores/nuevos; rechazar modificación, borrado y truncado; filtrar por correo, rol, módulo, acción y fecha; exportar Excel; excluir los eventos que no coinciden; denegar Recepción. | Local aprobado. Pantalla pública HTTP 200. |
| **CU04. Médicos y especialidades**. Administrador | Crear médico con especialidad y cuenta; baja lógica también de su cuenta; editar arancel y rango etario; rechazar un rango invertido sin alterar el registro; impedir variantes fonéticas de especialidades existentes. | Local aprobado. Catálogos de médicos y especialidades públicos HTTP 200. |
| **CU05. Horarios y disponibilidad**. Administrador | Crear consultorio y turno; duración según el día; rechazar horas invertidas; impedir solapes del médico y del consultorio desde PostgreSQL; baja lógica de turno libre; impedir baja con reserva activa. | Local aprobado. Pantalla de horarios pública HTTP 200. |
| **CU06. Pacientes y autorregistro**. Paciente/Recepción | Registro atómico de persona, paciente, perfil y cuenta; contraseña con hash; CI duplicado sin registros parciales; paciente sin cuenta conserva correo y genera avisos pendientes. | Local aprobado. Registro público, padrón y formulario de Recepción HTTP 200. |
| **CU07. Reservar, reprogramar y cancelar**. Paciente/Recepción | Reservar bloque válido; rechazar fecha pasada, horario inválido y edad incompatible; reprogramación atómica enlazada a la cita anterior; cancelación conserva historial y libera cupo; límite de 30 minutos; colisión secuencial y simultánea. | Local aprobado, incluida la concurrencia real de dos procesos. Formularios públicos de reserva HTTP 200. No se crearon nuevas reservas en Supabase en esta ejecución. |
| **CU08. Kardex**. Paciente/Recepción | Mostrar la cita cancelada del paciente; ocultar citas ajenas; denegar cancelación de cita de otro paciente. | Local aprobado. Historial público del paciente y listado de Recepción HTTP 200. El Kardex específico para Médico sigue siendo una diferencia de alcance documentada. |
| **CU09. Contingencias y recordatorios**. Administrador/Recepción/Médico | Trasladar citas y generar avisos; ante falta de cupo, revertir bloqueo y traslados; recordatorio programado para un día antes; respetar ventana y no repetir despacho; respuesta del paciente y solicitud de devolución. | Local aprobado con correo simulado. Pantallas públicas de contingencias HTTP 200. El despacho automático periódico de Railway requiere el ensayo pendiente indicado abajo. |
| **CU10. Perfil clínico**. Paciente/Médico | Paciente actualiza contacto; únicamente médico asignado modifica alergias/antecedentes; rechazar acceso ajeno y acceso clínico de Recepción. | Local aprobado. Perfil propio del paciente en nube HTTP 200. |
| **CU11. Agenda y estados**. Médico/Recepción | Agenda del médico sin citas ajenas; estados terminales no reabren; confirmar desde Recepción; rechazar atención futura; permitir atención después del inicio; inasistencia rechazada a los 29 minutos y aceptada a los 30. | Local aprobado. Agendas públicas de Médico y Recepción HTTP 200. El límite temporal se probó con reloj controlado. |
| **CU12. Receta y órdenes**. Médico/Paciente | Emitir receta y orden tras atención; control de alergias; PDF con cabecera válida; QR/verificación de integridad pública; verificador sin revelar diagnóstico; autorización para receta y PDF. | Local aprobado. No se efectuó lectura física del QR con una cámara en esta ejecución. |
| **CU13. Derivación**. Médico/Paciente/Recepción | Crear derivación, mostrarla pendiente al reservar, enlazar cita destino, confirmar y atender desde los roles correspondientes; pasar de Programada a Completada únicamente al atender destino. | Local aprobado por las rutas HTTP, con reloj controlado para la atención. Consulta pública de derivaciones de Recepción con paciente válido HTTP 200 y JSON. |
| **CU14. Pago y comprobante**. Paciente/Recepción | Mantener arancel y moneda de la reserva; caja por importe pactado; impedir QR bancario; Checkout sólo tarjeta; validar firma e idempotencia de webhook; transferir pago al reprogramar; comprobante PDF; devolución de efectivo y Stripe sin repetición. | Local aprobado. Integración de Stripe simulada en la suite. Pago completo, webhook y devolución de Stripe en el despliegue actual siguen pendientes de ensayo real en modo Test. |
| **CU15. Angélica**. Visitante/Paciente | Orientación guiada con especialidades activas; enlace de reserva; mensaje de urgencia; visitante no obtiene citas personales; paciente sólo consulta sus citas. | Local aprobado. En Railway el visitante recibió indicación de iniciar sesión y el paciente obtuvo sólo su cita activa, sin incluir la cancelada. |
| **CU16. Reportes administrativos**. Administrador/Recepción | Siete tipos de reporte; cita cancelada incluida y filtro por estado respetado; descargas PDF/Excel/CSV; totales de tres citas con una cancelada; separar 100 BOB de 100 USD y excluir 100 BOB ya devueltos. | Local aprobado. Los siete tipos respondieron HTTP 200 en Railway; PDF, Excel y CSV descargados con contenido. |
| **CU17. QBE**. Administrador | Seleccionar columnas y orden; filtro exacto devuelve una cita; PDF/Excel/CSV; filtro inexistente muestra cero filas; exportar vacío responde 422; identificadores y valores de inyección no se ejecutan; Recepción recibe 403. | Local aprobado. Consulta pública de la cita cancelada y exportaciones PDF/Excel HTTP 200. |
| **CU18. Reportes por voz/texto**. Administrador/Recepción/Médico | Validar el plan de Gemini; normalizar estados; rechazar estados inventados y mutaciones; limitar Médico a sus citas; Administrador y Recepción consultan/exportan; revocación bloquea pantalla, consulta y exportación; Paciente recibe 403. Interfaz distingue falta de permiso, micrófono, red y navegador incompatible. | Local aprobado con Gemini simulado y seis pruebas JavaScript. Gemini **real** respondió HTTP 200 desde Railway: dos filas correspondientes exclusivamente al médico ficticio; PDF/Excel descargados. La captura física de voz no se repitió. |

## Trazabilidad de las pruebas

Los nombres completos de cada prueba y sus aserciones figuran en el JUnit. Los
archivos principales son:

| Archivo | Casos cubiertos |
|---|---|
| [ClinicaTest.php](../tests/Feature/ClinicaTest.php) | CU01–CU03, CU05–CU12 y CU14–CU18: reservas, privacidad, permisos, recuperación y reportes |
| [AdditionalFlowsTest.php](../tests/Feature/AdditionalFlowsTest.php) | CU02, CU04–CU05, CU07, CU09 y CU11–CU14: cuentas, bajas, reprogramación, PDF y derivación |
| [RequisitosFuncionalesTest.php](../tests/Feature/RequisitosFuncionalesTest.php) | CU03–CU05, CU06–CU07, CU09–CU10 y CU14: integridad, avisos, caja, arancel y devolución |
| [CasosDeUsoAceptacionTest.php](../tests/Feature/CasosDeUsoAceptacionTest.php) | CU02–CU05, CU11, CU13, CU16–CU17: rutas administrativas y de atención, límites y resultados |
| [IntegridadDatabaseTest.php](../tests/Feature/IntegridadDatabaseTest.php) | CU05, CU07 y CU14: exclusión aun sin validador PL/pgSQL y actualización compatible idempotente |
| [PasswordHashingTest.php](../tests/Feature/PasswordHashingTest.php) | CU01: Argon2id, migración y complejidad de contraseña |
| [ReportesAccesoTest.php](../tests/Feature/ReportesAccesoTest.php) | CU16 y CU18: filtros, exportación y alcance por rol |
| [ChatbotOrientationTest.php](../tests/Feature/ChatbotOrientationTest.php) | CU15: orientación, catálogo y urgencias |
| [VozNormalizacionTest.php](../tests/Unit/VozNormalizacionTest.php) | CU18: estados y moneda del plan |
| [voz.test.cjs](../tests/js/voz.test.cjs) y [concurrency.php](../tests/concurrency.php) | CU18: interfaz de voz; CU07: exclusión simultánea |

## Incidencias de preparación y correcciones

No se identificó un fallo funcional reproducible en los escenarios válidos
ejecutados. Sí se corrigieron tres errores en las comprobaciones nuevas:

1. La aserción inicial del CSV leía el cuerpo normal de una respuesta transmitida.
   Se cambió para comprobar `streamedContent()`, donde Laravel entrega el CSV.
2. La sonda anónima reutilizaba el token CSRF después de intentar entrar a `/admin`.
   El middleware invalida esa sesión y regenera el token. La sonda se repitió
   obteniendo antes el formulario de login y recibió la respuesta esperada.
3. La sonda de derivaciones omitía `id_paciente`, obligatorio para ese endpoint
   JSON. Se repitió con el paciente ficticio autorizado y respondió HTTP 200.

Se conservan los [resultados públicos iniciales](evidencias/cu18-20261108-nube-inicial.json)
y la [repetición de las dos sondas](evidencias/cu18-20261108-nube-repeticion.json).
El archivo de resultados finales sustituye esas dos comprobaciones por su
repetición válida; no implica otra ejecución completa de las 60 sondas.

## Ensayos pendientes para cerrar la aceptación de la nube

1. **CU14, Stripe Test:** reservar una cita ficticia, abrir Checkout, completar
   tarjeta de prueba, comprobar webhook/pago único y descargar comprobante;
   cancelar y verificar devolución. Ensayar también abandono y rechazo.
2. **CU09, programador:** observar una ejecución automática en Railway, con un
   recordatorio dirigido a un buzón controlado, verificar entrega y ausencia de
   duplicados. La prueba local del comando y la entrega previa de recuperación
   mediante Brevo no demuestran por sí solas la periodicidad del programador.
3. **CU12 y CU18, interfaz:** consultar y descargar la receta desde el perfil clínico y dictar una consulta
   desde Chrome/Edge en HTTPS con permiso de micrófono; comparar el resultado
   con las citas y abrir el archivo descargado. La consulta escrita a Gemini
   real ya se verificó en esta ejecución.
4. **Interfaz:** recorrer los formularios con teclado, revisar diseño móvil y
   legibilidad de los PDF. Las sondas HTTP y la compilación Blade comprueban
   respuesta y generación, sin sustituir una revisión visual.

Continúan las diferencias de especificación de
[AUDITORIA_CASOS_DE_USO.md](AUDITORIA_CASOS_DE_USO.md), entre ellas edición/baja de
pacientes por Administrador, Kardex específico para Médico y campos clínicos
adicionales del QBE original. Estas pruebas no añaden esas funciones ni las
declaran implementadas. Antes de afirmar conformidad completa con el Word/PDF,
debe acordarse ese alcance documental.

## Repetición local

Desde la raíz del proyecto, con PostgreSQL local iniciado y la configuración
de `.env.testing` disponible, seleccionar explícitamente la base aislada actual:

```powershell
$env:APP_ENV = 'testing'
$env:DB_DATABASE = 'clinica_documentacion_20261108_test'
& '.tools/php/php.exe' vendor/bin/phpunit --testdox --colors=never
& '.tools/php/php.exe' tests/concurrency.php
node tests/js/voz.test.cjs
& '.tools/php/php.exe' artisan view:cache
git diff --check
```

La configuración predeterminada de `.env.testing` apunta a `clinica_test`, que
contenía un esquema anterior; esta ejecución usó la base aislada actual indicada.
`Tests\TestCase` y la prueba concurrente rechazan bases sin sufijo `_test`.
Para otra base vacía destinada a pruebas, preparar antes el esquema con
`APP_ENV=testing`, el nombre de esa base en `DB_DATABASE` y
`artisan clinica:instalar --datos`. No usar las variables de producción.

Los resultados incluyen datos sintéticos y nombres de pruebas, sin claves API,
contraseñas, cookies ni tokens de sesión.
