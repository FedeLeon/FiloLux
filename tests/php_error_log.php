<?php
declare(strict_types=1);
require dirname(__DIR__).'/filolux-private/app/php-errors.php';
function check(bool $ok): void { if(!$ok) throw new RuntimeException('Log reader assertion failed'); }
$file=tempnam(sys_get_temp_dir(),'filolux-log-');
try {
    $log=phpErrorLogTail($file); check($log['available'] && $log['content']==='');
    file_put_contents($file,implode("\n",array_map(fn($n)=>'Line '.$n,range(1,300)))."\n");
    $log=phpErrorLogTail($file); check($log['truncated'] && str_starts_with($log['content'],'Line 101') && str_ends_with($log['content'],'Line 300') && count(explode("\n",$log['content']))===200);
    file_put_contents($file,str_repeat('Private log entry '.str_repeat('x',200)."\n",2000)."Final reference\n");
    $log=phpErrorLogTail($file); check(strlen($log['content'])<=131072 && str_ends_with($log['content'],'Final reference'));
    file_put_contents($file,str_repeat('x',200000));
    check(phpErrorLogTail($file)['content']!=='');
    require dirname(__DIR__).'/filolux-private/app/core.php';
    file_put_contents($file,'<script>alert(1)</script>'); $log=phpErrorLogTail($file);
    ob_start(); require dirname(__DIR__).'/filolux-private/views/admin-php-errors.php'; $html=ob_get_clean();
    check(!str_contains($html,'<script>alert') && str_contains($html,'&lt;script&gt;'));
    check(!phpErrorLogTail($file.'-absent')['available']);
    echo "PASS: empty/missing logs, last 200 lines, bounded large file, long line and escaped HTML.\n";
} finally { unlink($file); }
