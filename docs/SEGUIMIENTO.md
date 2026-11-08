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
