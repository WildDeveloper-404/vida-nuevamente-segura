# Seguimiento de los requisitos funcionales RF01–RF27

Actualizado el 8 de noviembre de 2026. Los identificadores corresponden al archivo definitivo de 27 requisitos.

| RF | Criterio implementado y condiciones |
| --- | --- |
| RF01 | Inicio por correo, hash de contraseña, rutas protegidas, bloqueo progresivo y token de recuperación de 30 minutos. |
| RF02 | Menús y accesos filtrados por los permisos vigentes en cada petición. |
| RF03 | Administración de cuentas, roles, módulos y permisos con baja lógica. |
| RF04 | PostgreSQL registra cambios de las tablas de negocio en JSONB. Impide actualizar, borrar y truncar la bitácora. Incluye comparación y Excel. La bitácora reserva los valores personales, clínicos y secretos. |
| RF05 | Catálogo de especialidades con identificador, arancel y rango etario. PostgreSQL bloquea duplicados por mayúsculas, acentos y variantes fonéticas según reglas de escritura del español, como c/k y b/v. |
| RF06 | Padrón de médicos con matrícula y código únicos, vínculo a especialidades y baja lógica. |
| RF07 | Turnos semanales, bloques de 15, 20 o 30 minutos, cálculo de cupos y consultorio con piso. |
| RF08 | PostgreSQL rechaza la superposición del médico y la asignación simultánea de un consultorio en turnos activos. |
| RF09 | Autorregistro web y admisión por Recepción o Administración. En ventanilla se solicita correo y puede omitirse la contraseña para registrar sin cuenta. |
| RF10 | CI único, código correlativo y edad calculada a partir de la fecha de nacimiento. |
| RF11 | El paciente consulta sus antecedentes y edita teléfono, dirección y contacto de emergencia. El médico asignado edita antecedentes y alergias. |
| RF12 | Reserva transaccional con validación de edad, fecha, médico y cupo. Guarda el importe y la moneda pactados. |
| RF13 | Bloqueo por médico e índice parcial para la reserva. PostgreSQL también aplica exclusión de intervalos por médico y por consultorio, incluso cuando difiere la hora inicial. |
| RF14 | Reprogramación con al menos 30 minutos de antelación. Enlaza la cita anterior, conserva el arancel y traslada el pago sin cobro adicional. |
| RF15 | Cancelación con motivo y al menos 30 minutos. Libera el cupo y conserva el historial. Una cita pagada genera una devolución pendiente. |
| RF16 | Kardex por paciente con fechas, estados y motivos. El titular puede descargar sus recetas y ver el estado de la devolución. |
| RF17 | Administración puede declarar contingencias. El traslado atómico busca cupos hasta 30 días. El aviso lleva al Kardex para aceptar la fecha o solicitar devolución de una cita pagada. |
| RF18 | Recordatorio programado para 24 horas antes. Una reserva hecha con menos antelación queda lista para el siguiente despacho. Se anulan avisos obsoletos. |
| RF19 | Agenda diaria y semanal, estados por rol e inasistencia después de 30 minutos desde el inicio previsto. |
| RF20 | Diagnóstico y código con formato CIE-10. Se bloquea el principio activo si coincide con una alergia confirmada. La comparación normalizada no infiere equivalencias entre familias de medicamentos. |
| RF21 | Receta PDF al finalizar la atención, QR e integridad firmada de los datos de nuevas recetas. Las antiguas mantienen la verificación de existencia. |
| RF22 | Derivación rutinaria, prioritaria o urgente, precarga en la reserva, vínculo a la cita de destino y cierre al atenderla. |
| RF23 | Dos modalidades de cobro nuevas: tarjeta con Stripe Checkout y efectivo en caja. Recibos nuevos REC-01. Stripe y sus devoluciones operan en modo de prueba con webhook firmado. Los comprobantes históricos conservan su método registrado. |
| RF24 | Angélica informa sobre horarios, especialidades y citas propias. La CI sólo se acepta para el paciente autenticado que corresponde al documento. |
| RF25 | Reportes con filtros, subtotales, PDF y Excel. Las sumas de cobros separan BOB y USD y excluyen devoluciones finalizadas. |
| RF26 | QBE con columnas autorizadas, filtros parametrizados y exportación. Incluye arancel reservado, moneda y estado de devolución. |
| RF27 | Disponible para Administrador, Recepcionista y Médico. Micrófono con entrada escrita alternativa, plan JSON validado de Gemini, consulta de lectura y alcance restringido a la actividad propia para el médico. |

## Configuración

- Actualizar una base existente con **php artisan clinica:compatibilidad --moneda-anterior=usd** (o `bob` si corresponde a sus datos históricos). Para datos anteriores se usa el importe del pago asociado cuando existe. En una cita antigua sin pago se usa el arancel vigente al actualizar. La opción sólo rellena monedas ausentes y no convierte importes. Véase [database/README.md](../database/README.md) para el orden de instalación y la correspondencia con los 18 casos de uso.
- Instalar en una base vacía con **php artisan clinica:instalar --datos**. Para agregar ejemplos se usa **php artisan clinica:datos-ejemplo**.
- **CLINICA_MONEDA=bob** y **STRIPE_CURRENCY=bob** son los valores para nuevas reservas en BOB. Checkout requiere una clave de prueba y una cuenta Stripe que admita cobros con tarjeta en BOB. La moneda del cobro coincide con la de la reserva. Para una demostración en USD, configure ambas variables en USD antes de generar la reserva de prueba. Al actualizar una base anterior que todavía no guarda la moneda de sus pagos, indique su moneda histórica con `--moneda-anterior`; no se toma la configuración de reservas nuevas para deducirla.
- Configurar **STRIPE_SECRET** y **STRIPE_WEBHOOK_SECRET** para **/stripe/webhook**. El comando **php artisan clinica:devoluciones** procesa devoluciones Stripe Test. Recepción registra las devoluciones de caja desde el comprobante.
- Configurar SMTP y **APP_URL**. Ejecutar el programador de Laravel cada minuto. En desarrollo se puede usar **php artisan schedule:work**. Si **MAIL_MAILER=log**, los avisos permanecen pendientes.
- Configurar **GEMINI_API_KEY** para una prueba de voz de extremo a extremo. Conservar la **APP_KEY** utilizada para firmar las recetas para mantener la verificación de las ya emitidas.

## Verificación local

- La instalación desde una base PostgreSQL vacía completó el esquema, los ocho catálogos de especialidad y los permisos iniciales.
- La actualización de la base existente conservó las 14 citas y los 4 pagos. Se respaldó la base local antes de aplicar los cambios.
- La revisión del 7 de noviembre aprobó 45 pruebas con 235 aserciones sobre `clinica_revision_20261107_test`. La prueba independiente de reserva simultánea creó una sola cita y rechazó el intento competidor. Se verificaron la actualización repetida y las exclusiones del médico, y se comprobó que un fallo de instalación revierte las tablas y permite reintentar.
- El acceso local de Administrador y Paciente respondió correctamente en las pantallas nuevas y modificadas.
- El 8 de noviembre se completaron Stripe Checkout Test, webhooks auténticos y devolución, el despacho programado con Brevo en una base aislada y Gemini real desde la pantalla del médico. La suite final aprobó 47 pruebas y 238 aserciones. El usuario confirmó la recepción de los dos correos de prueba y la captura física con micrófono, que devolvió el reporte de una cita cancelada de Lucía. Véase [INTEGRACIONES_LOCALES.md](INTEGRACIONES_LOCALES.md).
