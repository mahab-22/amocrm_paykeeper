<?php

namespace App\Http\Controllers;


use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Enum\Invoices\BillStatusEnumCode;
use AmoCRM\Enum\InvoicesCustomFieldsEnums;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Filters\CatalogsFilter;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\CatalogElementModel;
use AmoCRM\Models\CustomFieldsValues\SelectCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\SelectCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\SelectCustomFieldValueModel;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use League\OAuth2\Client\Token\AccessToken;

class CallbackController extends Controller
{
    function __invoke(Request $request)
    {
        //Log::debug($request);
        if(empty($request->id) || empty($request->orderid)|| empty($request->sum) || empty($request->service_name) || empty($request->key))
        {
            Log::error("Ошибка оповещения. Не хватает параметров");
            return response("Ошибка оповещения. Не хватает параметров", 200);
        }
        $clientid =$request->clientid??'';
        $data = explode('|',$request->service_name);
        if (is_array($data)){
            if (count($data) ==2){
                $account_id = $data[0];
                $referer = $data[1];
            }
        }
        else
        {
            Log::error("Параметр service_name имеет не правильный формат");
            return response("Параметр service_name имеет не  правильный формат", 200);
        }
        $user = User::query()->where('account_id',$account_id)->first();
        if(is_null($user))
        {
            Log::error("Аккаунт не найден {$account_id}");
            return response("Аккаунт не найден {$account_id}", 200);
        }
        $order = Order::query()->where([
            'account_id'=>$account_id,
            'orderid'=>$request->orderid,
        ])->first();
        if(empty($order))
        {
            return response("Заказ {$request->orderid} для {$account_id} не найден ", 200);
        }
        if($order->payed=='payed')
        {
            if ($request->key = md5 ($request->id . sprintf ("%.2lf", $request->sum).$clientid.$request->orderid.$user->secret_word))
            {
                $hash = md5($request->id.$user->secret_word);
                return response("OK {$hash}");
            }

        }

        /* Создаем AMO клиента */

        $AMOClient = new AmoCRMApiClient(
            $_ENV['AMO_APP_ID'],
            $_ENV['AMO_SECRET_KEY'],
            $_ENV['CREDENTIALS_URI']
        );
        $AMOClient->setAccountBaseDomain($user->referer);

        $token_array = [
            'token_type' => 'Bearer',
            'access_token' => $user->access_token,
            'refresh_token' => $user->refresh_token,
            'expires' => $user->access_token_expires,
        ];
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
        });
        $catalogsFilter = new CatalogsFilter();
        $catalogsFilter->setType(EntityTypesInterface::INVOICES_CATALOG_TYPE_STRING);
        $invoicesCatalog = $AMOClient->catalogs()->get($catalogsFilter)->first();

        try {
            $invoice = $AMOClient
                ->catalogElements($invoicesCatalog->getId())
                ->getOne($request['orderid']);
        } catch (AmoCRMApiException $e) {
            return response("Ошибка получения счета - {$e}",200);

        }

        $invoiceForUpdate = (new CatalogElementModel())
            ->setId($invoice->getId())
            ->setCatalogId($invoicesCatalog->getId())
            ->setCustomFieldsValues(
                (new CustomFieldsValuesCollection())
                    ->add(
                        (new SelectCustomFieldValuesModel())
                            ->setFieldCode(InvoicesCustomFieldsEnums::STATUS)
                            ->setValues(
                                (new SelectCustomFieldValueCollection())
                                    ->add((new SelectCustomFieldValueModel())->setEnumCode(BillStatusEnumCode::PAID)) //Текст должен совпадать с одним из значений поля статус
                            )
                    )
            );

        $catalogElementsService = $AMOClient->catalogElements($invoicesCatalog->getId());
        try {
        $updatedInvoice = $catalogElementsService->updateOne($invoiceForUpdate);
        } catch (AmoCRMApiException $e) {
            Log::error("Ошибка при установке статуса - {$e}");
            return response("Ошибка при установке статуса ",200);
        }
        $order->paykeeper_id = $request->id;
        $order->payed = 'payed';
        $order->save();
        //Log::debug($request);
        if ($request->key = md5 ($request->id . sprintf ("%.2lf", $request->sum).$clientid.$request->orderid.$user->secret_word))
        {
            $hash = md5($request->id.$user->secret_word);
            return response("OK {$hash}");
        }
        else
        {
            return response("Error! Hash mismatch");
        }


    }
}
