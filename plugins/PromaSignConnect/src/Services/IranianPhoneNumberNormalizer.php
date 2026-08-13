<?php
namespace Proma\Plugins\SignConnect\Services;
final class IranianPhoneNumberNormalizer
{
    public static function normalize(string $input): string
    {
        $input=strtr($input,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        $digits=preg_replace('/[^0-9]/','',$input);
        if(str_starts_with($digits,'0098'))$digits=substr($digits,2);
        elseif(str_starts_with($digits,'98'))$digits=$digits;
        elseif(str_starts_with($digits,'09'))$digits='98'.substr($digits,1);
        elseif(str_starts_with($digits,'9'))$digits='98'.$digits;
        if(!preg_match('/^989\d{9}$/',$digits))throw new \InvalidArgumentException('شماره موبایل ایران معتبر نیست.');
        return $digits;
    }
    public static function mask(string $canonical): string{return substr($canonical,0,5).'****'.substr($canonical,-3);}
}
