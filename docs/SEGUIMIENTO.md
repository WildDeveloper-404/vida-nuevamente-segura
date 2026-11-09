# Seguimiento del proyecto

Actualizado: 25 de octubre de 2026 (America/Caracas).

Punto de partida: commit local `ea623aa` — `checkpoint inicial: migracion laravel 12 y citas medicas`.

## Estado final de esta reanudación

Los tres puntos solicitados quedaron completados. No se modificaron controladores, servicios, modelos, rutas, esquema SQL ni arquitectura. Los únicos archivos de entrega modificados en esta reanudación son este seguimiento y el informe de pruebas `docs/pruebas.xml`.

| Comprobación | Resultado |
| --- | --- |
| Resultado de `.tools/refine_views.py` | Verificado: todos los cambios esperados están presentes, sin duplicaciones. |
| Sintaxis de `public/js/reprogramar.js` | `node --check` finalizó correctamente. |
| Compilación de vistas Blade | `artisan view:cache` finalizó correctamente. |
| Reservas simultáneas | Dos procesos, una reserva aceptada, otra rechazada y una sola cita persistida. |
| Suite de regresión | **29 pruebas y 166 aserciones aprobadas**, sin errores ni fallos. |

## Verificación de `refine_views.py`

Se inspeccionaron el script y los cuatro archivos que modifica, comprobando mediante aserciones:

- Pacientes: correo, contraseña y confirmación obligatorios para el registro público; opcionales para el registro sin cuenta desde recepción.
- Usuarios: contraseña y confirmación obligatorias al crear; opcionales al editar.
- Médicos: contraseña y confirmación obligatorias al crear; opcionales al editar.
- Kardex: formulario de reprogramación conectado al endpoint de bloques, contenedor de horarios y mensaje de disponibilidad; eliminado el campo de hora libre anterior.
- Una sola inclusión de `reprogramar.js` y una sola instancia de los atributos de enlace en la plantilla del formulario.

El script ya había concluido antes del checkpoint. No fue necesario ejecutarlo otra vez. **No es idempotente**: repetirlo puede duplicar los atributos del formulario y la inclusión del JavaScript. Para verificar el estado actual, comprobar los archivos resultantes y compilar las vistas.

## Prueba de concurrencia

Comando ejecutado desde la raíz del proyecto:

```powershell
& '.tools/php/php.exe' tests/concurrency.php
```

Resultado (código de salida 0):

```text
OK: dos procesos simultáneos; una reserva creada, una rechazada; una sola cita persistida.
```

El ensayo inicia dos procesos PHP independientes con una barrera temporal común. Ambos solicitan el mismo médico, fecha y hora mediante `CitaService`. Verifica las respuestas `created` y `rejected`, además de contar exactamente una cita para ese médico y fecha.

Se ejecutó en la base aislada `clinica_test`, PostgreSQL local en `127.0.0.1:55432`. El script exige que el nombre de la base termine en `_test`. Los registros sintéticos de este ensayo permanecen en esa base de pruebas; no se alteraron bases externas al entorno de prueba del proyecto.

## Regresión y evidencia

```powershell
node --check public/js/reprogramar.js
& '.tools/php/php.exe' artisan view:cache
& '.tools/php/php.exe' vendor/bin/phpunit --testdox --log-junit docs/pruebas.xml
```

Resultado final: `OK (29 tests, 166 assertions)`. Duración de la suite: 55,662 segundos. Entorno: PHP 8.3.36 y PHPUnit 11.5.57. El informe JUnit actualizado está en [pruebas.xml](pruebas.xml).

La suite cubre, entre otros flujos, las diez pruebas mínimas, autenticación y bloqueo, aislamiento de pacientes y médicos, reprogramación, alergias, receta PDF/QR, comprobantes, idempotencia de pagos y webhooks, contingencias con reversión completa, derivaciones, QBE parametrizado, permisos y renderizado de páginas/exportaciones.

Incidencia de entorno resuelta: PostgreSQL estaba detenido al reanudar. La primera ejecución produjo errores de conexión, no resultados funcionales válidos. Se inició el clúster existente `.tools/pgdata`, se verificó que aceptara conexiones y se repitieron las pruebas. El XML conserva el resultado final aprobado.

## Límites del cierre

- Este cierre corresponde a las verificaciones solicitadas y su documentación; no implica una publicación en producción.
- Los pagos reales de Stripe, el envío real por SMTP y las respuestas reales de Gemini requieren sus credenciales y comprobación con esos servicios. Las pruebas automatizadas verifican eventos firmados de prueba y respuestas simuladas de IA.
- No se realizaron refactorizaciones, formateos masivos, nuevas actualizaciones de dependencias ni cambios arquitectónicos en esta reanudación.

## Revisión de la base para los 18 casos de uso — 7 de noviembre de 2026

Se revisaron las tablas, triggers y procedimientos actuales contra CU01–CU18 y
los RF01–RF27. El esquema tiene 25 tablas: 24 originales y `devolucion_pago`.
La correspondencia de cada caso con sus tablas y la guía de instalación están en
[database/README.md](../database/README.md).

Cambios de esta revisión:

- La instalación completa usa una transacción y un bloqueo compartido con el
  actualizador. Si falla una etapa, no queda un esquema parcialmente instalado.
- `clinica:compatibilidad --moneda-anterior=usd` explicita la moneda de datos
  antiguos sin moneda almacenada. También admite `bob`. No convierte importes
  ni sobrescribe monedas que ya existían.
- `08_integridad_agendas.sql` agrega exclusiones de intervalos por médico en
  turnos y citas, además de índices para Kardex, agenda, derivaciones y auditoría.
  Complementa las exclusiones por consultorio de la ampliación 07.
- Se conserva `04_datos.txt` como referencia anterior y se documenta por qué
  no se importa automáticamente. El sembrado actual usa catálogos base y
  `clinica:datos-ejemplo`.

Antes de actualizar la base local se generó el respaldo
`.tools/clinica_db-antes-revision-esquema-20261107.dump`. Después de actualizar,
`clinica_db` conservó las **14 citas y los 4 pagos**, con las cuatro exclusiones
de médico/consultorio presentes. No se cargaron nuevos ejemplos en esa base.

Validación:

- Instalación desde cero en `clinica_revision_20261107_test`: 25 tablas,
  4 roles y 8 especialidades.
- Suite completa: **45 pruebas y 235 aserciones**, sin fallos, en 37,353 segundos.
  Evidencia: [pruebas.xml](pruebas.xml).
- Dos procesos independientes reservaron simultáneamente el mismo cupo:
  una reserva creada, una rechazada y una sola cita persistida.
- Tres pruebas nuevas verifican que repetir la actualización no altera la
  reserva ni su moneda, y que las restricciones de exclusión bloquean
  superposiciones incluso sin el trigger de validación del médico.
- En `clinica_instalacion_20261107_test` se inyectó un fallo después de crear
  las tablas: la transacción las revirtió; el reintento instaló correctamente;
  una nueva instalación sobre ese esquema fue rechazada sin borrarlo.

Esta revisión valida el soporte de datos y la regresión de la aplicación. Sigue
pendiente completar el pago de extremo a extremo con Stripe Test y su webhook;
las pruebas automáticas de Stripe utilizan respuestas simuladas y eventos de prueba.

## Integraciones reales de prueba — 8 de noviembre de 2026

Se completó el flujo de Stripe Test desde el navegador: reserva CM-0015,
pago de 100 BOB, webhooks auténticos, comprobante REC-01-00000005, cancelación
y devolución íntegra. La API de Stripe confirmó pago y devolución `succeeded`,
con `livemode=false`. El historial local conserva 15 citas y 5 pagos.

El programador despachó dos avisos técnicos por Brevo en una base aislada, sin
repetirlos al ejecutar nuevamente el comando. Se detuvo el trabajador de prueba.
El usuario confirmó la llegada de ambos correos el 8 de noviembre de 2026.

Se probó Gemini real con `gemini-3.5-flash` y razonamiento `high`, tanto la
planificación como el reporte escrito desde la pantalla del médico. Se corrigió
la normalización de plurales de estados y la persistencia de `correo_contacto`
en el modelo de paciente. Se verificaron manualmente los portales de Paciente
y Médico y la respuesta de especialidades de Angélica.

La regresión final aprobó **47 pruebas y 238 aserciones**. La prueba con micrófono,
la revisión visual completa de los otros dos perfiles y la activación permanente
del programador sobre la base de uso habitual siguen pendientes. Detalles,
condiciones y capturas en [INTEGRACIONES_LOCALES.md](INTEGRACIONES_LOCALES.md).

### Seguimiento del micrófono

Tras el aviso del usuario se reprodujo un error `network` del reconocimiento
de voz en el navegador integrado. Se añadieron mensajes específicos y correcciones
del ciclo de inicio, parada y reintento, con seis pruebas JavaScript aprobadas.
La consulta escrita con Gemini sigue validada; la transcripción física queda
en ese momento pendiente de repetir con el usuario en Chrome o Edge. Detalles y captura en
[INTEGRACIONES_LOCALES.md](INTEGRACIONES_LOCALES.md#diagnóstico-de-captura-de-voz--8-de-noviembre-de-2026).

El 8 de noviembre de 2026 el usuario confirmó que la prueba posterior con voz
funcionó y devolvió una cita cancelada de Lucía. Se considera aprobado el recorrido
de micrófono, transcripción y reporte por confirmación del usuario. Sigue documentada
la incidencia de conexión `network` del navegador integrado. Quedan la revisión
visual completa de Administrador y Recepción y la activación permanente del
programador sobre la base de uso habitual.

### Corrección de reportes y ampliación de voz a Recepción

El 8 de noviembre se corrigió el filtro oculto de Programada de **Citas por fecha**.
Se verificaron la cita cancelada CM-0015 y las descargas PDF/Excel desde el
Administrador. Se habilitó voz para Recepción en rutas, menú y permiso de la base
local. Administrador y Recepción pueden consultar el alcance administrativo;
Médico conserva exclusivamente su actividad. Se comprobó también la revocación
del permiso y el rechazo de Paciente. La suite final aprobó **50 pruebas y
278 aserciones** en una instalación nueva; [pruebas.xml](pruebas.xml).

### PHP 8.5 y contraseñas Argon2id

El 8 de noviembre de 2026 se actualizó el contenedor de PHP 8.3 a PHP 8.5.12.
Las contraseñas nuevas usan Argon2id con 64 MiB, cuatro iteraciones y un hilo.
Las cuentas bcrypt existentes se verifican con su algoritmo original y se
actualizan a Argon2id tras un acceso correcto, bajo el bloqueo de la transacción
de autenticación. La sesión se crea usando el hash actualizado.

La imagen compiló desde el archivo de dependencias existente y pasó
`composer check-platform-reqs --no-dev`. `/up` y `/login` respondieron HTTP 200
en local. La suite completa en PHP 8.5.12 y una base PostgreSQL aislada pasó
54 pruebas y 297 aserciones. Incluye contraseña incorrecta sin modificación
del hash, migración desde bcrypt y continuidad del acceso al panel.

Railway desplegó correctamente el cambio `093046c`. El administrador autorizado
inició sesión con su contraseña existente y abrió el panel público con HTTP 200.
La verificación posterior en Supabase confirmó Argon2id en su `password_hash` y
cero cuentas pendientes de migración. El esquema de la demo tiene 25 tablas con
RLS activo y ocho especialidades. Las integraciones externas y el programador
de tareas todavía requieren la validación desde el entorno de nube.

## Acceso y perfiles de presentación en nube — 8 de noviembre de 2026

Se publicó manualmente en Railway el despliegue 5b9159b9-35e1-4bbd-8d7c-1311fda411d5, preparado desde la rama despliegue-supabase con los cambios de política de contraseña del espacio de trabajo. Estos cambios siguen pendientes de commit y publicación en GitHub.

Las altas, ediciones de contraseña y restablecimientos exigen ocho caracteres, minúscula, mayúscula, número y un carácter de puntuación o símbolo. Los formularios muestran la misma regla y el acceso conserva la compatibilidad con hashes existentes. La suite local aprobó 59 pruebas y 369 aserciones. La web pública respondió HTTP 200 en el formulario de restablecimiento y rechazó una contraseña débil con HTTP 422.

La cuenta de administrador autorizada se verificó con su nueva contraseña y Argon2id en Supabase. Se crearon tres cuentas ficticias para Recepcionista, Médico y Paciente, con ficha y perfil clínico para el paciente, un médico de Medicina General, un consultorio, siete turnos semanales y dos citas de demostración para el 10 de noviembre de 2026. Una cita está Programada y otra Cancelada. Los cuatro perfiles abrieron sus respectivos paneles públicos con HTTP 200. Las credenciales de demostración están en el archivo local Accesos_presentacion_VNS.txt de Descargas.

Brevo confirmó una clave API válida y un remitente activo. Se habilitó MAIL_MAILER=brevo en Railway. La recuperación conserva el enlace de un solo uso con token aleatorio de 64 caracteres, huella SHA-256 en la base y vigencia de 30 minutos. Se solicitó la recuperación desde el formulario público de Railway y se verificó un token nuevo en Supabase. Brevo registró el evento delivered para el correo de recuperación.

Los avisos de las citas ficticias dirigidos a example.test quedaron anulados para impedir su despacho. El programador de tareas y el ensayo de cobro completo sobre Railway siguen pendientes del cierre de la presentación.

## Pruebas CU01–CU18 — 8 de noviembre de 2026

Se ejecutó la suite sobre PostgreSQL local en la base aislada
`clinica_documentacion_20261108_test`, con PHP 8.3.36 y PHPUnit 11.5.57:
**67 pruebas y 442 aserciones, sin fallos, errores ni pruebas omitidas**.
La ejecución inicial tenía 59 pruebas; se agregaron ocho pruebas de aceptación
para permisos por HTTP, filtros de bitácora y Excel, edición de especialidad,
turnos con reservas, inasistencia a los 30 minutos, cierre de derivación,
totales separados por moneda y QBE con exportación y resultado vacío.

La reserva concurrente en dos procesos aprobó: una creada, una rechazada y una
sola cita persistida. Las seis pruebas JavaScript de la captura de voz también
aprobaron. La compilación de vistas Blade y `git diff --check` no dieron errores.

Sobre el dominio público de Railway se comprobaron los cuatro accesos, las
pantallas de cada perfil, restricciones entre roles, los siete reportes y sus
descargas, QBE y privacidad de Angélica. Gemini real generó un reporte médico
con las dos citas del profesional ficticio; PDF y Excel descargaron correctamente.
El resultado final de las sondas es **60 comprobaciones aprobadas**. Dos entradas
de la sonda inicial se corrigieron y repitieron: token CSRF renovado tras
invalidación de sesión anónima e inclusión de `id_paciente` en la consulta de
derivaciones. Se conserva evidencia inicial y de repetición.

La [matriz de pruebas CU01–CU18](PRUEBAS_CU01_CU18.md) detalla escenarios,
entornos, archivos de pruebas y límites de la validación. Las evidencias están
en `docs/evidencias/cu18-20261108-*` y no contienen credenciales.

No se ejecutó la suite de escritura sobre Supabase. El cobro completo de Stripe
Test y su devolución en Railway, la observación de un recordatorio automático,
la captura física de voz y el escaneo físico de QR siguen pendientes de ensayo.
Las diferencias de alcance del documento original permanecen en
`AUDITORIA_CASOS_DE_USO.md`; aprobar los recorridos implementados no las resuelve.
Se mantuvo la rama `despliegue-supabase`, sin commit, push ni nuevo despliegue.

### CU12: receta en el perfil, sin escaneo

Por solicitud del usuario se retiró el escaneo de receta de los pendientes de
aceptación y el QR del PDF. El perfil clínico ahora lista recetas con fecha,
médico, especialidad, diagnóstico, medicamentos/exámenes, dosis, frecuencia,
duración y descarga PDF. El paciente ve sus recetas; el médico únicamente las
emitidas en sus atenciones. Se mantienen las comprobaciones de integridad y
el verificador legado para compatibilidad con documentos anteriores.

La suite posterior aprobó **69 pruebas y 461 aserciones**, incluidas dos pruebas
nuevas del perfil, privacidad entre pacientes y médicos, PDF sin QR y estado
sin recetas. Evidencia: `docs/evidencias/recetas-perfil-20261108.xml`.
Blade compiló correctamente. Cambios locales en `despliegue-supabase`, pendientes
de commit y despliegue; todavía no están publicados en Railway.

### Despliegue de recetas en perfil y comprobación pública

Se publicó por autorización del usuario el despliegue Railway
`9d709dc8-0774-4468-a4e5-f1d3da34ce31`, con estado `SUCCESS`, sobre el servicio
existente en `production`. Se preparó una copia de Git con los archivos de
aplicación modificados, sin incluir archivos de entorno, credenciales ni datos
locales ajenos al cambio. No se hizo commit ni push. El conector de Railway
denegó acceso al proyecto; se utilizó la terminal previamente autenticada.

El proceso arrancó con PHP 8.5.12; `/up` devolvió HTTP 200 y los cuatro perfiles
abrieron sus paneles públicos con HTTP 200. Desde Edge se inició sesión como
paciente ficticio y se abrió `/paciente/perfil`, que redirigió a su perfil propio.
La interfaz accesible muestra la nueva sección «Recetas médicas» y el mensaje
«Todavía no hay recetas emitidas para mostrar», coherente con los datos de Lucía.
No se guardó la contraseña en el navegador ni se modificaron citas o recetas
de Supabase para esta revisión.

La captura gráfica de la ventana agotó el tiempo de espera; la navegación y la
verificación se realizaron con teclado y árbol de accesibilidad. No se declara
aprobación visual completa. La descarga de una receta con contenido desde nube
requiere emitir una receta sobre una atención ficticia completada. La generación
y la privacidad con contenido ya aprobaron las pruebas locales anteriores.

### Ensayo de cierre en Railway

El despliegue `faa4ec21-6a78-4184-9b93-f5f3df085237` inició Apache y el
programador de Laravel en producción. Los logs muestran las ejecuciones
automáticas de avisos y devoluciones. El recordatorio de demostración de la
cita CM-0001 quedó registrado como Enviado y Brevo confirmó una entrega.

En la cita ficticia CM-0003, Stripe Test confirmó Checkout completo por 100 BOB.
El sistema registró un solo pago y el comprobante REC-01-00000025 se descargó
como PDF con HTTP 200. Se canceló la cita de prueba y el programador procesó la
devolución. Stripe confirmó `succeeded` y la aplicación registró `Devuelto`.

La regresión local aprobó 73 pruebas PHP, 487 aserciones y 10 pruebas JavaScript.
El usuario confirmó que la consulta dictada produjo la transcripción y el
reporte esperado en Edge y Brave.
