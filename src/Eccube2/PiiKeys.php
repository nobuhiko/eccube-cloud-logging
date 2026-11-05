<?php

namespace Nobuhiko\EccubeCloudLogging\Eccube2;

/**
 * EC-CUBE 2 系の標準の表で、個人情報・秘密が入る列の名前。Log::configure() の pii_keys の既定。
 * 独自に足した列は、使う側で array_merge(PiiKeys::DEFAULT, [...]) して渡す。
 */
final class PiiKeys
{
    public const DEFAULT = [
        // dtb_shipping
        'shipping_name01', 'shipping_name02', 'shipping_kana01', 'shipping_kana02',
        'shipping_tel01', 'shipping_tel02', 'shipping_tel03',
        'shipping_fax01', 'shipping_fax02', 'shipping_fax03',
        'shipping_zip01', 'shipping_zip02', 'shipping_zipcode',
        'shipping_addr01', 'shipping_addr02', 'shipping_company_name',
        'shipping_email',
        // dtb_order の注文者
        'order_name01', 'order_name02', 'order_kana01', 'order_kana02',
        'order_tel01', 'order_tel02', 'order_tel03',
        'order_fax01', 'order_fax02', 'order_fax03',
        'order_zip01', 'order_zip02', 'order_zipcode',
        'order_addr01', 'order_addr02', 'order_company_name',
        'order_email', 'order_birth',
        // dtb_customer・dtb_other_deliv・dtb_member
        'name01', 'name02', 'kana01', 'kana02',
        'tel01', 'tel02', 'tel03', 'fax01', 'fax02', 'fax03',
        'zip01', 'zip02', 'zipcode', 'addr01', 'addr02', 'company_name',
        'email', 'email_mobile', 'birth',
        // 秘密
        'password', 'password02', 'reminder_answer', 'salt', 'secret_key', 'auth_magic',
    ];
}
