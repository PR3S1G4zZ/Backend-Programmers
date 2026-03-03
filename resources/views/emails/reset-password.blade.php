<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Recuperar contraseña</title>
</head>

<body style="margin:0; padding:0; background:#0D0D0D; font-family:Arial, Helvetica, sans-serif;">

  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#0D0D0D; padding:40px 0;">
    <tr>
      <td align="center">

        <table role="presentation" width="100%" style="max-width:520px; background:#1A1A1A; border-radius:12px; padding:40px;">
          
          <!-- Logo -->
          <tr>
            <td align="center" style="padding-bottom:25px;">
              <div style="background:#00FF85; width:60px; height:60px; border-radius:12px; display:flex; align-items:center; justify-content:center;">
                <span style="font-size:32px; font-weight:bold; color:#0D0D0D;">&lt;/&gt;</span>
              </div>
            </td>
          </tr>

          <!-- Título -->
          <tr>
            <td align="center" style="color:#FFFFFF; font-size:24px; font-weight:700; padding-bottom:10px;">
              Recupera tu contraseña
            </td>
          </tr>

          <!-- Texto principal -->
          <tr>
            <td align="center" style="color:#CCCCCC; font-size:15px; line-height:22px; padding-bottom:30px;">
              Hola, hemos recibido una solicitud para restablecer la contraseña de tu cuenta en <strong style="color:#00FF85;">Programmers</strong>.
              <br><br>
              Haz clic en el siguiente botón para continuar:
            </td>
          </tr>

          <!-- BOTÓN -->
          <tr>
            <td align="center" style="padding-bottom:35px;">
              <a href="{{ $resetUrl }}" 
                style="
                  background:#00FF85;
                  color:#0D0D0D;
                  text-decoration:none;
                  padding:14px 26px;
                  border-radius:8px;
                  font-size:16px;
                  font-weight:bold;
                  display:inline-block;
                "
              >
                Restablecer contraseña
              </a>
            </td>
          </tr>

          <!-- Aviso -->
          <tr>
            <td align="center" style="color:#777; font-size:12px; line-height:18px;">
              Si tú no solicitaste este cambio, puedes ignorar este mensaje.
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td align="center" style="padding-top:30px; color:#555; font-size:11px;">
              © {{ date('Y') }} Programmers — Todos los derechos reservados.
            </td>
          </tr>
        </table>

      </td>
    </tr>
  </table>

</body>
</html>
