<?php
namespace App\Services;
use App\Models\{CitaMedica,Pago,Comprobante,Medico};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class PagoService
{
    public static function cents(string $amount): int
    {
        [$whole,$fraction]=array_pad(explode('.',$amount,2),2,'0');
        return ((int)$whole)*100+(int)str_pad(substr($fraction,0,2),2,'0');
    }
    public function record(int $id,int $amount,string $currency,string $reference,string $method='Stripe'): Pago
    {
        return DB::transaction(function() use($id,$amount,$currency,$reference,$method) {
            $initial=CitaMedica::findOrFail($id);
            Medico::whereKey($initial->id_medico)->lockForUpdate()->firstOrFail();
            $c=CitaMedica::whereKey($id)->lockForUpdate()->firstOrFail();
            // Replayed events can still identify the original booking after rescheduling.
            $sameReference=Pago::where('referencia_stripe',$reference)->first();
            if($sameReference) {
                $linked=CitaMedica::findOrFail($sameReference->id_cita);$visited=[];
                while($linked && !isset($visited[$linked->id_cita])) {
                    if($linked->id_cita===$id && self::cents($sameReference->monto)===$amount && strtolower($currency)===strtolower(config('services.stripe.currency'))) return $sameReference;
                    $visited[$linked->id_cita]=true;$linked=$linked->id_cita_anterior?CitaMedica::find($linked->id_cita_anterior):null;
                }
                throw ValidationException::withMessages(['pago'=>'La referencia corresponde a otro pago.']);
            }
            $existing=Pago::where('id_cita',$id)->first();
            if($existing) {
                if($existing->referencia_stripe===$reference && self::cents($existing->monto)===$amount) return $existing;
                throw ValidationException::withMessages(['pago'=>'La cita ya cuenta con un pago diferente.']);
            }
            if(!in_array($c->estado,['Programada','Confirmada']) || $amount!==self::cents($c->especialidad->arancel_consulta) || strtolower($currency)!==strtolower(config('services.stripe.currency'))) throw ValidationException::withMessages(['pago'=>'El monto, moneda o estado de la cita no coincide con la reserva.']);
            $p=Pago::create(['id_cita'=>$id,'monto'=>number_format($amount/100,2,'.',''),'metodo_pago'=>$method,'referencia_stripe'=>$reference,'estado_pago'=>'Pagado']);
            $person=$c->paciente->persona;
            $n=DB::selectOne("SELECT 'COMP-' || lpad(nextval('seq_nro_comprobante')::text,8,'0') AS codigo")->codigo;
            Comprobante::create(['id_pago'=>$p->id_pago,'nro_comprobante'=>$n,'nit_ci'=>$person->ci,'razon_social'=>$person->nombre_completo,'concepto'=>'Consulta '.$c->especialidad->nombre.' · '.$c->codigo_cita]);
            return $p;
        });
    }
}
