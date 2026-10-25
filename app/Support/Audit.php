<?php
namespace App\Support;
use App\Models\Bitacora;
final class Audit
{
    public static function log(string $module, string $action, string $description): void
    {
        Bitacora::create(['id_usuario'=>session('id_usuario'), 'modulo'=>$module, 'accion'=>$action,
            'descripcion'=>$description, 'direccion_ip'=>request()->ip() ?? '127.0.0.1']);
    }
}
