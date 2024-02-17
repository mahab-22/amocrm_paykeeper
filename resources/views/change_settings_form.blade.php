<!doctype html>
<html lang="ru" >
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
    <script src="../js/bootstrap.js"></script>
    <link rel="stylesheet" href="../css/bootstrap.css">

</head>
<body >
@if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<div class="container mt-4 ">
    <div class="row align-items-center" >
        <div class="col-lg-6 ">
            <form  class="align-middle " method="post">
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">Адрес электронной почты</label>
                    <input type="email" class="form-control" id="email" name="email" >
                </div>
                <div class="mb-3">
                    <label for="pk_url" class="form-label">Адрес личного кабинета</label>
                    <input type="text" class="form-control" id="pk_url" name="pk_url">
                </div>
                <div class="mb-3">
                    <label for="secret_word" class="form-label">Секретное слово</label>
                    <input type="text" class="form-control" id="secret_word" name="secret_word">
                </div>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
