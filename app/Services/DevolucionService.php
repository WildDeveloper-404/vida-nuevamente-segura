<?php

namespace App\Services;

use App\Models\DevolucionPago;
use App\Models\Pago;
use Illuminate\Support\Facades\Cache;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class DevolucionService
{
    public function queue(Pago $payment): void
    {
        DevolucionPago::firstOrCreate(['id_pago' => $payment->id_pago]);
    }

    public function processStripe(): void
    {
        $key = (string) config('services.stripe.secret');
        if (!preg_match('/^(sk|rk)_test_/', $key)) {
            return;
        }
        $client = new StripeClient($key);
        DevolucionPago::whereIn('estado', ['Pendiente', 'Procesando'])
            ->whereHas('pago', fn ($query) => $query->where('metodo_pago', 'Stripe'))
            ->with('pago')->chunkById(25, function ($rows) use ($client) {
                foreach ($rows as $row) {
                    Cache::lock('stripe-refund-'.$row->id_pago, 90)->get(function () use ($row, $client) {
                        $row->refresh();
                        if (!in_array($row->estado, ['Pendiente', 'Procesando'], true)) {
                            return;
                        }
                        $payment = $row->pago;
                        try {
                            $refund = $row->referencia
                                ? $client->refunds->retrieve($row->referencia)
                                : $client->refunds->create(
                                    ['payment_intent'=>$payment->referencia_stripe, 'amount'=>PagoService::cents($payment->monto)],
                                    ['idempotency_key'=>'clinica-refund-'.$payment->referencia_stripe]
                                );
                            $status = match ($refund->status) {
                                'succeeded' => 'Devuelto',
                                'pending', 'requires_action' => 'Procesando',
                                default => 'Revisar',
                            };
                            $row->update(['estado'=>$status, 'referencia'=>$refund->id, 'actualizada_en'=>now()]);
                        } catch (ApiErrorException $e) {
                            $http = $e->getHttpStatus();
                            if (in_array($http, [400, 404], true)) {
                                $row->update(['estado'=>'Revisar', 'actualizada_en'=>now()]);
                            }
                            report($e);
                        }
                    });
                }
            }, 'id_pago');
    }
}
