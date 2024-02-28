<?php

namespace App\Http\Controllers;

use App\Models\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Client\Token\AccessToken;
use AmoCRM\Client\AmoCRMApiClient;
use Exception;

class InstallController extends Controller
{

    function install(Request $request)
    {
        $request->validate([
          'code' => ['required'],
          'referer' => ['required'],
          'client_id' => ['required']
        ]);
        $AMOClient = new AmoCRMApiClient(
            $_ENV['AMO_APP_ID'],
            $_ENV['AMO_SECRET_KEY'],
            $_ENV['CREDENTIALS_URI']
        );
        $AMOClient->setAccountBaseDomain($request->input('referer'));
        $raw_token = $AMOClient->getOAuthClient()->getAccessTokenByCode($request->input('code'));
        $token_array = $raw_token->jsonSerialize();

        $AMO_token = new AccessToken($token_array);
        $AMOClient->setAccessToken($AMO_token);
        try {
            $account = $AMOClient->account()->getCurrent()->toArray();
        }
        catch (Exception $exception) {
            Log::error("Ошибка получения данных аккаунта {$request->input('referer')}");
            return response("Ошибка получения данных аккаунта",200);
        }
        $user = User::query()->where('account_id' , $account['id'])->first();
        if(is_null($user)) {
            $user = User::query()->create(
                [
                    'client_id' => $request->input('client_id'),
                    'account_id' => $account['id'],
                    'referer' => $request->input('referer'),
                    'access_token'=>$token_array['access_token'],
                    'access_token_expires'=>$token_array['expires'],
                    'refresh_token'=> $token_array['refresh_token'],
                    'refresh_token_expires'=> time()+7776000,
                ]);
        } elseif (isset($user)) {
            $user->referer = $request->input('referer');
            $user->access_token=$token_array['access_token'];
            $user->access_token_expires=$token_array['expires'];
            $user->refresh_token= $token_array['refresh_token'];
            $user->refresh_token_expires= time()+7776000;
            $user->save();
        }
        return response("Installed successfully", 200);
    }
    function deactivate(Request $request)
    {
        $request->validate([
            'client_uuid' => ['required'],
            'account_id' => ['required']
        ]);

        $user = User::query()->where('account_id',$request->account_id)->first();
        if (is_null($user))
        {
            Log::error("Деинсталляция. Пользователь {$request->account_id} отсутствует");
            return \response("При деинсталляции произошла ошибка. Ползователь {$request->account_id} отсутствует");
        }
        $user->active=0;
        $user->email=null;
        $user->secret_word=null;
        $user->pk_url=null;
        $user->save();
        return response('Uninstalled successfully',200);
    }
}
