<?php
namespace Tests\Feature;
use App\Services\DatosDemostracionService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoReportesTest extends TestCase
{
    public function test_carga_ficticia_es_idempotente_y_alimenta_reportes_sin_avisos_pendientes(): void
    {
        $this->travelTo(today()->setTime(18,0),function() {
            $seeder=new DatosDemostracionService;
            $first=$seeder->run();$second=$seeder->run();
            $this->assertSame($first,$second);
            $this->assertSame(48,$first['citas']);$this->assertSame(16,$first['recetas']);
            $this->assertCount(5,$first['estados']);
            $this->assertSame(0,DB::table('notificacion_cita')->join('cita_medica','cita_medica.id_cita','=','notificacion_cita.id_cita')->where('codigo_cita','like','DEMO-RPT-%')->where('estado_envio','Pendiente')->count());
            $this->withSession($this->sessionFor($this->user()))
                ->get('/admin/reportes?tipo=consolidado&desde='.today()->toDateString().'&hasta='.today()->addDays(40)->toDateString())
                ->assertOk()->assertViewHas('rows',fn($rows)=>collect($rows)->sum('total_general')>=48);
        });
    }
}
