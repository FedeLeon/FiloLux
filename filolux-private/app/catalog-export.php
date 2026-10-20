<?php
declare(strict_types=1);

// Export only published information, independently of catalog filters/pagination.
function catalogExportProducts(): array {
    $where='p.active=1'.(hasProductDeletion()?' AND p.deleted_at IS NULL':'');
    $products=query('SELECT p.id,p.name,p.description,p.price_cents,p.mode,p.dimensions,p.lead_time,p.featured,p.category_id'.(hasProductColors()?',p.available_colors':'').',c.name AS primary_category FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE '.$where.' ORDER BY p.name,p.id')->fetchAll();
    $categories=[];
    foreach(query('SELECT p.id,c.id AS category_id,c.name FROM products p JOIN categories c ON '.productCategoryMembershipSql().' WHERE '.$where.' ORDER BY c.id')->fetchAll() as $row) $categories[$row['id']][]=$row;
    $images=[];
    foreach(query('SELECT i.product_id,i.path FROM product_images i JOIN products p ON p.id=i.product_id WHERE '.$where.' ORDER BY i.id')->fetchAll() as $row) {
        // Do not turn database content into arbitrary local paths or external requests.
        if(preg_match('#^uploads/[a-zA-Z0-9_-]+\.(?:webp|jpg|jpeg|png)$#D',$row['path'])) $images[$row['product_id']][]=$row['path'];
    }
    foreach($products as &$p) {
        $assigned=$categories[$p['id']]??[];
        $p['export_category']=$p['primary_category']??($assigned[0]['name']??'Sin categoría');
        $p['categories']=implode(', ',array_column($assigned,'name'));
        $p['colors']=implode(', ',array_map(fn($key)=>productColorOptions()[$key],productColors($p)));
        $p['images']=$images[$p['id']]??[];
        $p['link']=url('/producto/'.$p['id']);
    }
    unset($p);
    usort($products,fn($a,$b)=>strnatcasecmp($a['export_category'],$b['export_category'])?:strnatcasecmp($a['name'],$b['name'])?:($a['id']<=>$b['id']));
    return $products;
}
function catalogExportHeaders(): array {
    return ['Categoría principal','Categorías asignadas','Código de producto','Producto','Descripción','Modalidad','Precio (ARS)','Medidas','Plazo de fabricación','Colores disponibles','Destacado','Enlace del producto','Fotos (enlaces)','Foto principal'];
}
function catalogExportRow(array $p): array {
    return [$p['export_category'],$p['categories'],'#'.$p['id'],$p['name'],$p['description'],$p['mode']==='buy'?'Compra online':'A medida / consultar',
        $p['mode']==='buy'?$p['price_cents']/100:'A consultar',$p['dimensions']??'',$p['lead_time'],$p['colors'],(int)$p['featured']?'Sí':'No',$p['link'],implode("\n",array_map(fn($path)=>url('/'.$path),$p['images'])),''];
}
function catalogExportThumbnail(string $relative): ?string {
    $uploadRoot=realpath(PUBLIC_ROOT.'/uploads');
    $file=realpath(PUBLIC_ROOT.'/'.$relative);
    if(!$uploadRoot || !$file || !str_starts_with($file,$uploadRoot.DIRECTORY_SEPARATOR) || !is_file($file)) return null;
    $info=@getimagesize($file);
    if(!$info || $info[0]*$info[1]>16000000 || filesize($file)>5*1024*1024) return null;
    $image=match($info[2]) { IMAGETYPE_WEBP=>@imagecreatefromwebp($file),IMAGETYPE_JPEG=>@imagecreatefromjpeg($file),IMAGETYPE_PNG=>@imagecreatefrompng($file),default=>false };
    if(!$image) return null;
    $scale=min(1,320/$info[0],240/$info[1]); $w=max(1,(int)round($info[0]*$scale)); $h=max(1,(int)round($info[1]*$scale));
    $thumb=imagecreatetruecolor($w,$h); imagefill($thumb,0,0,imagecolorallocate($thumb,255,255,255));
    imagecopyresampled($thumb,$image,0,0,0,0,$w,$h,$info[0],$info[1]);
    ob_start(); imagejpeg($thumb,null,80); $bytes=ob_get_clean(); imagedestroy($thumb); imagedestroy($image);
    return $bytes?:null;
}
function catalogExportCsv(array $products,string $file): void {
    $out=fopen($file,'wb');
    try {
        fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,array_slice(catalogExportHeaders(),0,13),';','"','');
        foreach($products as $p) {
            $row=array_slice(catalogExportRow($p),0,13);
            $row[6]=$p['mode']==='buy'?number_format($p['price_cents']/100,2,',',''):'A consultar';
            // Spreadsheet applications must never interpret product text as formulas.
            $row=array_map(fn($v)=>is_string($v)&&preg_match('/^[\s\x00-\x1f]*[=+@-]/u',$v)?"'".$v:$v,$row);
            fputcsv($out,$row,';','"','');
        }
    } finally { fclose($out); }
}
function catalogExportExcel(array $products,string $file): void {
    $book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    try {
        $sheet=$book->getActiveSheet(); $sheet->setTitle('Catálogo');
        $book->getProperties()->setCreator(setting('brand','FiloLux'))->setTitle('Catálogo de productos');
        foreach(catalogExportHeaders() as $col=>$text) $sheet->setCellValueExplicit([$col+1,1],$text,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $row=2; $previous=null;
        foreach($products as $p) {
            if($p['export_category']!==$previous) {
                $sheet->mergeCells('A'.$row.':N'.$row);
                $sheet->setCellValueExplicit('A'.$row,$p['export_category'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getStyle('A'.$row.':N'.$row)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFF0E6CD');
                $sheet->getStyle('A'.$row)->getFont()->setBold(true); $sheet->getRowDimension($row)->setRowHeight(25); $row++; $previous=$p['export_category'];
            }
            foreach(catalogExportRow($p) as $col=>$value) $sheet->setCellValueExplicit([$col+1,$row],$value,is_float($value)||is_int($value)?\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC:\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            if($p['mode']==='buy') $sheet->getStyle('G'.$row)->getNumberFormat()->setFormatCode('"$" #,##0.00');
            $sheet->getCell('L'.$row)->getHyperlink()->setUrl($p['link']);
            $height=max(95,min(409,16*(int)ceil(mb_strlen($p['description'])/45)));
            $sheet->getRowDimension($row)->setRowHeight($height);
            if($p['images'] && ($jpeg=catalogExportThumbnail($p['images'][0]))) {
                $drawing=new \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing();
                $drawing->setName($p['name'])->setDescription('Foto principal');
                $drawing->setImageResource(imagecreatefromstring($jpeg)); $drawing->setRenderingFunction(\PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::RENDERING_JPEG);
                $drawing->setMimeType(\PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::MIMETYPE_JPEG);
                $drawing->setCoordinates('N'.$row)->setOffsetX(6)->setOffsetY(6)->setHeight(100)->setWorksheet($sheet);
                if($drawing->getWidth()>165) $drawing->setWidth(165);
            }
            $row++;
        }
        $sheet->freezePane('D2'); $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1,1);
        $sheet->getStyle('A1:N'.max(1,$row-1))->getAlignment()->setWrapText(true)->setVertical('top');
        $sheet->getStyle('A1:N1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:N1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF29251E');
        $sheet->getRowDimension(1)->setRowHeight(35);
        foreach(['A'=>24,'B'=>28,'C'=>14,'D'=>30,'E'=>55,'F'=>24,'G'=>18,'H'=>24,'I'=>26,'J'=>30,'K'=>14,'L'=>42,'M'=>55,'N'=>26] as $col=>$width) $sheet->getColumnDimension($col)->setWidth($width);
        if(!$products) $sheet->setCellValueExplicit('A2','No hay productos publicados.',\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($file);
    } finally { $book->disconnectWorksheets(); }
}
function catalogExportPdf(array $products,string $file): void {
    $options=new \Dompdf\Options();
    $options->set('isRemoteEnabled',false); $options->set('isPhpEnabled',false); $options->set('isJavascriptEnabled',false);
    $options->set('chroot',PUBLIC_ROOT.'/assets'); $options->set('defaultFont','DejaVu Sans');
    // Writable font cache stays private; vendor may be read-only on the hosting.
    $cache=ROOT.'/storage/pdf-fonts';
    if(!is_dir($cache) && !mkdir($cache,0700,true) && !is_dir($cache)) throw new RuntimeException('No se pudo preparar el PDF.');
    $options->set('fontCache',$cache);
    $pdf=new \Dompdf\Dompdf($options);
    ob_start();
    try { require ROOT.'/views/catalog-pdf.php'; $html=ob_get_contents(); } finally { ob_end_clean(); }
    $pdf->loadHtml($html,'UTF-8'); $pdf->setPaper('A4'); $pdf->render();
    $pdf->getCanvas()->page_text(42,810,'Catálogo · {PAGE_NUM} / {PAGE_COUNT}',null,8,[0.4,0.4,0.4]);
    file_put_contents($file,$pdf->output());
}
function downloadCatalog(string $format): void {
    if(!in_array($format,['pdf','xlsx','csv'],true)) { abortPage(404,'Formato de catálogo no disponible.'); return; }
    rateLimit('catalog-export',15,600);
    $products=catalogExportProducts();
    session_write_close();
    $file=tempnam(sys_get_temp_dir(),'filolux-catalog-');
    if($file===false) throw new RuntimeException('No se pudo preparar el catálogo.');
    try {
        match($format) { 'pdf'=>catalogExportPdf($products,$file), 'xlsx'=>catalogExportExcel($products,$file), 'csv'=>catalogExportCsv($products,$file) };
        header('Content-Type: '.match($format) { 'pdf'=>'application/pdf','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','csv'=>'text/csv; charset=UTF-8' });
        header('Content-Disposition: attachment; filename="filolux-catalogo-'.date('Y-m-d').'.'.$format.'"');
        header('Content-Length: '.filesize($file));
        readfile($file);
    } finally { unlink($file); }
}
