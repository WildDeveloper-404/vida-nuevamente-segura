<?php
namespace App\Services;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\{Spreadsheet,Cell\DataType,Writer\Xlsx};
class ExportService
{
    public function download(array $report,string $format,string $title)
    {
        abort_if(empty($report['rows']),422,'No hay registros para exportar.');
        abort_unless(in_array($format,['pdf','csv','xlsx']),422);
        if($format==='pdf') return Pdf::loadView('admin.reportes.pdf',$report+['title'=>$title])->setPaper('a4','landscape')->download('reporte.pdf');
        $rows=[array_values($report['columns'])];
        foreach($report['rows'] as $row) $rows[]=array_map(fn($key)=>(string)(((array)$row)[$key]??''),array_keys($report['columns']));
        if($report['totals']) $rows[]=array_map(fn($k)=>(string)($report['totals'][$k]??($k===array_key_first($report['columns'])?'TOTAL':'')),array_keys($report['columns']));
        if($format==='csv') return response()->streamDownload(function() use($rows) {$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");foreach($rows as $row) fputcsv($out,array_map(fn($v)=>preg_match('/^[\s]*[=+@-]/u',$v)?"'".$v:$v,$row),',','"','');fclose($out);},'reporte.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
        return response()->streamDownload(function() use($rows) {$book=new Spreadsheet;$sheet=$book->getActiveSheet();foreach($rows as $i=>$row) foreach($row as $j=>$value) $sheet->setCellValueExplicit([$j+1,$i+1],(string)$value,DataType::TYPE_STRING);$sheet->getStyle('1:1')->getFont()->setBold(true);$sheet->freezePane('A2');foreach(range(1,count($rows[0])) as $col) $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);(new Xlsx($book))->save('php://output');$book->disconnectWorksheets();},'reporte.xlsx',['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
