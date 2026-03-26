<x-mail::message>
# Hola {{ $userName }},

Gracias por registrarte en **Programmers**. Para completar tu registro, por favor verifica tu correo electrónico haciendo clic en el botón de abajo.

<x-mail::button :url="$url">
Verificar mi cuenta
</x-mail::button>

Si no creaste una cuenta, puedes ignorar este mensaje.

Saludos,
El equipo de Programmers
</x-mail::message>
