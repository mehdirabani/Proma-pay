<?php
namespace Proma\Plugins\SignConnect\Services;

final class TemplateVariableRegistry
{
    public static function definitions(): array
    {
        $fallback = [
            'customer_name'=>['title'=>'نام مشتری','sample'=>'مشتری گرامی','sensitive'=>false],
            'contract_number'=>['title'=>'شماره قرارداد','sample'=>'۱۴۰۵-۱۰۰','sensitive'=>false],
            'installment_number'=>['title'=>'شماره قسط','sample'=>'۳','sensitive'=>false],
            'due_date'=>['title'=>'تاریخ سررسید','sample'=>'۱۴۰۵/۰۶/۱۵','sensitive'=>false],
            'payable_amount'=>['title'=>'مبلغ قابل پرداخت','sample'=>'۱۲٬۰۰۰٬۰۰۰ ریال','sensitive'=>true],
            'payment_amount'=>['title'=>'مبلغ پرداخت','sample'=>'۵٬۰۰۰٬۰۰۰ ریال','sensitive'=>true],
            'signature_url'=>['title'=>'پیوند امضا','sample'=>'https://example.invalid/sign/…','sensitive'=>true],
            'portal_url'=>['title'=>'پیوند سامانه','sample'=>'https://example.invalid/portal','sensitive'=>false],
            'otp'=>['title'=>'کد یکبارمصرف','sample'=>'******','sensitive'=>true],
            'expiry_minutes'=>['title'=>'دقایق اعتبار','sample'=>'۳','sensitive'=>false],
            'store_name'=>['title'=>'نام فروشگاه','sample'=>'پرما پی','sensitive'=>false],
            'support_phone'=>['title'=>'شماره پشتیبانی','sample'=>'۰۲۱••••••••','sensitive'=>false],
            'login_time'=>['title'=>'زمان ورود','sample'=>'۱۴۰۵/۰۵/۰۷ ۱۲:۳۰','sensitive'=>false],
            'lock_minutes'=>['title'=>'مدت مسدودی','sample'=>'۱۵','sensitive'=>false],
            'request_id'=>['title'=>'شناسه درخواست','sample'=>'REQ-••••','sensitive'=>false],
            'case_number'=>['title'=>'شماره پرونده','sample'=>'L-۱۴۰۵-۱۰','sensitive'=>true],
            'file_title'=>['title'=>'عنوان فایل','sample'=>'گواهی قرارداد','sensitive'=>false],
        ];
        try {
            $rows=\Model::fetchAll("SELECT * FROM proma_connect_template_variables WHERE is_active=1 AND archived_at IS NULL ORDER BY is_system DESC,title_fa");
            if(!$rows) return $fallback;
            $result=[];
            foreach($rows as$row)$result[$row['variable_key']]=[
                'title'=>$row['title_fa'],'description'=>$row['description_fa'],'type'=>$row['value_type'],
                'resolver'=>$row['source_resolver'],'sample'=>$row['sample_value'],'sensitivity'=>$row['sensitivity'],
                'sensitive'=>in_array($row['sensitivity'],['personal','financial','secret'],true),'system'=>(bool)$row['is_system'],
            ];
            return $result;
        } catch(\Throwable $e){return $fallback;}
    }
    public static function keys(): array { return array_keys(self::definitions()); }

    public function providerMappings(): array
    {
        $rows=\Model::fetchAll('SELECT v.variable_key,m.provider_key,m.provider_parameter,m.wrapper_prefix,m.wrapper_suffix FROM proma_connect_variable_provider_mappings m JOIN proma_connect_template_variables v ON v.id=m.variable_id WHERE m.is_active=1 AND v.is_active=1');
        $out=[];foreach($rows as$row)$out[$row['variable_key']][$row['provider_key']]=[
            'parameter'=>$row['provider_parameter'],'display'=>(string)$row['wrapper_prefix'].$row['provider_parameter'].(string)$row['wrapper_suffix']
        ];return$out;
    }

    public function mapValues(string $provider,array $values): array
    {
        if(!in_array($provider,['ippanel','smsir','bale','telegram'],true))throw new \InvalidArgumentException('ارائه‌دهنده نگاشت متغیر معتبر نیست.');
        $mappings=$this->providerMappings();$result=[];
        foreach($values as$key=>$value){$key=(string)$key;if(!in_array($key,self::keys(),true))throw new \InvalidArgumentException('متغیر قالب مجاز نیست: '.$key);$target=$mappings[$key][$provider]['parameter']??$key;$result[$target]=$value;}
        return$result;
    }

    public function save(array $input,int $actorId): void
    {
        $key=trim((string)($input['variable_key']??''));
        if(!preg_match('/^[a-z][a-z0-9_]{2,79}$/',$key)) throw new \InvalidArgumentException('نام متغیر باید انگلیسی، معنادار و با حروف کوچک باشد.');
        $title=trim(strip_tags((string)($input['title_fa']??'')));
        if(mb_strlen($title)<2||mb_strlen($title)>190) throw new \InvalidArgumentException('عنوان فارسی متغیر معتبر نیست.');
        $type=(string)($input['value_type']??'string');if(!in_array($type,['string','integer','money','date','url','otp'],true))throw new \InvalidArgumentException('نوع متغیر معتبر نیست.');
        $resolver=trim((string)($input['source_resolver']??''));
        $allowedPrefixes=['customer.','contract.','installment.','payment.','signature.','application.','otp.'];
        if(!array_filter($allowedPrefixes,fn($prefix)=>str_starts_with($resolver,$prefix)))throw new \InvalidArgumentException('منبع داده متغیر در فهرست امن resolverها نیست.');
        $sensitivity=(string)($input['sensitivity']??'normal');if(!in_array($sensitivity,['normal','personal','financial','secret'],true))throw new \InvalidArgumentException('حساسیت متغیر معتبر نیست.');
        \Model::begin();try{
            \Model::execute("INSERT INTO proma_connect_template_variables (variable_key,title_fa,description_fa,value_type,source_resolver,sample_value,sensitivity,is_system,is_active,created_by,created_at) VALUES (?,?,?,?,?,?,?,0,1,?,NOW()) ON DUPLICATE KEY UPDATE title_fa=VALUES(title_fa),description_fa=VALUES(description_fa),value_type=VALUES(value_type),source_resolver=VALUES(source_resolver),sample_value=VALUES(sample_value),sensitivity=VALUES(sensitivity),updated_by=VALUES(created_by),updated_at=NOW()",[$key,$title,trim(strip_tags((string)($input['description_fa']??''))),$type,$resolver,trim((string)($input['sample_value']??'')),$sensitivity,$actorId]);
            $row=\Model::fetch('SELECT id FROM proma_connect_template_variables WHERE variable_key=?',[$key]);
            foreach(['ippanel'=>['%','%'],'smsir'=>['',''],'bale'=>['#','#'],'telegram'=>['{','}']]as$provider=>$wrap){$parameter=trim((string)($input['mapping'][$provider]??$key));if(!preg_match('/^[A-Za-z][A-Za-z0-9_]{1,119}$/',$parameter))throw new \InvalidArgumentException('نام پارامتر '.$provider.' معتبر نیست.');\Model::execute("INSERT INTO proma_connect_variable_provider_mappings (variable_id,provider_key,provider_parameter,wrapper_prefix,wrapper_suffix,is_active,updated_by,updated_at) VALUES (?,?,?,?,?,1,?,NOW()) ON DUPLICATE KEY UPDATE provider_parameter=VALUES(provider_parameter),wrapper_prefix=VALUES(wrapper_prefix),wrapper_suffix=VALUES(wrapper_suffix),is_active=1,updated_by=VALUES(updated_by),updated_at=NOW()",[(int)$row['id'],$provider,$parameter,$wrap[0]?:null,$wrap[1]?:null,$actorId]);}
            \Model::execute("INSERT INTO proma_connect_settings_audit (section_key,action_key,actor_id,after_json,request_id,created_at) VALUES ('variables','variable_saved',?,?,?,NOW())",[$actorId,json_encode(['key'=>$key,'type'=>$type,'sensitivity'=>$sensitivity]),\ErrorHandler::requestId()]);\Model::commit();
        }catch(\Throwable$e){\Model::rollBack();throw$e;}
    }
}
