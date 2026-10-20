<?php
declare(strict_types=1);
$app='C:/laragon/www/proma-qa-backup-v405-20261008';
$_SERVER['REQUEST_METHOD']='CLI'; $_SERVER['REMOTE_ADDR']='198.51.100.46';
require $app.'/bootstrap.php';
if (Model::db()->query('SELECT DATABASE()')->fetchColumn()!=='proma_qa_backup_v405_20261008') throw new RuntimeException('Owned QA database required');
$base='http://127.0.0.1:8085/index.php?route=';
$request=static function(string $route,string $cookie,?array $post=null,array $query=[]) use($base):array {
    $ch=curl_init($base.rawurlencode($route).($query?'&'.http_build_query($query):''));
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_TIMEOUT=>60]);
    if($post!==null) curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
    $body=curl_exec($ch); $result=['status'=>curl_getinfo($ch,CURLINFO_RESPONSE_CODE),'url'=>curl_getinfo($ch,CURLINFO_EFFECTIVE_URL),'body'=>(string)$body];curl_close($ch);return $result;
};
$login=static function(string $identifier,string $password,string $cookie) use($request):array {
    $page=$request('auth/login',$cookie);
    if(!preg_match('/name="_csrf"[^>]*value="([^"]+)"/',$page['body'],$m)) throw new RuntimeException('Missing CSRF');
    return $request('auth/login',$cookie,['_csrf'=>html_entity_decode($m[1],ENT_QUOTES,'UTF-8'),'identifier'=>$identifier,'password'=>$password]);
};
$report=['customers'=>['allowed'=>0,'denied'=>0,'attempts'=>0],'staff'=>[],'table_pages'=>0,'file_downloads'=>0,'failures'=>[]];
$customers=($argv[1]??'')==='tables' ? [] : Model::fetchAll("SELECT id,username,national_id,mobile,status,last_login_at FROM users WHERE role='customer' ORDER BY id");
foreach($customers as $index=>$user) {
    try {
        foreach(array_unique(array_filter([$user['national_id'],$user['mobile']])) as $identifier) {
            $cookie=tempnam(sys_get_temp_dir(),'proma405-');
            try {
                $result=$login((string)$identifier,substr((string)$user['mobile'],-4),$cookie);
                $authenticated=$result['status']===200 && !str_contains(rawurldecode($result['url']),'auth/login');
                $report['customers']['attempts']++;
                if($authenticated===($user['status']==='active')) $report['customers'][$authenticated?'allowed':'denied']++;
                else $report['failures'][]='customer-login-'.($index+1);
                if($authenticated) { $denied=$request('backup/data',$cookie); if($denied['status']!==403) $report['failures'][]='customer-data-permission'; }
            } finally { @unlink($cookie); }
        }
    } finally { Model::execute('UPDATE users SET last_login_at=? WHERE id=?',[$user['last_login_at'],$user['id']]); }
    if(($index+1)%50===0) echo 'CUSTOMERS_CHECKED='.($index+1)."\n";
}
$adminCookie=null;
foreach([['admin','5249911439','Hh13671367'],['operator','09159771019','1019'],['lawyer','5240249490','Mm13791379'],['technician','09158840771','0771']] as [$role,$identifier,$password]) {
    $user=Model::fetch('SELECT id,last_login_at FROM users WHERE username=? OR mobile=? OR national_id=?',[$identifier,$identifier,$identifier]);
    $cookie=tempnam(sys_get_temp_dir(),'proma405-');
    try {
        $result=$login($identifier,$password,$cookie); $ok=$result['status']===200&&!str_contains(rawurldecode($result['url']),'auth/login');
        $report['staff'][$role]=$ok; if(!$ok) $report['failures'][]='staff-'.$role;
        if($role==='admin') $adminCookie=$cookie;
        elseif($ok && $request('backup/data',$cookie)['status']!==403) $report['failures'][]='staff-data-permission-'.$role;
    } finally { Model::execute('UPDATE users SET last_login_at=? WHERE id=?',[$user['last_login_at'],$user['id']]); if($role!=='admin') @unlink($cookie); }
}
if($adminCookie && $report['staff']['admin']) {
    foreach(Model::db()->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $count=(int)Model::db()->query('SELECT COUNT(*) FROM `'.str_replace('`','``',$table).'`')->fetchColumn();
        foreach(array_unique([1,max(1,(int)ceil($count/25))]) as $page) {
            $r=$request('backup/data',$adminCookie,null,['table'=>$table,'page'=>$page]);
            // Historical logs can legitimately contain SQL error text. The HTTP
            // status and rendered table determine whether this view failed.
            if($r['status']!==200||!str_contains($r['body'],'داده‌ها و سوابق بازیابی‌شده')||!str_contains($r['body'],'<thead>')) $report['failures'][]='table-page-'.$table;
            else $report['table_pages']++;
        }
    }
    $invalid=$request('backup/data',$adminCookie,null,['table'=>'users` WHERE 1=1 --']); if($invalid['status']!==404) $report['failures'][]='table-allowlist';
    foreach(Model::fetchAll("SELECT file_uuid,storage_path,status FROM files WHERE status<>'deleted'") as $file) {
        $path=FileRecord::absoluteStoragePath($file['storage_path']);
        if(!$path||!is_file($path)) { $report['failures'][]='missing-active-file-content';continue; }
        $r=$request('file-manager/download/'.$file['file_uuid'],$adminCookie);
        if($r['status']!==200||!hash_equals(hash_file('sha256',$path),hash('sha256',$r['body']))) $report['failures'][]='attachment-download'; else $report['file_downloads']++;
    }
    @unlink($adminCookie);
}
$resultPath=dirname(__DIR__).'/storage/qa-v405-http-result.json';
if(($argv[1]??'')==='tables' && is_file($resultPath)) {
    $previous=json_decode(file_get_contents($resultPath),true); $report['customers']=$previous['customers'];
    $report['failures']=array_merge(array_values(array_filter($previous['failures'],static fn($reason)=>!str_starts_with($reason,'table-page-'))),$report['failures']);
}
file_put_contents($resultPath,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode($report,JSON_UNESCAPED_UNICODE)."\n";
exit($report['failures']?1:0);
