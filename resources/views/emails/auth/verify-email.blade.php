<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Confirme seu e-mail</title>
</head>
<body>

    <p>Olá, {{ $user->name }}.</p>

    <p>Falta pouco para começar a usar o <strong>{{ config('app.name') }}</strong> — confirme seu e-mail para liberar o acesso à sua conta.</p>

    <p>
        <a href="{{ $url }}">Confirmar meu e-mail</a>
    </p>

    <p>Este link expira em <strong>{{ $expiration }} minutos</strong>.</p>

    <p>Se você não fez esse cadastro, nenhuma ação é necessária.</p>

    <p>
        Caso o botão acima não funcione, copie e cole o link abaixo no seu navegador:<br>
        <span>{{ $url }}</span>
    </p>

    <p>{{ config('app.name') }}</p>

</body>
</html>
