<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Redefinição de senha</title>
</head>
<body>

    <p>Olá, {{ $user->name }}.</p>

    <p>Recebemos uma solicitação para redefinir a senha da sua conta em <strong>{{ config('app.name') }}</strong>.</p>

    <p>
        <a href="{{ $url }}">Redefinir minha senha</a>
    </p>

    <p>Este link expira em <strong>{{ $expiration }} minutos</strong>.</p>

    <p>Se você não solicitou a redefinição de senha, nenhuma ação é necessária — sua senha permanece a mesma.</p>

    <p>
        Caso o botão acima não funcione, copie e cole o link abaixo no seu navegador:<br>
        <span>{{ $url }}</span>
    </p>

    <p>{{ config('app.name') }}</p>

</body>
</html>
