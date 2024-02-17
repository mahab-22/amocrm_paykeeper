<?php

namespace App\Http\Controllers;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Http\Request;
use App\Models\Token;

class ChangeSettingsController extends Controller
{
   function show_form (Request $request,$token_)
   {
        $token = Token::query()->where('token',$token_)->first();
       if(empty($token))
       {
           return response("<h2>Такая ссылка отсутствует</h2>",200);
       }
       $diff_in_minute = now()->diffInMinutes($token->updated_at);
       if($diff_in_minute>60 )
       {
           return response("<h2>Ссылка просрочена</h2>",200);
       }
       if($token->active==0)
       {
           return response("<h2>Ссылка уже использована</h2>",200);
       }
       $user = User::query()->where('account_id',$token->account_id)->first();
        return view('change_settings_form',['user'=>$user]);
   }
   function save(Request $request,$token_)
   {
        $request->validate([
            'email'=>'required',
            'pk_url'=>'required',
            'secret_word'=>'required'
        ]);
        $token =Token::query()->where('token',$token_)->first();
        if (is_null($token))
        {
            return response("<h2>Такая ссылка отсутствует</h2>",200);
        }
        $user = User::query()->where('account_id',$token->account_id)->first();
       if (is_null($token))
       {
           return response("Аккаунт не найден",200);
       }

       $user->email=$request->email;
       $user->pk_url=$request->pk_url;
       $user->secret_word=$request->secret_word;
       $user->save();
       $token->active=0;
       $token->save();
       return response("<h2>Изменение параметров прошло успешно</h2>", 200);
   }
}
