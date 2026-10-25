<?php
namespace App\Http\Controllers;
use App\Models\{CitaMedica,Pago};
use App\Services\PagoService;
use App\Support\{Access,Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Stripe\{StripeClient,Webhook};
use Barryvdh\DomPDF\Facade\Pdf;
class PagoController extends Controller
{
    public function checkout(CitaMedica $cita) { Access::cita($cita);return view('pagos.checkout',compact('cita')); }
    public function iniciarPago(Request $r,CitaMedica $cita)
    {
        Access::cita($cita);abort_unless($cita->estado==='Programada' && !$cita->pago,422,'La cita ya está pagada o no está pendiente de pago.');
        abort_unless(config('services.stripe.secret'),503,'Configure Stripe para habilitar el pago en línea.');
        $amount=PagoService::cents($cita->especialidad->arancel_consulta);
        abort_if($amount<=0,422,'Esta reserva no requiere un cobro electrónico.');
        $stripe=new StripeClient(config('services.stripe.secret'));
        $key=hash('sha256',$cita->id_cita.'|'.$amount.'|'.config('services.stripe.currency'));
        $session=Cache::lock('checkout-lock-'.$cita->id_cita,40)->block(5,function() use($stripe,$cita,$amount,$key) {
            $cached=Cache::get('checkout-'.$cita->id_cita);
            $previous=null;
            if($cached) {
                $previous=$stripe->checkout->sessions->retrieve($cached);
                if($previous->status==='open' && (int)$previous->amount_total===$amount) return $previous;
                abort_if($previous->status==='complete',409,'El pago está en conciliación. Espere la confirmación de la pasarela.');
                if($previous->status==='open') $stripe->checkout->sessions->expire($cached);
            }
            $session=$stripe->checkout->sessions->create(['mode'=>'payment','payment_method_types'=>['card'],'expires_at'=>time()+1800,'client_reference_id'=>(string)$cita->id_cita,'metadata'=>['id_cita'=>(string)$cita->id_cita],'payment_intent_data'=>['metadata'=>['id_cita'=>(string)$cita->id_cita]],'line_items'=>[['price_data'=>['currency'=>config('services.stripe.currency'),'unit_amount'=>$amount,'product_data'=>['name'=>'Consulta '.$cita->especialidad->nombre]],'quantity'=>1]],'success_url'=>route('pagos.checkout',$cita).'?resultado=pendiente','cancel_url'=>route('pagos.checkout',$cita).'?resultado=cancelado'],['idempotency_key'=>'cita-'.$key.'-'.($previous?->id??'initial')]);
            Cache::forever('checkout-'.$cita->id_cita,$session->id);
            return $session;
        });
        return redirect()->away($session->url);
    }
    public function webhookStripe(Request $r,PagoService $service)
    {
        abort_unless(config('services.stripe.webhook_secret'),503);
        try {$event=Webhook::constructEvent($r->getContent(),$r->header('Stripe-Signature',''),config('services.stripe.webhook_secret'));}
        catch(\UnexpectedValueException|\Stripe\Exception\SignatureVerificationException $e) {return response()->json(['message'=>'Firma o evento no válido.'],400);}
        $o=$event->data->object;
        if($event->type==='checkout.session.completed' || $event->type==='checkout.session.async_payment_succeeded') {
            if(($o->payment_status??null)!=='paid') return response()->json(['received'=>true]);
            $id=(int)($o->metadata->id_cita??0);$amount=(int)$o->amount_total;$reference=(string)$o->payment_intent;
        } elseif($event->type==='payment_intent.succeeded') {
            $id=(int)($o->metadata->id_cita??0);$amount=(int)$o->amount_received;$reference=(string)$o->id;
        } else return response()->json(['received'=>true]);
        if(!$id || !$reference) return response()->json(['message'=>'Evento sin reserva asociada.'],422);
        try {$service->record($id,$amount,(string)$o->currency,$reference);}
        catch(\Illuminate\Validation\ValidationException $e) {return response()->json(['message'=>'Se requiere conciliación del pago con recepción.'],409);}
        return response()->json(['received'=>true]);
    }
    public function presencial(Request $r,CitaMedica $cita,PagoService $service)
    {
        Access::cita($cita);abort_unless(session('rol')==='Recepcionista',403);
        $d=$r->validate(['monto'=>'required|decimal:0,2|gt:0','metodo'=>'required|in:Efectivo,Transferencia','referencia'=>'required|uuid']);
        $p=$service->record($cita->id_cita,PagoService::cents($d['monto']),config('services.stripe.currency'),'LOCAL-'.$d['referencia'],$d['metodo']);
        Audit::log('Pagos','PAGO_RECEPCION','Pago '.$p->id_pago);
        return redirect()->route('pagos.comprobante',$p);
    }
    public function comprobante(Request $r,Pago $pago)
    {
        Access::cita($pago->cita);$pago->load('comprobante','cita.especialidad');
        if($r->boolean('pdf')) return Pdf::loadView('pagos.pdf',compact('pago'))->download('comprobante-'.$pago->id_pago.'.pdf');
        return view('pagos.comprobante',compact('pago'));
    }
}
