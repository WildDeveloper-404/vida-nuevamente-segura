<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['rol' => App\Http\Middleware\MiddlewareRol::class, 'permiso' => App\Http\Middleware\MiddlewarePermiso::class]);
        $middleware->prependToPriorityList(Illuminate\Routing\Middleware\SubstituteBindings::class, App\Http\Middleware\MiddlewareRol::class);
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);
        $middleware->appendToGroup('web', App\Http\Middleware\CabecerasSeguras::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash(['password', 'password_confirmation', 'token']);
        $exceptions->render(function (Illuminate\Database\QueryException $e, Illuminate\Http\Request $r) {
            $message = App\Support\DatabaseError::message($e);
            if ($r->is('stripe/webhook')) return response()->json(['message' => 'No se pudo registrar el evento.'], 503);
            if ($r->expectsJson()) return response()->json(['message' => $message], 422);
            if ($r->isMethod('GET')) return response()->view('errors.message', ['message' => $message], 503);
            return back()->withInput($r->except(['password', 'password_confirmation', 'token']))->withErrors(['db_error' => $message]);
        });
        $exceptions->render(function (Stripe\Exception\ApiErrorException $e, Illuminate\Http\Request $r) {
            return back()->withErrors(['pago' => 'La pasarela no está disponible. Puede reintentar el pago.']);
        });
    })->create();
