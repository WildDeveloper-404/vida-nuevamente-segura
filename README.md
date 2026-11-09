# Vida Nuevamente Segura

Sistema web de gestión clínica para registrar pacientes, organizar la agenda médica, reservar citas, atender consultas y generar reportes. Proyecto académico con 18 casos de uso y datos de demostración.

[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](Dockerfile)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](composer.json)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-Supabase-4169E1?logo=postgresql&logoColor=white)](database/README.md)

## Acceder a la aplicación

**[Abrir Vida Nuevamente Segura](https://vida-nuevamente-segura-production.up.railway.app)** · **[Iniciar sesión](https://vida-nuevamente-segura-production.up.railway.app/login)**

El frontend y el backend se sirven desde Railway. La base de datos PostgreSQL está alojada en Supabase.

## Cuentas de demostración

Usa estas cuentas para recorrer los distintos perfiles de la presentación.

| Perfil | Correo | Contraseña |
| --- | --- | --- |
| Recepcionista | recepcion.demo@example.test | Recep1!3ece83743e |
| Médico | medico.demo@example.test | Medic1!930ea90b06 |
| Paciente | paciente.demo@example.test | Pacie1!862aa99ee1 |

La cuenta de administración es **sebastianrial16@gmail.com**. Su contraseña se comparte de forma privada con la persona autorizada.

Las credenciales de la tabla son públicas y pertenecen a cuentas de demostración. Los registros de pacientes y atenciones son simulados. Stripe se utiliza en modo de prueba.

## Funciones por perfil

| Perfil | Funciones principales |
| --- | --- |
| Administrador | Usuarios, roles, permisos, bitácora, catálogos, horarios, contingencias y reportes. |
| Recepcionista | Registro de pacientes, reservas, reprogramaciones, cancelaciones, Kardex, cobros en caja y reportes autorizados. |
| Médico | Agenda propia, perfil clínico, atención, recetas, derivaciones y reportes de su actividad. |
| Paciente | Citas, historial, perfil clínico, recetas, pagos y orientación con Angélica. |

## Casos de uso

| Código | Función |
| --- | --- |
| CU01 | Iniciar sesión y recuperar el acceso. |
| CU02 | Administrar usuarios, roles y permisos. |
| CU03 | Consultar la bitácora de auditoría. |
| CU04 | Gestionar médicos y especialidades. |
| CU05 | Configurar horarios, consultorios y disponibilidad. |
| CU06 | Registrar y gestionar pacientes. |
| CU07 | Reservar, reprogramar y cancelar citas. |
| CU08 | Consultar el Kardex de citas. |
| CU09 | Gestionar contingencias y recordatorios. |
| CU10 | Consultar y actualizar el perfil clínico. |
| CU11 | Gestionar la agenda y los estados de las citas. |
| CU12 | Emitir recetas y órdenes de exámenes. |
| CU13 | Registrar y atender derivaciones médicas. |
| CU14 | Pagar una cita y emitir el comprobante. |
| CU15 | Consultar orientación con Angélica. |
| CU16 | Generar reportes administrativos. |
| CU17 | Construir reportes personalizados QBE. |
| CU18 | Generar reportes por voz o texto. |

## Tecnología y organización

La aplicación utiliza Laravel 12 con vistas Blade y JavaScript. Los controladores reciben las solicitudes, los servicios aplican las reglas del negocio y los modelos acceden a PostgreSQL.

| Carpeta | Contenido |
| --- | --- |
| [resources/views](resources/views) | Frontend y vistas Blade. |
| [public](public) | Recursos públicos de la aplicación. |
| [routes](routes) | Rutas y puntos de entrada HTTP. |
| [app/Http/Controllers](app/Http/Controllers) | Controladores del backend. |
| [app/Services](app/Services) | Reservas, pagos, recetas, reportes e integraciones. |
| [app/Models](app/Models) | Modelos de datos. |
| [database](database) | Esquema SQL, procedimientos, triggers y catálogos. |
| [tests](tests) | Pruebas automatizadas. |
| [deploy](deploy) | Inicio del servicio en la nube. |
| [docs](docs) | Requisitos, arquitectura, despliegue y evidencias. |

Las integraciones incluyen Stripe para pagos con tarjeta de prueba, Brevo para correo y Gemini para las funciones de inteligencia artificial. Las contraseñas de acceso utilizan Argon2id y el sistema aplica bloqueo progresivo ante intentos fallidos.

## Documentación del proyecto

- [Arquitectura y diseño](docs/ARQUITECTURA_Y_DISENO.md)
- [Requisitos funcionales](docs/REQUISITOS_FUNCIONALES.md)
- [Base de datos e instalación](database/README.md)
- [Despliegue en Railway y Supabase](docs/DESPLIEGUE_DEMO.md)
- [Pruebas de los 18 casos de uso](docs/PRUEBAS_CU01_CU18.md)
- [Seguimiento del desarrollo](docs/SEGUIMIENTO.md)

La configuración local y de servicios externos se realiza mediante variables de entorno. El archivo [.env.example](.env.example) contiene la plantilla.
