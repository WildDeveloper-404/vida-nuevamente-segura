<?php

namespace App\Services;

use App\Models\CitaMedica;
use App\Models\Paciente;
use App\Models\RecetaMedica;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

class RecetaService
{
    public function emitir(CitaMedica $cita, array $data): RecetaMedica
    {
        return DB::transaction(function () use ($cita, $data) {
            $cita = CitaMedica::whereKey($cita->id_cita)->lockForUpdate()->firstOrFail();
            abort_unless($cita->estado === 'Atendida', 422, 'Finalice la atención antes de emitir la receta.');
            Paciente::whereKey($cita->id_paciente)->lockForUpdate()->firstOrFail();
            $receta = RecetaMedica::create([
                'id_cita'=>$cita->id_cita,
                'diagnostico'=>$data['diagnostico'],
                'cie10'=>strtoupper($data['cie10']),
                'codigo_qr'=>hash('sha256', random_bytes(32)),
            ]);
            $receta->detalles()->createMany($data['items']);
            $receta->refresh();
            $receta->update(['huella_integridad'=>$this->huella($receta)]);
            Audit::log('Consultorio', 'RECETA', 'Receta '.$receta->id_receta.' para '.$cita->codigo_cita);

            return $receta;
        });
    }

    public function esIntegra(RecetaMedica $receta): bool
    {
        return $receta->huella_integridad !== null && hash_equals($receta->huella_integridad, $this->huella($receta));
    }

    private function huella(RecetaMedica $receta): string
    {
        $header = $receta->getRawOriginal();
        unset($header['huella_integridad']);
        ksort($header);
        $lines = $receta->detalles()->orderBy('id_detalle')->get()->map(function ($item) {
            $data = $item->getRawOriginal();
            ksort($data);

            return $data;
        });

        return hash_hmac('sha256', json_encode([$header, $lines], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }
}
