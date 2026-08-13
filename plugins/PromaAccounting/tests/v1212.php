<?php
$root=dirname(__DIR__); $m=json_decode(file_get_contents($root.'/plugin.json'),true);
if (version_compare((string)($m['version']??''), '1.2.12', '<')) { fwrite(STDERR,"version mismatch\n"); exit(1); }
$paths=array_column($m['routes']??[],'path');
foreach(['plugin/accounting/referrals','portal/referrals','plugin/accounting/salary'] as $p) if(in_array($p,$paths,true)){fwrite(STDERR,"removed route still present $p\n");exit(1);}
foreach([['views/components/ui.php','تأیید و ادامه'],['assets/js/accounting.js','is-loading']] as $check) if(strpos(file_get_contents($root.'/'.$check[0]),$check[1])===false){fwrite(STDERR,"missing {$check[1]}\n");exit(1);}
echo "PROMA_ACCOUNTING_V1212_OK\n";
