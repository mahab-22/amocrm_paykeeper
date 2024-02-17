<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Оплата заказа</title>
</head>
<body>
<form id="pay_form" method="POST" action="{{$order->getOrderParams('form_url')}}" >
    <input type="hidden" name="sum" value="{{$order->getOrderTotal()}}"/>
    <input type="hidden" name="clientid" value="{{$order->getOrderParams('clientid')}}"/>
    <input type="hidden" name='orderid' value="{{$order->getOrderParams('orderid')}}"/>
    <input type="hidden" name='client_email' value="{{$order->getOrderParams('client_email')}}"/>
    <input type="hidden" name='client_phone' value="{{$order->getOrderParams('client_phone')}}"/>
    <input type="hidden" name='service_name' value="{{$order->getOrderParams('service_name')}}"/>
    <input type="hidden" name='cart' value='{{json_encode($order->getFiscalCart())}}'/>
    <input type="hidden" name='user_result_callback' value='{{$user_result_callback}}'/>
    <input type="hidden" name='sign' value="{{$sign}}"/>
</form>
<script>
    window.onload=function(){
        document.querySelector('#pay_form').submit();
    }
</script>
</body>
</html>
