# Base de datos: alcance e instalación

Revisión: 7 de noviembre de 2026. Motor utilizado: PostgreSQL 18.1 local.

Esta carpeta contiene los scripts que construyen y actualizan la base. Los datos
del servidor local se almacenan en el clúster `.tools/pgdata`, no en estos SQL.
La aplicación utiliza la conexión PostgreSQL definida en `.env`.

El esquema actual tiene **25 tablas**: las 24 originales y `devolucion_pago`.
Los 18 casos de uso comparten tablas; no requieren una tabla por caso.
Los requisitos RF01–RF27 detallan y amplían esos casos. Consulta también
[el seguimiento funcional](../docs/REQUISITOS_FUNCIONALES.md).

## Correspondencia con los 18 casos de uso

| Caso | Nombre | Tablas principales y soporte |
| --- | --- | --- |
| CU01 | Autenticar usuario, cargar vistas por rol y recuperar contraseña | `usuario`, `persona`, `rol`, `permiso`, `rol_permiso`; bloqueo e intentos de acceso. La recuperación utiliza el mecanismo implementado en la aplicación. |
| CU02 | Administrar usuarios, roles, módulos y permisos | `usuario`, `rol`, `modulo`, `permiso`, `rol_permiso`, `personal_administrativo`. |
| CU03 | Consultar y auditar bitácora de eventos | `bitacora`; cambios anteriores y nuevos en JSONB, protección contra modificación, borrado y truncado. |
| CU04 | Gestionar médicos y especialidades | `persona`, `medico`, `especialidad`, `medico_especialidad`; códigos, matrícula, arancel, rango etario y nombres normalizados. |
| CU05 | Configurar horarios y disponibilidad médica | `horario_atencion`, `consultorio`, `bloqueo_agenda`; cupos calculados y exclusión de intervalos de médico y consultorio. |
| CU06 | Gestionar pacientes y autorregistro | `persona`, `paciente`, `usuario`, `perfil_clinico`; CI y códigos únicos, correo de contacto. |
| CU07 | Programar, reprogramar y cancelar citas | `cita_medica`, `bloqueo_agenda`, `notificacion_cita`; vínculo a cita anterior, estados, importe y moneda reservados. |
| CU08 | Consultar historial / Kardex del paciente | `cita_medica`, `receta_medica`, `pago`, `comprobante`, `devolucion_pago`. El Kardex es una consulta del historial, no una tabla adicional. |
| CU09 | Gestionar recordatorios y reprogramación masiva | `bloqueo_agenda`, `cita_medica`, `notificacion_cita`, `devolucion_pago`; fecha de despacho, respuesta al traslado y procedimiento transaccional. |
| CU10 | Administrar perfil clínico y antecedentes | `perfil_clinico`, `paciente`, `persona`; antecedentes, alergias y contactos. |
| CU11 | Consultar agenda y controlar estados de atención | `cita_medica`, `medico`, `horario_atencion`; máquina de estados. |
| CU12 | Emitir receta digital y orden de exámenes | `receta_medica`, `detalle_receta`, `perfil_clinico`; CIE-10, principio activo, QR, huella de integridad y protección de recetas emitidas. |
| CU13 | Registrar derivación / interconsulta | `derivacion_medica`, `cita_medica`, `especialidad`; prioridad, origen y cita de destino. |
| CU14 | Procesar pago de reserva | `pago`, `comprobante`, `devolucion_pago`, `cita_medica`; referencia única e importe y moneda. Nuevos métodos: Stripe o efectivo en caja. |
| CU15 | Interactuar con Angélica | `consulta_asistente_ia` y consultas autorizadas de especialidades, médicos, horarios y citas. |
| CU16 | Generar reportes administrativos | Consultas sobre las tablas de negocio, sin duplicar sus datos; pagos agrupados por moneda y devoluciones. |
| CU17 | Generar reportes QBE | Tablas de negocio consultadas con columnas autorizadas y filtros parametrizados desde el backend. |
| CU18 | Generar reportes por voz con IA | `consulta_asistente_ia` y tablas de negocio; Administrador, Recepción y Médico. Validación del plan y permisos en el backend; el médico consulta sólo su actividad. |

## Instalar una base vacía

Crear previamente la base PostgreSQL y configurar su conexión en `.env`.
Desde la raíz del proyecto:

```powershell
& '.tools/php/php.exe' artisan clinica:instalar --datos
& '.tools/php/php.exe' artisan clinica:admin
```

`--datos` carga cuatro roles, cinco módulos y ocho especialidades. El instalador
agrega los permisos utilizados por las rutas. No crea contraseñas conocidas ni
un administrador por defecto; el segundo comando permite crearlo.

Para la demostración, crea pacientes, médicos y turnos ficticios desde la
aplicación y asigna contraseñas distintas a las cuentas. La instalación sólo
carga los catálogos iniciales.

El instalador ejecuta **una sola transacción**, con bloqueo de instalación:

1. `sql/01_tablas.sql`: tablas originales y secuencias.
2. `sql/02_triggers.sql`: triggers base.
3. `sql/07_requisitos_funcionales.sql`: ampliaciones de los RF01–RF27.
4. `sql/05_procedimientos_compatibles.sql`: funciones y procedimientos actuales.
5. `sql/06_reglas_compatibles.sql`: validación de citas y máquina de estados actualizadas.
6. `sql/08_integridad_agendas.sql`: exclusiones por médico e índices del historial y agenda.
7. `sql/04_catalogos_base.sql`, si se solicita `--datos`, y permisos iniciales.

El orden numérico de los nombres **no es el orden de ejecución**. Si falla una
etapa, se revierte toda la instalación. Una base ya instalada se actualiza con
el comando siguiente, sin repetir la instalación.

## Actualizar una base existente

Respaldar primero la base con `pg_dump`. Después:

```powershell
& '.tools/php/php.exe' artisan clinica:compatibilidad --moneda-anterior=usd
```

Usar `bob` si los registros históricos que todavía no guardan moneda estaban
realmente expresados en BOB. El valor predeterminado es `usd`, la moneda del
proyecto original. Esta opción sólo rellena monedas ausentes: no convierte
importes ni cambia monedas ya almacenadas. La configuración de las reservas
nuevas es independiente (`CLINICA_MONEDA` y `STRIPE_CURRENCY`).

El comando aplica 07, 05, 06 y 08 en una transacción. Puede repetirse y no
restablece permisos personalizados ni recarga ejemplos. Para citas antiguas
sin importe reservado usa el pago asociado, o el arancel vigente si no existe
pago; no es posible reconstruir otro arancel histórico sin una fuente adicional.

Si existen intervalos superpuestos, las exclusiones rechazan la actualización
y se revierte la transacción: se deben revisar esos registros antes de repetirla.
No se borran ni corrigen citas automáticamente.

Para habilitar el acceso de voz de Recepción en una base ya instalada, ejecutar:

```powershell
& '.tools/php/php.exe' artisan clinica:habilitar-voz-recepcion
```

El comando agrega únicamente esa relación de permiso y conserva las demás
asignaciones. Las instalaciones nuevas ya incluyen los tres perfiles con voz.
La actualización general no vuelve a conceder este permiso si posteriormente
se revoca desde Administración.

## Archivos anteriores que no se cargan

- `sql/03_procedimientos.sql`: versión original de referencia. El instalador usa
  `05_procedimientos_compatibles.sql` y `06_reglas_compatibles.sql`.
- `sql/04_datos.txt`: lote anterior conservado tal como fue entregado. Contiene
  identificadores fijos, permisos antiguos y métodos de pago que ya no se admiten
  para registros nuevos. No es el sembrado actual y no se ejecuta automáticamente.

No ejecutar todos los archivos de la carpeta indiscriminadamente. Para instalar,
actualizar y generar ejemplos deben utilizarse los comandos anteriores.

## Alcance de la validación

Las claves, relaciones, restricciones y procedimientos sostienen los 18 casos.
Los permisos por usuario, los plazos de cancelación, los servicios externos,
el PDF, el despacho de correos y las consultas de IA también requieren el código
Laravel y su configuración; disponer de las tablas no certifica esos flujos por sí solo.
La prueba completa con Stripe Test sigue siendo una verificación independiente.
