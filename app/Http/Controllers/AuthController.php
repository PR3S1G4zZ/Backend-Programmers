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
use Laravel\Socialite\Facades\Socialite;

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
                    'password' => Hash::make($validated['password']),
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
                            'title' => 'Programador Web', // Ejemplo por defecto o null
                        ]);
                    }
                }

                return $user;
            });

            $token = $user->createToken('auth_token')->plainTextToken;

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
            'max:15',
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
                'max:15', // Consistent with other password validations
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
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                // Si el usuario no existe, lo creamos (como programador por defecto o pide completar perfil)
                // Para simplificar, lo creamos como Programador
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'password' => Hash::make(\Illuminate\Support\Str::random(16)), // password aleatoria
                    'user_type' => 'programmer', // Default
                    'role' => 'programmer',
                ]);

                // Crear perfil vacío
                if (class_exists(\App\Models\DeveloperProfile::class)) {
                     \App\Models\DeveloperProfile::create([
                        'user_id' => $user->id,
                        'title' => 'Programador Web',
                    ]);
                }
                
                // Preferencias por defecto
                $user->preferences()->create([
                    'theme' => 'dark',
                    'accent_color' => '#00FF85',
                    'language' => 'es'
                ]);

            } else {
                // Si existe, actualizamos google_id si no lo tiene
                if (!$user->google_id) {
                    $user->google_id = $googleUser->getId();
                    $user->avatar = $googleUser->getAvatar();
                    $user->save();
                }
            }

            // Login manual
            Auth::login($user);
            $token = $user->createToken('auth_token')->plainTextToken;

            // Retornamos un script que envía el token al opener (popup) o redirige con token en URL
            // Como es SPA, lo mejor es redirigir al frontend con el token en la URL
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            
            return redirect("{$frontendUrl}/auth/callback?token={$token}&user_type={$user->user_type}&name={$user->name}");

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error en autenticación Google: ' . $e->getMessage()], 500);
        }
    }
}
