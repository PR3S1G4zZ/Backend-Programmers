<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vincular cuenta social</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { background-color: #ffffff; margin: 20px auto; padding: 20px; max-width: 600px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; font-size: 24px; text-align: center; }
        p { color: #555; font-size: 16px; line-height: 1.5; }
        .btn { display: inline-block; padding: 10px 20px; color: #fff; background-color: #007bff; text-decoration: none; border-radius: 5px; font-weight: bold; margin-top: 20px; }
        .footer { margin-top: 30px; font-size: 12px; color: #999; text-align: center; }
        .center { text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Vincula tu cuenta</h1>
        <p>Hola,</p>
        <p>Hemos detectado un intento de inicio de sesión con una red social usando este correo electrónico. Si has sido tú, por favor haz clic en el botón de abajo para confirmar y vincular la cuenta.</p>
        <div class="center">
            <a href="{{ $linkUrl }}" class="btn" style="color: white !important;">Confirmar Vinculación</a>
        </div>
        <p>Si no has solicitado esto, puedes ignorar este correo de forma segura.</p>
        
        <div class="footer">
            <p>Este es un correo automático, por favor no respondas.</p>
        </div>
    </div>
</body>
</html>
