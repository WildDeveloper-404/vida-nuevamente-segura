# Datos de ejemplo cargados

Carga realizada en la base local `clinica_db` el 25 de octubre de 2026. Los nombres, documentos, teléfonos, correos, antecedentes, diagnósticos y transacciones añadidos son **ficticios**. Las referencias de pago de este conjunto corresponden al método de demostración `Efectivo`; no representan cobros reales ni eventos de Stripe.

## Contenido añadido

| Elemento | Nuevos registros |
| --- | ---: |
| Médicos con cuenta, especialidad y agenda de lunes a viernes | 4 |
| Pacientes con cuenta y perfil clínico | 8 |
| Consultorios | 4 |
| Horarios de atención | 20 |
| Citas, incluida una nueva cita ligada a una reprogramación | 13 |
| Pagos presenciales ficticios y comprobantes | 4 de cada uno |
| Receta y sus detalles | 1 receta, 2 detalles |
| Derivación y bloqueo de agenda | 1 de cada uno |
| Consultas de ejemplo del asistente | 2 |

La reserva genera notificaciones y entradas de auditoría mediante los procedimientos existentes. Una cita se cancela y otra se reprograma, de modo que el historial muestra distintos estados. La receta y la derivación están vinculadas a una cita confirmada. Los horarios se sitúan a partir del lunes siguiente a la ejecución del comando.

Los roles, permisos, módulos y ocho especialidades base ya existían; se conservaron. También se conservaron las cuatro cuentas y la cita inicial de `clinica:demo`. Tras la carga, se comprobó que las 24 tablas contienen registros. La base tiene 5 médicos, 9 pacientes, 14 citas, 4 pagos y 4 comprobantes en total.

## Acceso local

Los nuevos correos siguen los patrones `medico01@clinica.test` a `medico04@clinica.test` y `paciente01@clinica.test` a `paciente08@clinica.test`. La contraseña de demostración es `DemoVida2026!`. Estas credenciales sólo se destinan al entorno local. Los correos con dominio `.test` no sirven para recibir mensajes reales.

## Reproducción

Desde la raíz del proyecto:

```powershell
& '.tools/php/php.exe' artisan clinica:datos-ejemplo
```

El comando está implementado en `app/Console/Commands/SampleDataCommand.php`. Sólo se ejecuta con `APP_ENV=local`, inserta todas las filas en una transacción y se detiene sin duplicarlas si detecta que el conjunto ya existe. No borra ni modifica los registros previos.
