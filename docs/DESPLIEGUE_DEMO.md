# Publicar la demostración en Railway con Supabase

La aplicación usa Blade para generar las páginas en Laravel. El frontend, los
archivos CSS/JavaScript y el backend pueden publicarse juntos en un servicio web
Docker en Railway bajo el mismo dominio HTTPS. Supabase aloja PostgreSQL.
Laravel conserva las cuentas, las sesiones y la autorización de los cuatro
perfiles. Railway Trial ofrece hasta 30 días o USD 5 de crédito, lo que ocurra
primero. La prueba limitada puede restringir salidas de red. [Railway Trial](https://docs.railway.com/pricing/free-trial)

El plan Free de Supabase puede pausar el proyecto tras siete días de baja
actividad. Revisa su estado en el panel el día anterior a la presentación.
[Pausa de proyectos Free de Supabase](https://supabase.com/docs/guides/platform/free-project-pausing)

1. Crea un proyecto **Free** de Supabase en una región cercana al servicio web.
   Desactiva **Data API** antes de cargar las tablas. Todo el acceso a PostgreSQL
   pasa por Laravel. En **Connect** copia la cadena **Session pooler**, puerto
   `5432`, compatible con IPv4 y sentencias preparadas. Usa `sslmode=require`.
   Si `unaccent` está instalado en `extensions`, `DB_SEARCH_PATH=public,extensions`
   permite que lo encuentren los procedimientos y disparadores. [Conexión PostgreSQL](https://supabase.com/docs/guides/database/connecting-to-postgres) · [Data API](https://supabase.com/docs/guides/api/securing-your-api) · [Extensiones](https://supabase.com/docs/guides/database/extensions)
2. Crea la base con los catálogos y el administrador autorizado antes de abrir
   el sitio. Usa los comandos de abajo y escribe una contraseña exclusiva para
   la nube cuando `clinica:admin` la solicite. Para CI y teléfono puedes usar
   identificadores de demostración. Para el acceso usa el correo autorizado
   de Sebastián. Después se pueden cargar los casos ficticios desde la aplicación.
3. En [Railway](https://railway.com/), conecta el repositorio público de GitHub
   a un solo servicio y selecciona la rama `despliegue-supabase`. Railway toma
   el `Dockerfile` y la comprobación de arranque `/up` de `railway.json`.
   En **Settings → Networking**, genera un dominio público hacia el puerto
   `10000`. Carga las variables indicadas abajo. Guarda la clave de aplicación
   para futuros despliegues y para validar las recetas firmadas.
4. Abre el subdominio generado. Comprueba `/up`, el acceso de Administrador, una
   reserva ficticia y un reporte. El inicio de sesión se configura con cookies
   seguras y los enlaces se generan con HTTPS.

En **Variables → RAW Editor** del servicio pega esta base y sustituye el
dominio por el que generó Railway:

```dotenv
PORT=10000
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-servicio.up.railway.app
SESSION_SECURE_COOKIE=true
DB_SSLMODE=require
DB_SEARCH_PATH=public,extensions
CLINICA_MONEDA=bob
STRIPE_CURRENCY=bob
MAIL_MAILER=log
```

Añade `APP_KEY` y `DATABASE_URL` con sus valores reales en **Variables** y
pulsa **Deploy** para aplicar los cambios. `APP_KEY` es la salida de
`key:generate --show` y `DATABASE_URL` es la cadena Session pooler de Supabase.
[Variables](https://docs.railway.com/variables) · [Dominios públicos](https://docs.railway.com/networking/public-networking)

## Preparar la base y la clave

Desde PowerShell 7 en la raíz del proyecto:

```powershell
docker build -t vida-nuevamente-segura:demo .
docker run --rm vida-nuevamente-segura:demo php artisan key:generate --show
$env:DATABASE_URL = Read-Host -MaskInput 'URL Session pooler de la base nueva en Supabase'
try {
    docker run --rm -e DATABASE_URL -e DB_SSLMODE=require -e DB_SEARCH_PATH=public,extensions vida-nuevamente-segura:demo php artisan clinica:instalar --datos
    if ($LASTEXITCODE -ne 0) { throw 'Falló la instalación de la base de demostración.' }
    docker run --rm -it -e DATABASE_URL -e DB_SSLMODE=require -e DB_SEARCH_PATH=public,extensions vida-nuevamente-segura:demo php artisan clinica:admin
} finally {
    Remove-Item Env:DATABASE_URL -ErrorAction SilentlyContinue
}
```

El asistente crea el administrador con la contraseña que se introduce en ese
momento. Usa una base nueva para separar los datos ficticios y el acceso de nube
de las cuentas y contraseñas de la instalación local. La instalación carga
catálogos y permisos. Los registros clínicos de ejemplo se crean con cuentas de
demostración de acceso controlado. Entrega el enlace de la demo a sus evaluadores
con la indicación de usar datos ficticios.

## Activar integraciones después del primer acceso

| Integración | Configuración y prueba |
| --- | --- |
| Stripe | Agrega `STRIPE_KEY`, `STRIPE_SECRET` y `STRIPE_WEBHOOK_SECRET` de prueba en Railway. Crea un destino de prueba en Stripe para `https://<dominio-de-railway>/stripe/webhook`, con los eventos `payment_intent.succeeded`, `checkout.session.completed` y `checkout.session.async_payment_succeeded`. Usa el secreto de firma de ese destino. Repite reserva, pago y devolución en BOB. |
| Gemini | Agrega `GEMINI_API_KEY` y prueba una consulta escrita y luego el micrófono sobre HTTPS. |
| Correo | Configura `MAIL_MAILER=brevo`, `BREVO_API_KEY` y `MAIL_FROM_ADDRESS` con el remitente verificado. Usa una clave de API de Brevo. El transporte envía por HTTPS, compatible con la restricción de SMTP en Railway Trial. Verifica la recepción de una recuperación y de un recordatorio en un buzón de prueba. Con `MAIL_MAILER=log` en producción, la recuperación no genera enlaces y los avisos quedan pendientes. [Red saliente de Railway](https://docs.railway.com/networking/outbound-networking#email-delivery) · [API de Brevo](https://developers.brevo.com/reference/send-transac-email) |
| Recordatorios y devoluciones | Ejecuta `php artisan schedule:work` desde un equipo controlado conectado a Supabase durante la demostración, con las variables de nube y destinatarios de prueba revisados. Con `MAIL_MAILER=log` los recordatorios quedan pendientes. |

Las sesiones y el caché del servicio usan el almacenamiento temporal del
contenedor. Los reinicios pueden cerrar la sesión del navegador. Las citas,
cuentas y demás registros se guardan en Supabase.

## Rama y revisión

El contenedor usa PHP 8.5 y las contraseñas nuevas se guardan con Argon2id.
El inicio de sesión acepta las cuentas bcrypt existentes y actualiza su hash
después de verificar correctamente la contraseña, dentro de la misma transacción.
La configuración conserva la verificación del algoritmo para cada hasher.
Argon2id usa 64 MiB de memoria, cuatro iteraciones y un hilo por operación.

Usa `despliegue-supabase` como rama de origen del servicio de demostración.
Cuando el ensayo en nube pase, abre un pull request con base `main` y compara
`despliegue-supabase`. Después de revisar y fusionar el pull request, puedes
cambiar la rama de despliegue de Railway a `main` desde los ajustes del servicio.
[Selección de rama en Railway](https://docs.railway.com/deployments/github-autodeploys#configure-the-github-branch-for-deployment-triggers)

El cambio de GitHub `Eliminar Demo` retiró comandos que incluían credenciales
conocidas. Para la presentación, prepara las cuentas ficticias desde la
interfaz y asigna una contraseña distinta a cada cuenta.

La imagen se compiló y respondió HTTP 200 en `/up`, `/login` y su CSS. Se
comprobaron los enlaces HTTPS y la cookie de sesión segura. El instalador creó
las 25 tablas y ocho especialidades desde el contenedor en PostgreSQL de prueba,
con `unaccent` en el esquema `extensions`. La conexión con un proyecto real de Supabase y las
integraciones desde el dominio público quedan pendientes del ensayo en nube.

Con el transporte Brevo por HTTPS, se volvió a compilar el contenedor y se
verificaron `/up`, la cookie segura y el origen HTTPS fijado en `APP_URL`.
La suite PHP aprobó 52 pruebas y 287 aserciones, incluida la recuperación con
una respuesta de Brevo simulada. La entrega a un buzón real se valida en el
ensayo de Railway.
