<!DOCTYPE html>
<html>
<head>
    <title>Recuperación de Contraseña</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .button { display: inline-block; padding: 10px 20px; color: #ffffff; background-color: #007bff; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .footer { margin-top: 20px; font-size: 12px; color: #888; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Hola!</h2>
        <p>Has recibido este correo porque solicitaste restablecer tu contraseña para tu cuenta en Programmers.</p>
        <p>Haz clic en el siguiente botón para restablecer tu contraseña:</p>
        <a href="{{ $url }}" class="button">Restablecer Contraseña</a>
        <p>Este enlace expirará en 60 minutos.</p>
        <p>Si no solicitaste un restablecimiento de contraseña, no es necesario realizar ninguna acción.</p>
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
