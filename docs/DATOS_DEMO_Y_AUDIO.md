# Datos para reportes y captura de audio compatible

Actualización: 8 de noviembre de 2026. Rama `despliegue-supabase`, sin commit.

## Datos de demostración

La carga añade personas identificadas con CI `DEMO-RPT-*` y citas con código
`DEMO-RPT-*`. Conserva las cuentas y contraseñas existentes. Los nuevos médicos
y pacientes no reciben cuentas adicionales.

- 18 pacientes ficticios nuevos, con perfiles clínicos y edades diversas.
- Cuatro médicos para reportes: Daniel, ya disponible para ingresar, y tres
  profesionales ficticios nuevos de Cardiología, Oftalmología y Traumatología.
- 48 citas: 16 Atendidas, 4 Inasistencias, 12 Canceladas, 8 Confirmadas y 8 Programadas.
- 24 pagos simulados en efectivo con comprobantes y referencia `DEMO-CAJA-*`.
  No se llama a Stripe ni se efectúan cobros.
- 16 recetas con diagnóstico y orden de laboratorio explícitamente ficticios.
- Cuatro derivaciones pendientes de programación.
- Fechas de citas: día de la carga y días +1, +3, +7, +12, +20 y +35.
  Las atenciones e inasistencias corresponden a turnos ya transcurridos de hoy.
- Los avisos pendientes de estas citas quedan anulados. No se envían mensajes a
  direcciones ficticias.

La carga es transaccional y usa códigos estables: repetirla conserva los registros
creados y no genera otras 48 citas. No desactiva triggers ni restricciones. Ajusta
la zona horaria sólo dentro de su transacción para respetar el día de la clínica.
Requiere ejecución después de las 12:00 del día local para las atenciones de hoy.

Comando de ejecución, usando las variables del entorno de destino autorizado:

```text
php artisan clinica:datos-reportes --confirmar-demo
```

Para ver todos los registros, seleccione en Reportes un intervalo que incluya
el día de la carga y los siguientes 40 días. Pruebe «Citas por estado», «Citas por
médico», «Citas por especialidad» y «Consolidado de citas». En el padrón puede
buscar «Reporte Simulado». El Médico conserva acceso sólo a su actividad.

Lucía, la paciente de acceso ya disponible, se incluye en la primera atención de
Daniel y recibe una receta de demostración, consultable y descargable desde su
perfil clínico.

## Audio en Edge, Brave y otros navegadores

El usuario informó que la captura anterior funcionaba en Chrome y fallaba en
Edge y Brave. La aplicación dependía de `SpeechRecognition`, cuya presencia no
garantiza un servicio operativo en cada navegador.

Ahora se prefiere `MediaRecorder` más `getUserMedia` en contexto seguro. El
navegador negocia un formato soportado entre WebM/Opus, Ogg/Opus y MP4. Si no
ofrece la grabación compatible, se conserva el reconocimiento anterior como
alternativa cuando esté disponible.

Recorrido del usuario:

1. Abrir Reportes por voz desde Administrador, Recepción o Médico.
2. Pulsar **Hablar** y permitir el micrófono si el navegador lo solicita.
3. Dictar una consulta breve y pulsar **Detener**. La grabación se detiene
   automáticamente al llegar a 30 segundos.
4. Esperar la transcripción, revisar el texto y pulsar **Consultar**.
5. Comparar los resultados y descargar PDF o Excel.

El audio se envía a Gemini a través del backend, con la clave existente en el
servidor. No se guarda como archivo permanente ni se registra su contenido binario.
El endpoint exige sesión, rol autorizado, permiso de voz, CSRF y límite de seis
solicitudes por minuto. Acepta como máximo 6 MiB y comprueba el tipo del archivo
en el servidor. El límite temporal de 30 segundos se aplica en la interfaz.
La transcripción se limita a 500 caracteres y no ejecuta consultas ni genera
reportes automáticamente. Se conserva el texto anterior ante denegación, audio
vacío, problemas de red o errores del proveedor.

La captura sigue dependiendo del permiso y de un micrófono funcional en HTTPS.
No se afirma compatibilidad universal con navegadores integrados o restricciones
del sistema operativo.

## Validación

Railway publicó el despliegue `88721d7c-09df-4def-b68d-61e000887261`. La carga de
Supabase se completó el 8 de noviembre y se comprobó desde la aplicación pública.
El CSV contiene las 48 citas ficticias. Lucía ve su receta en el perfil y el PDF
se descarga con HTTP 200. Consulte los [totales de la carga](evidencias/datos-reportes-nube-20261108.json)
y las [comprobaciones públicas](evidencias/reportes-perfil-nube-20261108.json).


- Suite completa: **73 pruebas PHP y 487 aserciones**, sin fallos ni omitidas.
  [JUnit](evidencias/audio-datos-demo-20261108.xml).
- **10 pruebas JavaScript**, incluidas negociación MP4, permiso denegado,
  parada a los 30 segundos, transcripción y conservación del texto ante fallo.
  [Salida](evidencias/audio-navegadores-20261108.txt).
- La carga se ejecutó dos veces dentro de una prueba aislada: conserva 48 citas,
  16 recetas y cero avisos pendientes para sus registros.
- Gemini real transcribió un audio de voz sintética con la consulta
  «Mostrar citas canceladas de este mes».
- El endpoint público autenticado transcribió correctamente el mismo audio en
  **WebM, Ogg y MP4**, todos con HTTP 200.
  [Resultados públicos](evidencias/audio-publico-20261108.json).
- El usuario confirmó la captura física y el resultado del reporte en Edge y Brave
  con la consulta «Mostrar citas canceladas de este mes».

Fuentes técnicas: [SpeechRecognition](https://developer.mozilla.org/en-US/docs/Web/API/SpeechRecognition/start),
[negociación de formatos](https://developer.mozilla.org/en-US/docs/Web/API/MediaRecorder/isTypeSupported_static)
y [audio en Gemini](https://ai.google.dev/gemini-api/docs/audio).
