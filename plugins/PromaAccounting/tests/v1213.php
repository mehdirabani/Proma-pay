<?php
$root=dirname(__DIR__);$m=json_decode(file_get_contents($root.'/plugin.json'),true);
if(version_compare((string)($m['version']??''),'1.2.13','<')){fwrite(STDERR,"version mismatch\n");exit(1);}
$js=file_get_contents($root.'/assets/js/accounting.js');
if (is_file($root.'/views/salary.php') || is_file($root.'/src/Services/SalaryService.php')) { fwrite(STDERR,"salary module still present\n"); exit(1); }
if (strpos($js,'در حال ثبت…')===false) { fwrite(STDERR,"modal loading missing\n"); exit(1); }
echo "PROMA_ACCOUNTING_V1213_OK\n";
