<?php

namespace App\Http\Controllers;

use App\Mail\PaymentReferenceMail;
use App\Mail\RestoreReferenceMail;
use App\Models\Token;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SettingsController extends Controller
{
    function install(Request $request)
    {
        $user = User::query()->where(
            'referer',$request['domain']
        )->first();
        if (is_null($user)) {
            return response('{"status":"error","msg":"Аккаунт не существует"}');
        }
        if (isset($user->pk_url,$user->secret_word,$user->email)) {
            return response('{"status":"error","msg":"Аккаунт уже активирован"}',200);
        } else {
            $user->pk_url = $request['payment_url'];
            $user->secret_word = $request['secret_word'];
            $user->email = $request['email'];
            $user->active = 1;
            $user->save();
            return response('{"status":"ok","msg":"Аккаунт успешно активирован"}',200);
        }
        return response('ok',200);
    }
    function settings_get(Request $request)
    {
        $user = User::query()->where(
            'referer',$request['domain']
        )->first();
        if(is_null($user)) {
            return response('{"status":"error","msg":"Аккаунт не существует"}');
        }
        if (isset($user->pk_url,$user->secret_word,$user->email))
        {
            return response('{"status":"ok","msg":"Аккаунт активирован"}',200);
        } else {
            return response('{"status":"error","msg":"Аккаунт не активироан"}',200);
        }
    }
    function mail(Request $request)
    {
        if(!$request->has('domain'))
            return response('{"status":"error","msg":"Отсутствует необходимый параметр"}',200);
        $user = User::query()->where('referer',$request->domain)->first();
        if(is_null($user)) {
            Log::error("Ошибка. {$request->domain} не существует");
            return response('{"status":"error","msg":"Такого домена не существует"}',200);
        }
        $token_string =bin2hex(random_bytes(16));
        $token = Token::query()->create([
            'token'         =>  $token_string,
            'account_id'     =>  $user->account_id
        ]);
        Log::debug($request->domain);
        try {
            Mail::to($user->email)->send(new RestoreReferenceMail($token_string));
        } catch(Exception $e) {
            Log::error("Ошибка отправки сообщения для {$request->domain}");
            return response('{"status":"error","msg":"Произошла ошибка при отправке письма"}',200);
        }
        return response('{"status":"ok","msg":"Ссылка отправлена. Срок действия  - 1 час"}',200);
    }
}
