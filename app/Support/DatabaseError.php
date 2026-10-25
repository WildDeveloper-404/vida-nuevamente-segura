<?php
namespace App\Support;
use Illuminate\Database\QueryException;
final class DatabaseError
{
    public static function message(QueryException $e): string
    {
        $state = $e->errorInfo[0] ?? $e->getCode();
        if ($state === 'P0001') {
            $raw = $e->errorInfo[2] ?? '';
            if (preg_match('/ERROR:\s*(.+?)(?:\r?\n|$)/u', $raw, $m)) {
                return mb_substr(strip_tags($m[1]), 0, 400);
            }
            return 'La operación no cumple las reglas de la clínica.';
        }
        return match ($state) {
            '23505' => 'Ya existe un registro con esos datos o el horario acaba de ser reservado.',
            '23503' => 'El registro relacionado ya no está disponible.',
            '23514', '23502', '22007', '22P02' => 'Revise los datos y los rangos ingresados.',
            '40001', '40P01', '55P03' => 'Otra operación está usando estos datos. Intente de nuevo.',
            default => 'No se pudo completar la operación. Intente más tarde o contacte a administración.',
        };
    }
}
