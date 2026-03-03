<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;

use App\Notifications\ResetPasswordNotification;

/**
 * @property int $id
 * @property string $name
 * @property string $lastname
 * @property string $email
 * @property string $password
 * @property string $user_type
 * @property string $role
 * @method \Laravel\Sanctum\NewAccessToken createToken(string $name, array $abilities = [])
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    protected $dates = [
        'deleted_at',
    ];

    protected $fillable = [
        'name',
        'lastname',
        'email',
        'password',
        'user_type',
        'role',
        'profile_picture',
        'banned_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'banned_at'         => 'datetime',
        ];
    }

    /**
     * Guarda temporalmente el password original para validarlo
     */
    protected $originalPassword = null;

    /**
     * Mutator que detecta si la contraseña ya está hasheada.
     */
    public function setPasswordAttribute($value)
    {
        $isHashed = is_string($value) &&
            (str_starts_with($value, '$2y$') || str_starts_with($value, '$2a$') || str_starts_with($value, '$2b$'));

        // Guardar contraseña original (solo si no está hasheada)
        $this->originalPassword = $isHashed ? null : $value;

        $this->attributes['password'] =
            !$isHashed && !empty($value)
                ? Hash::make($value)
                : $value;
    }

    /**
     * Boot: Validar campos y sanitizar datos
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            static::validateUserData($user, false);

            $user->name     = strip_tags(trim($user->name));
            $user->lastname = strip_tags(trim($user->lastname));
            $user->email    = strtolower(trim($user->email));
        });

        static::updating(function ($user) {

            static::validateUserData($user, true);

            if ($user->isDirty('name'))     $user->name = strip_tags(trim($user->name));
            if ($user->isDirty('lastname')) $user->lastname = strip_tags(trim($user->lastname));
            if ($user->isDirty('email'))    $user->email = strtolower(trim($user->email));
        });
    }

    /**
     * Validación central de campos
     */
    protected static function validateUserData($user, $isUpdate = false)
    {
        $rules = [
            'name'      => 'required|string|max:255|regex:/^(?!\s)[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+(?<!\s)$/',
            // lastname es opcional si es empresa
            'lastname'  => [
                $user->user_type === 'company' ? 'nullable' : 'required', 
                'string', 
                'max:255', 
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s\-]+$/'
            ],
            'email'     => [
                'required', 'string', 'email', 'max:255',
                'regex:/^[^@\s]+@[^@\.\s]+\.[^@\.\s]+$/i',
                'regex:/^\S+$/',
                $isUpdate ? 'unique:users,email,' . $user->id : 'unique:users',
            ],
            'user_type' => 'required|in:programmer,company,admin',
        ];

        // Validar contraseña SOLO si es creación o si está siendo cambiada
        if (!$isUpdate || $user->originalPassword !== null) {

            $rules['password'] = [
                'required', 'string', 'regex:/^\S+$/',
                function ($attribute, $value, $fail) {

                    if (preg_match('/\s/', $value)) {
                        return $fail('La contraseña no debe contener espacios.');
                    }

                    $len = mb_strlen($value);
                    if ($len < 8)  return $fail('La contraseña debe tener al menos 8 caracteres.');
                    if ($len > 64) return $fail('La contraseña no puede tener más de 64 caracteres.');

                    if (!preg_match('/[A-Z]/', $value)) return $fail('Debe contener al menos una mayúscula.');
                    if (!preg_match('/[a-z]/', $value)) return $fail('Debe contener al menos una minúscula.');
                    if (!preg_match('/[0-9]/', $value)) return $fail('Debe contener al menos un número.');
                    if (!preg_match('/[@$!%*?&#]/', $value)) return $fail('Debe contener un carácter especial.');
                }
            ];
        }

        $attributes = $user->getAttributes();

        if ($user->originalPassword !== null) {
            $attributes['password'] = $user->originalPassword;
        }

        $validator = Validator::make($attributes, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    /**
     * Override: Enviar notificación de reset personalizada
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function developerProfile()
    {
        return $this->hasOne(DeveloperProfile::class);
    }

    public function companyProfile()
    {
        return $this->hasOne(CompanyProfile::class);
    }

    public function reviewsReceived()
    {
        return $this->hasMany(Review::class, 'developer_id');
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'developer_skill', 'developer_id', 'skill_id');
    }
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function preferences()
    {
        return $this->hasOne(UserPreference::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class, 'developer_id');
    }
}
