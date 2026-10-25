<?php
namespace App\Http\Controllers;
use App\Services\{ReporteService,ExportService};
use Illuminate\Http\Request;
class QbeController extends Controller
{
    public function index(Request $r,ReporteService $service,ExportService $export)
    {
        $report=['columns'=>[],'rows'=>[],'totals'=>[]];
        if($r->has('generar')) {
            $d=$r->validate(['campos'=>'required|array|min:1|max:20','campos.*'=>'string|max:40','filtros'=>'nullable|array|max:10','filtros.*.campo'=>'required|string','filtros.*.operador'=>'required|string','filtros.*.valor'=>'nullable|string|max:200','orden'=>'required|string','formato'=>'nullable|in:pdf,csv,xlsx']);
            $report=$service->qbe($d);if($r->filled('formato')) return $export->download($report,$d['formato'],'Reporte QBE');
        }
        return view('admin.reportes.qbe',$report+['fields'=>ReporteService::FIELDS]);
    }
}
