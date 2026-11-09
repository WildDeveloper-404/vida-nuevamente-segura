# Auditoría de los 18 casos de uso

Revisión para la presentación del proyecto. Se compararon los actores, flujos,
excepciones y resultados de la [especificación de casos de uso](casos-de-uso.txt)
con los [requisitos actualizados](REQUISITOS_FUNCIONALES.md), las rutas, los
controladores, las vistas, las reglas SQL y las pruebas. Las diferencias se
distinguen de las decisiones acordadas durante el desarrollo. La revisión de
código y la evidencia local se registran por separado de la prueba en nube.

La presentación usa datos clínicos y cobros simulados. Sebastián es el titular
del único acceso real previsto. El administrador existente en local y las
cuentas de demostración pertenecen al entorno local. La base nueva de Supabase
se prepara con un administrador y con los datos ficticios necesarios para cada
escenario. Véase [la guía de despliegue](DESPLIEGUE_DEMO.md).

## Matriz de revisión

| Caso y requisitos actuales | Implementación y evidencia local | Diferencia, criterio pendiente o prueba de aceptación |
| --- | --- | --- |
| **CU01. Acceso, menús y recuperación** · RF01–02 | Cuatro perfiles, hash, bloqueo progresivo, cierre de sesión, enlace de un solo uso de 30 minutos. Hay pruebas de cuenta inactiva, sesión y recuperación. | En nube, entrar con la cuenta real de Administrador y abrir una ruta de otro perfil para comprobar la denegación. Verificar que el enlace de recuperación apunte al dominio HTTPS y que el correo llegue. Describir la contraseña como hash en la especificación. |
| **CU02. Usuarios, roles, módulos y permisos** · RF02–03 | Administración gestiona cuentas y permisos por rol. La revocación aplica en la petición siguiente. La cuenta médica se crea desde Médicos y la de paciente desde Pacientes o autorregistro. | Aclarar en el flujo desde qué pantalla se da de alta cada rol. Los cuatro roles son fijos. Se crean módulos y permisos, pero un permiso nuevo requiere vincularse a una ruta para controlar una función. Probar baja lógica de una cuenta ficticia y revocación de un permiso. |
| **CU03. Bitácora** · RF04 | Filtros, vista de diferencias, Excel y protección contra edición, borrado y truncado. Las mutaciones prueban actor y valores anterior/nuevo. | Correo y rol se obtienen de la cuenta actual al consultar, por lo que cambiar el rol puede alterar cómo se muestra un evento histórico. Decidir si el caso exige una copia del rol y correo al momento del evento. Mostrar en la demo una acción y su diferencia registrada. |
| **CU04. Médicos y especialidades** · RF05–06 | Alta médica con matrícula única, código correlativo, especialidades activas y baja lógica. Catálogo con rango de edad, arancel y control de duplicados por acentos o variantes fonéticas. | Al dar de baja al médico se avisa cuántas citas pendientes tiene. La reprogramación ordinaria conserva el mismo médico. Definir el recorrido para transferir esas citas a otro profesional o cancelarlas y volver a reservar, incluido qué ocurre con el pago. |
| **CU05. Horarios y disponibilidad** · RF07–08 | Turnos de 15, 20 o 30 minutos, consultorios, feriados y exclusión de solapes. Se bloquea la baja de un turno con reservas activas. | La especificación dice lunes a sábado y la app admite domingo. Acordar si documentamos domingo. También aclarar si el feriado se registra por médico, como ahora, o si se necesita un bloqueo de toda la clínica en una sola operación. |
| **CU06. Pacientes y autorregistro** · RF09–10 | Autorregistro, alta de ventanilla por Recepción y Administración, CI único, edad y código. Ventanilla puede registrar sin cuenta. Recepción puede editar y desactivar. | El documento da edición y baja también a Administración. En la app ese perfil sólo lista y registra. La búsqueda admite CI o apellido, mientras que el caso menciona también nombre y correo. El formulario de edición tampoco cambia el correo de contacto. Confirmar qué acciones requiere Administración y si el correo de contacto sin cuenta puede compartirse. |
| **CU07. Reservar, reprogramar y cancelar** · RF12–15 | Reserva transaccional, validación etaria y de horario, prueba de concurrencia, regla de 30 minutos, conservación del historial y del arancel. El pago se traslada al reprogramar. | Documentar los estados elegibles `Programada` y `Confirmada` y el límite de 30 minutos. En ventanilla la búsqueda rápida funciona por CI o apellido. La especificación menciona correo o nombre. Repetir una reserva con otro rol y demostrar la liberación del cupo tras cancelar. |
| **CU08. Kardex** · RF16 | Paciente ve su historial y Recepción accede desde el padrón. Filtros por fecha, especialidad y estado. El paciente ve recetas, comprobantes y devolución según su cita. | El caso incluye Kardex para el Médico y la fecha de registro de la cita. La app ofrece al Médico agenda y antecedentes autorizados, sin una pantalla de Kardex, y la tabla del historial no muestra la fecha de registro. Si se habilita, definir si el Médico ve sus propias atenciones o todo el historial de pacientes asignados. |
| **CU09. Contingencias y recordatorios** · RF17–18 | Traslado atómico hasta 30 días, avisos programados, respuesta del paciente y solicitud de devolución. La prueba con Brevo confirmó recepción de dos avisos. | Si faltan cupos, se revierte el traslado y la interfaz informa. El documento menciona reubicación manual. El sistema registra fallas de despacho SMTP, pero el rebote posterior y la marca de «aviso telefónico» del caso no tienen un flujo implementado. Definir si entran en el alcance. En la nube falta verificar el programador con un destinatario controlado. |
| **CU10. Perfil clínico** · RF11 | Paciente consulta antecedentes y edita contacto. El Médico asignado edita antecedentes y alergias. Recepción queda fuera del perfil clínico. | El caso mezcla Recepción y preferencias de privacidad o correo. Documentar qué datos se gestionan en la ficha administrativa y cuáles en el perfil clínico. Los campos del contacto de emergencia se validan por separado. El caso pide nombre y teléfono juntos cuando se informa un contacto. |
| **CU11. Agenda y estados** · RF19 | Agenda diaria y semanal, Médico limitado a su agenda, Recepción confirma, Médico marca atendida y ambos pueden marcar inasistencia desde los 30 minutos. Las transiciones inválidas se rechazan. | El caso pide bloques libres cuando la agenda está vacía. La pantalla de agenda lista citas y los bloques libres se consultan al reservar. Aclarar si la agenda necesita mostrar ambos. Para la demo, preparar un turno iniciado y otro con más de 30 minutos de retraso para probar ambos cambios. |
| **CU12. Receta y órdenes** · RF20–21 | Emisión tras finalizar atención, CIE-10 con validación de formato, control de alergia por principio activo, consulta y descarga PDF desde el perfil clínico del paciente, con control de integridad. | La regla acordada bloquea coincidencias de alergia, mientras el caso original menciona justificar una excepción. El formulario maneja medicamentos y exámenes de laboratorio. El texto incluye imagenología, presentación e indicaciones de preparación como datos separados. Definir cuáles se exigirán y si CIE-10 necesita catálogo. La consulta y descarga del PDF de demostración se comprobaron en Railway. |
| **CU13. Derivación** · RF22 | Prioridad Rutinaria, Prioritaria o Urgente, código de orden, estado pendiente, precarga, vínculo y cierre al atender. Sin médicos activos, se registra el pendiente y se avisa. | Actualizar la terminología Normal/Preferente del caso original. Detallar qué sucede si se cancela la cita de destino y qué prioridad implica sólo etiqueta o también reglas de agenda. Demostrar el estado final de una derivación enlazada. |
| **CU14. Pago de reserva** · RF23 | Tarjeta con Stripe Test y efectivo en caja, sin cobro por QR. Webhook firmado, cobro idempotente, comprobante PDF y devolución. Se probó en local el flujo completo de 100 BOB. | La especificación menciona enviar el comprobante por correo. La app muestra y descarga el PDF. Confirmar si el envío forma parte de la exposición. En nube, probar el webhook del dominio público y qué ve el paciente si Stripe rechaza, se abandona el pago o la confirmación tarda. |
| **CU15. Angélica** · RF24 | Orientación sobre horarios, especialidades y requisitos. Las citas personales se muestran al paciente autenticado. Prueba del rechazo de un documento ajeno. | Sustituir en el caso la consulta pública por CI por el flujo autenticado acordado. Mostrar en la demo el contraste entre la consulta general como visitante y «Mis citas» después de ingresar. |
| **CU16. Reportes administrativos** · RF25 | Siete opciones, filtros, tablas y exportación. Se verificó «Citas por fecha» con una cancelada y descarga PDF/Excel. | Actualizar el nombre y la regla del primer reporte. Ahora incluye todos los estados o el que seleccione el actor. Acordar qué entiende el evaluador por «consolidado» y validar que sus columnas y totales sean consistentes con filtros activos y con el rango sin filtros. |
| **CU17. QBE** · RF26 | Constructor para Administrador, columnas permitidas, filtros parametrizados y exportación. Hay prueba contra identificadores inyectados. | La lista original incluye grupo sanguíneo, motivo de consulta y diagnóstico. El catálogo actual no ofrece esas tres columnas. Confirmar si deben quedar fuera de las exportaciones o si el caso las exige para Administrador. Probar combinación de columnas, filtros y orden, incluido el resultado vacío. |
| **CU18. Reportes por voz** · RF27 | Administrador, Recepción y Médico. Gemini produce un plan de lectura validado, el Médico se limita a su actividad y las exportaciones requieren permiso. La web graba audio y Gemini devuelve una transcripción editable. En Railway se verificó el procesamiento de WebM, Ogg y MP4. | Agregar Recepción al documento original. Definir las columnas de Recepción y aclarar si «horarios disponibles» significa franjas semanales o cupos libres para una fecha, ya que la consulta de voz de horarios lista franjas. Probar voz y entrada escrita en el dominio HTTPS y usar un navegador donde se haya validado el micrófono. |

## Cierre de la presentación

1. **Resolver las diferencias que afectan al recorrido previsto.** Priorizar el
   alcance de Pacientes para Administración (CU06), el Kardex médico (CU08) y las
   columnas de reportes (CU17 y CU18). Si el evaluador exige que se muestre alguna
   de esas funciones, acordar el resultado antes de hacer el cambio.
2. **Preparar un conjunto ficticio pequeño.** Usar un médico con dos
   especialidades, un consultorio, un paciente con cuenta, otro sin cuenta y
   citas en estados distintos. Usar contraseñas únicas para los perfiles de la
   presentación. Conservar por separado la credencial del administrador real.
3. **Ensayar tres recorridos desde el enlace público.** Alta, horario y auditoría
   como Administrador. Registro, reserva, caja y comprobante como Recepción.
   Atención, receta en el perfil clínico, derivación y reporte por voz como Médico, con el portal
   del paciente para reserva, Kardex y Stripe Test. Los cobros en caja y tarjeta
   pueden mostrarse con citas diferentes.
4. **Verificar integraciones de nube.** Salud del servicio, login y enlaces HTTPS,
   acceso a Supabase, webhook firmado de Stripe, entrega de correo a un buzón
   controlado, programador durante el ensayo y Gemini. La aprobación local previa
   se conserva como evidencia de regresión.

Fuentes del código consultadas: [rutas](../routes/web.php),
[controladores](../app/Http/Controllers), [servicios](../app/Services),
[scripts PostgreSQL](../database/sql) y [pruebas de los flujos](../tests/Feature).
