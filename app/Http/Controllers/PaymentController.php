<?php

namespace App\Http\Controllers;

use AmoCRM\Enum\InvoicesCustomFieldsEnums;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Filters\CompaniesFilter;
use AmoCRM\Filters\CustomersFilter;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\AccountModel;
use AmoCRM\Models\CompanyModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\ItemsCustomFieldValueModel;
use App\Models\Order;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use AmoCRM\Client\AmoCRMApiClient;
use League\OAuth2\Client\Token\AccessToken;
use AmoCRM\Filters\CatalogsFilter;
use AmoCRM\Filters\ContactsFilter;
use AmoCRM\Models\CatalogElementModel;
use App\Utils\PaykeeperPayment;

class PaymentController extends Controller
{
    function __invoke(Request $request)
    {

        $AMOClient = new AmoCRMApiClient(
            $_ENV['AMO_APP_ID'],
            $_ENV['AMO_SECRET_KEY'],
            $_ENV['CREDENTIALS_URI']
        );
        $user = User::query()->where('account_id',$request->account_id)->first();

        if(is_null($user))
        {
            return response("<h2>Такого аккаунта не существует!</h2", 200);
            Log::log("Попытка запроса несуществующего аккаунта {$request->account_id}");
        }
        if(($user->active==0) || (empty($user->pk_url)) || (empty($user->secret_word)))
        {
            return response("<h2>Аккаунт не активирован. Обратитесь к продавцу.</h2", 200);
        }
        if((empty($user->pk_url)) || (empty($user->secret_word)))
        {
            return response("<h2>Аккаунт продавца не настроен. Обратитесь к продавцу.</h2", 200);
        }
        $AMOClient->setAccountBaseDomain($user->referer);

        $token_array = [
            'token_type' => 'Bearer',
            'access_token' => $user->access_token,
            'refresh_token' => $user->refresh_token,
            'expires' => $user->access_token_expires,
        ];
        //dump($token_array);
        $AMO_token = new AccessToken($token_array);
        $AMOClient->setAccessToken($AMO_token);
        $AMOClient->onAccessTokenRefresh(function ($raw_token,$baseDomain){
            $user=User::query()->where('referer',$baseDomain)->first();
            $token_array = $raw_token->jsonSerialize();
            $user->access_token = $token_array['access_token'];
            $user->refresh_token = $token_array['refresh_token'];
            $user->access_token_expires = $token_array['expires'];
            $user->refresh_token_expires = time()+7776000;
            $user->save();
//            dump($token_array);
//            dump($baseDomain);
        });
//        $account = $AMOClient->account()->getCurrent(AccountModel::getAvailableWith());
//        dump("Аккаунт");
//        dump($account);
        $catalogsFilter = new CatalogsFilter();
        $catalogsFilter->setType(EntityTypesInterface::INVOICES_CATALOG_TYPE_STRING);
        $invoicesCatalog = $AMOClient->catalogs()->get($catalogsFilter)->first();

        try {
        $invoice = $AMOClient
            ->catalogElements($invoicesCatalog->getId())
            ->getOne($request['invoice_id'],[CatalogElementModel::INVOICE_LINK]);
        } catch (Exception $e) {
            //$er=printError($e);
            return response("<h2>Ошибка получения счета. Возможно такой счет отсутствует</h2>",200);
        }
        if ($invoiceLink = $invoice->getInvoiceLink()) {
            $user_result_callback = $invoiceLink;
        } else
        {
            return response("<h2>Отсутствует ссылка на  счет. Обратитесь в техподдржку сервиса</h2", 200);
            Log::error('Отсутствует ссылка на  счет. Счет ниже');
            Log::error($invoice);
        }


//        dump("Кастомфилд счета");
//        dump($customFieldValues);
        $customFieldValues = $invoice->getCustomFieldsValues();
        $pk_obj = new PaykeeperPayment();
        if ($vatValue = $customFieldValues->getBy('fieldCode', InvoicesCustomFieldsEnums::VAT_TYPE)) {
//            dump('НДС');
//            dump($vatValue->getValues()->first()->toApi());
            $pk_obj->setUseTaxes();
            $vat_field = $vatValue->getValues()->first()->toApi()['enum_code'];
            if($vat_field==InvoicesCustomFieldsEnums::VAT_NOT_INCLUDED)
            {
                $pk_obj->tax_included =false;
            }
            else if($vat_field==InvoicesCustomFieldsEnums::VAT_INCLUDED)
            {
                $pk_obj->tax_included =true;
            }

        };

        /** Получаем контакты покупателя */

        $client_phone = '';
        $client_email = '';
        $clientid='';

        if ($payerValue = $customFieldValues->getBy('fieldCode', InvoicesCustomFieldsEnums::PAYER)) {
//            dump("Покупатель");
            //dump($payerValue->getValues()->first()->getValue());
            $payer_obj = $payerValue->getValues()->first()->getValue();
            if($payer_obj['entity_type']=='companies')
            {
                $companyServise = $AMOClient->companies();
                $filter = new CompaniesFilter();
                $filter->setIds([$payer_obj['entity_id']]);
                $client_obj = $companyServise->get($filter)->first();
            }
            if($payer_obj['entity_type']=='contacts')
            {
                $contactServise = $AMOClient->contacts();
                $filter = new ContactsFilter();
                $filter->setIds([$payer_obj['entity_id']]);
                $client_obj = $contactServise->get($filter)->first();
            }

            if(isset($client_obj))
            {
                //Log::debug($client_obj);
                $phone_field = $client_obj->getCustomFieldsValues()->getBy('fieldCode', 'PHONE');

                $email_field = $client_obj->getCustomFieldsValues()->getBy('fieldCode', 'EMAIL');
                if(isset($email_field))
                {
                    $client_email = $email_field->getValues()->first()->getValue();
                }
                if(isset($client_phone))
                {
                    $client_phone = $phone_field->getValues()->first()->getValue();
                }
            }

            $clientid_field   = $customFieldValues->getBy('fieldCode', InvoicesCustomFieldsEnums::PAYER);
            if(isset($clientid_field))
            {
                $clientid = $clientid_field->getValues()->first()->getValue()['name'];
            }

        }
//        else
//        {
//            return response("<h2>В счете отсутствует контрагент. Обратитесть к продавцу</h2", 200);
//
//        }
        if (is_null($customFieldValues->getBy('fieldCode', InvoicesCustomFieldsEnums::PRICE))) {
            return response('<h2>Платежная система не поддерживает счета без суммы</h2>',200);
            die;
        }


        $sum = $customFieldValues->getBy('fieldCode', InvoicesCustomFieldsEnums::PRICE)->getValues()->first()->getValue();
        $orderid = $invoice->getId();
        $service_name = $request->account_id.'|'.$user->referer;

        $pk_obj->setOrderParams(
            // sum
            number_format($sum,2,'.',''),
            //clientid
            $clientid,
            //orderid
            $orderid,
            //client_email
            $client_email,
            //client_phone
            $client_phone,
            //service_name
            $service_name,
            //payment form url
            'https://'.$user->pk_url.'/create',
            //secret key
            $user->secret_word
        );

        $item_index = 0;
        $total_sum =0;
        if ($itemsValue = $customFieldValues->getBy('fieldCode', InvoicesCustomFieldsEnums::ITEMS)) {
            /** @var ItemsCustomFieldValueModel $item */
            foreach ($itemsValue->getValues() as $item)
            {
                $tax_amount = 0;
                $tax_rate = 0;
                $taxes = array("tax" => "none", "tax_sum" => 0);
                $name = $item->toArray()["description"];
                $quantity = $item->toArray()["quantity"];
                if($pk_obj->use_taxes) $tax_rate=$item->toArray()['vat_rate_value'];
                $price = $item->toArray()["unit_price"];
                $position_sum = $price*$quantity;

                /* Применяем скидки */

                if($item->toArray()["discount"]["value"]>0)
                {
                    if($item->toArray()["discount"]['type']=='percentage')
                    {
                        $position_sum = $position_sum - ($position_sum/100)*$item->toArray()["discount"]["value"];
                    }
                    else
                    {
                        $position_sum = $position_sum - $item->toArray()["discount"]["value"];
                    }
                    $price =number_format($position_sum/$quantity,2,'.','');
                }

                /* НДС сверху суммы */

                if($pk_obj->use_taxes && !$pk_obj->tax_included)
                {
                    $price = number_format($price+(($price/100)*$tax_rate),2,'.','');
                    $position_sum = number_format($price*$quantity,2,'.','');
                }
                if ($quantity == 1 && $pk_obj->single_item_index < 0)
                    $pk_obj->single_item_index = $item_index;
                if ($quantity > 1 && $pk_obj->more_then_one_item_index < 0)
                    $pk_obj->more_then_one_item_index = $item_index;
                $taxes = $pk_obj->setTaxes($tax_rate);
                $pk_obj->updateFiscalCart($pk_obj->getPaymentFormType(),
                    $name, $price, $quantity, $position_sum, $taxes["tax"]);
                $total_sum+=$position_sum;
                $item_index++;
            }

        }
        else
        {
           return response("<h2>В счете отсутствуют товары. Обратитесть к продавцу</h2", 200);


        }

        $pk_obj->order_total = $total_sum;
        $order = Order::query()->where([
            'orderid'       =>  $pk_obj->getOrderParams("orderid"),
            'account_id'    =>  $user->account_id
        ])->first();

        if(is_null($order))
        {
            $order = Order::query()->create
            ([
                'orderid'       =>  $pk_obj->getOrderParams("orderid"),
                'account_id'    =>  $user->account_id,
                'referer'       =>  $user->referer,
                'sum'           =>  $pk_obj->order_total,
                'cart'          => json_encode($pk_obj->fiscal_cart),
                'payed'         => 'new'
            ]);
        }
        else
        {
            $order->orderid = $pk_obj->getOrderParams("orderid");
            $order->account_id    =  $user->account_id;
            $order->referer       =  $user->referer;
            $order->sum           =  $pk_obj->order_total;
            $order->cart          = json_encode($pk_obj->fiscal_cart);
            $order->payed         = 'new';
            $order->save();
        }

        $secret_seed = $user->secret_word;

        $to_hash =$sum . $clientid. $orderid . $service_name . $client_email . $client_phone . $secret_seed ;
        $sign = hash('sha256' , $to_hash);
        return view('payment_form',['order'=>$pk_obj,'sign'=>$sign, 'user_result_callback'=>$user_result_callback]);
        //dump($pk_obj);

    }
}
