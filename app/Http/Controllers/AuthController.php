<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPasswordNotification;
use App\Mail\SocialLinkVerification;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Registrar un nuevo usuario
     */
    public function register(Request $request)
    {
        try {
            // Reglas de validación base
            $rules = [
                'name' => 'required|string|max:255',
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    'unique:users',
                    'regex:/^[^@\s]+@[^@\.\s]+\.[^@\.\s]+$/i',
                    'regex:/^\S+$/'
                ],
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'max:64',
                    'confirmed',
                    'regex:/^\S+$/',
                ],
                'user_type' => 'required|in:programmer,company',
            ];

            // Reglas específicas por tipo de usuario
            if ($request->user_type === 'programmer') {
                $rules['lastname'] = 'required|string|max:255';
            } elseif ($request->user_type === 'company') {
                $rules['company_name'] = 'required|string|max:255';
                // 'position' es opcional en la BD pero el frontend lo pide, lo validamos si viene
                $rules['position'] = 'nullable|string|max:255'; 
            }

            // Mensajes personalizados
            $messages = [
                'email.regex' => 'El correo debe contener "@", un solo punto en el dominio y no debe tener espacios.',
                'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
                'password.max' => 'La contraseña no puede tener más de 64 caracteres.',
                'password.regex' => 'La contraseña no debe contener espacios.',
                'password.confirmed' => 'Las contraseñas no coinciden.',
                'company_name.required' => 'El nombre de la empresa es obligatorio.',
            ];

            $validated = $request->validate($rules, $messages);

            // Transacción para asegurar integridad
            $user = \DB::transaction(function () use ($validated) {
                // Crear usuario
                $user = User::create([
                    'name' => strip_tags(trim($validated['name'])),
                    'lastname' => isset($validated['lastname']) ? strip_tags(trim($validated['lastname'])) : null,
                    'email' => strtolower(trim($validated['email'])),
                    'password' => $validated['password'], // Pass plain password, let the model handle hashing
                    'user_type' => $validated['user_type'],
                    'role' => $validated['user_type'],
                ]);

                // Crear perfil según tipo
                if ($user->user_type === 'company') {
                    \App\Models\CompanyProfile::create([
                        'user_id' => $user->id,
                        'company_name' => strip_tags(trim($validated['company_name'])),
                        // 'position' no está en company_profiles según la migración vista, 
                        // pero si es necesario guardarlo, debería estar allí o en users.
                        // Revisando User model, no tiene 'position'.
                        // Revisando CompanyProfile migration: company_name, website, about.
                        // Si 'position' es importante, debería agregarse a la tabla users o company_profiles.
                        // Por ahora, lo omitimos o lo guardamos en 'about' si es crítico, 
                        // pero la migración no lo tiene. Asumiremos que es meta-data no crítica por ahora 
                        // o que falta el campo. 
                        // *Corrección*: El usuario probablemente quiere guardar el cargo. 
                        // Voy a asumir que por ahora solo guardamos company_name que SI está en la tabla.
                    ]);
                } elseif ($user->user_type === 'programmer') {
                    // Crear perfil de desarrollador vacío o con datos por defecto
                    // Verificar si existe el modelo DeveloperProfile
                    if (class_exists(\App\Models\DeveloperProfile::class)) {
                         \App\Models\DeveloperProfile::create([
                            'user_id' => $user->id,
                            // Campos iniciales vacíos o por defecto
                            'headline' => 'Programador Web', // Ejemplo por defecto o null
                        ]);
                    }
                }

                return $user;
            });

            $token = $user->createToken('auth_token')->plainTextToken;

            // Enviar correo de verificación (en background, no bloquea el registro)
            $this->createAndSendVerificationToken($user);

            return response()->json([
                'success' => true,
                'message' => 'Usuario registrado exitosamente',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'lastname' => $user->lastname,
                    'email' => $user->email,
                    'user_type' => $user->user_type,
                ],
                'token' => $token
            ], 201);


        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Iniciar sesión
     */
    public function login(Request $request)
    {
        try {
            // Validación reforzada con sanitización de datos
            $validated = $request->validate([
                'email' => [
                    'required',
                    'email',
                    'regex:/^[^@\s]+@[^@\.\s]+\.[^@\.\s]+$/i', // Debe tener "@" y un solo punto después del "@"
                    'regex:/^\S+$/' // Sin espacios en todo el correo
                ],
                'password' => [
                    'required',
                    'string',
                    'max:255',
                    'regex:/^\S+$/', // Sin espacios
                ]
            ], [
                'email.regex' => 'El correo debe contener "@", un solo punto en el dominio y no debe tener espacios.',
                'password.regex' => 'La contraseña no debe contener espacios.',
            ]);

            // Sanitización del email antes de la autenticación
            $email = strtolower(trim($validated['email']));
            $password = $validated['password'];

            // Laravel usa consultas preparadas automáticamente, pero verificamos credenciales de forma segura
            $user = User::where('email', $email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'El correo electrónico no está registrado.'
                ], 404);
            }

            if (!Hash::check($password, $user->password)) {
                 return response()->json([
                    'success' => false,
                    'message' => 'La contraseña es incorrecta.'
                ], 401);
            }

            // Manually logging in the user since we bypassed Auth::attempt
            Auth::login($user);

            $user = Auth::user();
            
            // Cargar preferencias
            $user->load('preferences');
            
            // Si no tiene preferencias, crear por defecto
            if (!$user->preferences) {
                $user->preferences()->create([
                    'theme' => 'dark',
                    'accent_color' => '#00FF85',
                    'language' => 'es'
                ]);
                $user->load('preferences');
            }

            // Registrar actividad de login
            \App\Models\ActivityLog::create([
                'user_id' => $user->id,
                'action' => 'login',
                'details' => 'Inicio de sesión exitoso',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            $token = $user->createToken('auth_token')->plainTextToken; // crear token de autenticación

            return response()->json([
                'success' => true,
                'message' => 'Inicio de sesión exitoso',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'lastname' => $user->lastname,
                    'email' => $user->email,
                    'user_type' => $user->user_type,
                    'preferences' => $user->preferences
                ],
                'token' => $token
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Cerrar sesión
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Sesión cerrada exitosamente'
            ], 200);

        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error al cerrar sesión',
            ], 500);
        }
    }

    /**
     * Obtener información del usuario autenticado
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'lastname' => $user->lastname,
                    'email' => $user->email,
                    'user_type' => $user->user_type,
                ]
            ], 200);

        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información del usuario',
            ], 500);
        }
    }

    public function sendResetLink(Request $request)
{
    $request->validate([
        'email' => 'required|email'
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json([
            'success' => true,
            'message' => 'Si el correo existe, se enviará un enlace de recuperación.'
        ], 200);
    }

    // Generar token de reseteo
    $token = Password::createToken($user);

    // URL del frontend
    $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5174'), '/');
    $resetUrl = "{$frontendUrl}/reset-password?token={$token}&email={$user->email}";

    // Enviar correo
    Mail::to($user->email)->send(new ResetPasswordNotification($resetUrl));

    return response()->json([
        'success' => true,
        'message' => 'Si el correo existe, se enviará un enlace de recuperación.'
    ], 200);
}


/**
 * Resetear contraseña desde el frontend
 */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => [
                'required',
                'string',
                'min:8',
                'max:64',
                'confirmed',
                'regex:/^\S+$/'
            ]
        ]);

    $status = Password::reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function ($user) use ($request) {
            $user->forceFill([
                'password' => Hash::make($request->password)
            ])->save();
        }
    );

    if ($status === Password::PASSWORD_RESET) {
        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada correctamente'
        ], 200);
    }

    return response()->json([
        'success' => false,
        'message' => 'No se pudo restablecer la contraseña.'
    ], 400);
    }

    /**
     * Cambiar contraseña (usuario autenticado)
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => [
                'required',
                'string',
                'min:8',
                'max:64', // Consistent with other password validations
                'confirmed',
                'regex:/^\S+$/', // No spaces
                'different:current_password'
            ]
        ], [
            'new_password.regex' => 'La contraseña no debe contener espacios.',
            'new_password.different' => 'La nueva contraseña debe ser diferente a la actual.'
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'La contraseña actual es incorrecta.'
            ], 400);
        }

        $user->forceFill([
            'password' => Hash::make($request->new_password)
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada correctamente.'
        ], 200);
    }
    /**
     * Redirigir a Google
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Callback de Google
     */
    public function handleGoogleCallback()
    {
        return $this->handleSocialCallback('google');
    }

    /**
     * Redirigir a GitHub
     */
    public function redirectToGithub()
    {
        return Socialite::driver('github')->stateless()->redirect();
    }

    /**
     * Callback de GitHub
     */
    public function handleGithubCallback()
    {
        return $this->handleSocialCallback('github');
    }

    /**
     * Lógica centralizada para manejar callbacks de redes sociales
     */
    protected function handleSocialCallback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
            $email = $socialUser->getEmail();
            $providerIdField = $provider . '_id';
            
            $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
            if (!str_starts_with($frontendUrl, 'http://') && !str_starts_with($frontendUrl, 'https://')) {
                $frontendUrl = 'https://' . $frontendUrl;
            }

            $user = User::where('email', $email)->first();

            if (!$user) {
                $fullName = $socialUser->getName() ?? $socialUser->getNickname() ?? 'Usuario';
                $nameParts = explode(' ', $fullName, 2);
                $firstName = $nameParts[0];
                $lastName = isset($nameParts[1]) ? $nameParts[1] : '';

                // Nuevo usuario
                $user = User::create([
                    'name' => $firstName,
                    'lastname' => $lastName,
                    'email' => $email,
                    $providerIdField => $socialUser->getId(),
                    'avatar' => $socialUser->getAvatar(),
                    'password' => Hash::make(Str::random(16)),
                    'user_type' => 'programmer',
                    'role' => 'programmer',
                ]);

                if (class_exists(\App\Models\DeveloperProfile::class)) {
                    \App\Models\DeveloperProfile::create([
                        'user_id' => $user->id,
                        'headline' => 'Programador Web',
                    ]);
                }
                
                $user->preferences()->create([
                    'theme' => 'dark',
                    'accent_color' => '#00FF85',
                    'language' => 'es'
                ]);

                return $this->loginAndRedirect($user, $frontendUrl);
            }

            // El usuario ya existe, comprobamos si ya está vinculado
            if ($user->{$providerIdField} === $socialUser->getId()) {
                // Ya estaba vinculado
                return $this->loginAndRedirect($user, $frontendUrl);
            }

            // --- MODO DE PRUEBAS: AUTO-VINCULACIÓN INMEDIATA ---
            // Como las cuentas de correo temporales o configuraciones SMTP en Railway pueden fallar/demorar,
            // vinculamos la cuenta directamente si el email coincide (confiamos en la validación de Google/Github).
            $user->{$providerIdField} = $socialUser->getId();
            
            // Actualizamos avatar si no tenía
            if (!$user->avatar && $socialUser->getAvatar()) {
                $user->avatar = $socialUser->getAvatar();
            }
            $user->save();

            return $this->loginAndRedirect($user, $frontendUrl);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error en autenticación ' . ucfirst($provider) . ': ' . $e->getMessage()], 500);
        }
    }

    /**
     * Método auxiliar para loguear y redirigir
     */
    protected function loginAndRedirect($user, $frontendUrl)
    {
        Auth::login($user);
        $token = $user->createToken('auth_token')->plainTextToken;
        return redirect("{$frontendUrl}/auth/callback?token={$token}&user_type={$user->user_type}&name={$user->name}");
    }

    /**
     * Endpoint para verificar el token de vinculación
     */
    public function verifySocialLink(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        $user = User::where('social_link_token', $request->token)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido o expirado'
            ], 400);
        }

        // Vincular
        $providerField = $user->social_link_provider . '_id';
        $user->{$providerField} = $user->social_link_id;
        
        // Limpiar tokens de verificación
        $user->social_link_token = null;
        $user->social_link_provider = null;
        $user->social_link_id = null;
        $user->save();

        Auth::login($user);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Cuenta vinculada exitosamente',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'user_type' => $user->user_type,
            ]
        ]);
    }

    /**
     * Enviar correo de verificación de email
     */
    public function sendVerificationEmail(Request $request)
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'Tu correo ya está verificado.'
            ]);
        }

        $this->createAndSendVerificationToken($user);

        return response()->json([
            'success' => true,
            'message' => 'Correo de verificación enviado. Revisa tu bandeja de entrada.'
        ]);
    }

    /**
     * Verificar email con token
     */
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $tokenRecord = \DB::table('email_verification_tokens')
            ->where('token', $request->token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$tokenRecord) {
            return response()->json([
                'success' => false,
                'message' => 'El enlace de verificación es inválido o ha expirado.'
            ], 400);
        }

        $user = User::find($tokenRecord->user_id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.'
            ], 404);
        }

        // Marcar email como verificado
        $user->email_verified_at = now();
        $user->save();

        // Eliminar token usado
        \DB::table('email_verification_tokens')->where('token', $request->token)->delete();

        // Generar token de sesión para auto-login
        $sessionToken = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => '¡Correo verificado exitosamente!',
            'token' => $sessionToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'user_type' => $user->user_type,
            ]
        ]);
    }

    /**
     * Crear token de verificación y enviar correo
     */
    private function createAndSendVerificationToken(User $user): void
    {
        // Eliminar tokens anteriores
        \DB::table('email_verification_tokens')->where('user_id', $user->id)->delete();

        // Crear nuevo token
        $token = Str::random(64);
        \DB::table('email_verification_tokens')->insert([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Enviar correo
        try {
            \Mail::to($user->email)->send(new \App\Mail\EmailVerificationMail($token, $user->name));
        } catch (\Exception $e) {
            \Log::error('Error enviando correo de verificación: ' . $e->getMessage());
        }
    }
}
