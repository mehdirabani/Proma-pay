<?php
$root=dirname(__DIR__);$m=json_decode(file_get_contents($root.'/plugin.json'),true);
if(($m['version']??'')!=='1.2.14'){fwrite(STDERR,"version mismatch\n");exit(1);}
$paths=array_column($m['routes']??[],'path'); foreach(['plugin/accounting/salary','plugin/accounting/referrals','portal/referrals'] as $p) if(in_array($p,$paths,true)){fwrite(STDERR,"removed route still present $p\n");exit(1);}
if(is_file($root.'/views/salary.php')||is_file($root.'/src/Services/SalaryService.php')||is_file($root.'/views/referrals.php')){fwrite(STDERR,"removed module files still present\n");exit(1);}
echo "PROMA_ACCOUNTING_V1214_REMOVAL_OK\n";
