<?php
declare(strict_types=1);
function phpErrorLogTail(string $file): array {
    $result=['content'=>'','available'=>false,'truncated'=>false,'modified'=>null];
    // Read only a bounded tail so a large log cannot exhaust PHP memory.
    $handle=@fopen($file,'rb');
    if(!$handle) return $result;
    try {
        $stat=fstat($handle);
        if($stat===false) return $result;
        $offset=max(0,$stat['size']-131072);
        if(fseek($handle,$offset)!==0) return $result;
        $text=stream_get_contents($handle,131072);
        if($text===false) return $result;
        if($offset>0) { $end=strpos($text,"\n"); $text=$end===false || $end===strlen($text)-1?$text:substr($text,$end+1); }
        $lines=$text===''?[]:preg_split('/\r\n|\n|\r/',$text);
        if($lines && end($lines)==='') array_pop($lines);
        return ['content'=>implode("\n",array_slice($lines,-200)),'available'=>true,'truncated'=>$offset>0 || count($lines)>200,'modified'=>$stat['mtime']];
    } finally { fclose($handle); }
}
