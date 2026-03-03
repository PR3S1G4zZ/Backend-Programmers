# Guía de Pruebas de Validaciones

Este documento contiene ejemplos de cómo probar las validaciones implementadas en el sistema de autenticación.

## Validaciones Implementadas

### 1. Validación de Email
- Debe contener "@"
- Debe terminar en ".com"

### 2. Validación de Contraseña
- Mínimo 8 caracteres
- Máximo 15 caracteres
- Al menos 1 mayúscula
- Al menos 1 minúscula
- Al menos 1 número
- Al menos 1 carácter especial (@$!%*?&#)

### 3. Protección contra SQL Injection
- Sanitización de datos
- Consultas preparadas (automático en Laravel)
- Validación estricta de tipos

## Cómo Probar las Validaciones

### Usando cURL (Terminal)

#### 1. Usuario CORRECTO - Debe funcionar ✓

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Juan",
    "lastname": "Pérez",
    "email": "juan.perez@example.com",
    "password": "MiPass123!@#",
    "password_confirmation": "MiPass123!@#",
    "user_type": "programmer"
  }'
```

**Resultado esperado:** Usuario creado exitosamente (201)

---

#### 2. Usuario INCORRECTO - Email sin .com ✗

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "María",
    "lastname": "González",
    "email": "maria.gonzalez@example.org",
    "password": "MiPass123!@#",
    "password_confirmation": "MiPass123!@#",
    "user_type": "company"
  }'
```

**Resultado esperado:** Error de validación (422) - "El correo electrónico debe contener "@" y terminar en ".com"."

---

#### 3. Usuario INCORRECTO - Email sin @ ✗

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Luis",
    "lastname": "Sánchez",
    "email": "luissanchez.test.com",
    "password": "MiPassword123!@#",
    "password_confirmation": "MiPassword123!@#",
    "user_type": "programmer"
  }'
```

**Resultado esperado:** Error de validación (422) - "El correo electrónico debe contener "@" y terminar en ".com"."

---

#### 4. Usuario INCORRECTO - Contraseña muy corta (menos de 8 caracteres) ✗

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Ana",
    "lastname": "Martínez",
    "email": "ana.martinez@test.com",
    "password": "Pass1!",
    "password_confirmation": "Pass1!",
    "user_type": "programmer"
  }'
```

**Resultado esperado:** Error de validación (422) - "La contraseña debe tener al menos 8 caracteres."

---

#### 5. Usuario INCORRECTO - Contraseña muy larga (más de 15 caracteres) ✗

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Pedro",
    "lastname": "García",
    "email": "pedro.garcia@test.com",
    "password": "MiPassword123!@#Extra",
    "password_confirmation": "MiPassword123!@#Extra",
    "user_type": "programmer"
  }'
```

**Resultado esperado:** Error de validación (422) - "La contraseña no puede tener más de 15 caracteres."

---

#### 6. Usuario INCORRECTO - Contraseña sin mayúscula ✗

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Laura",
    "lastname": "López",
    "email": "laura.lopez@test.com",
    "password": "password123!@#",
    "password_confirmation": "password123!@#",
    "user_type": "company"
  }'
```

**Resultado esperado:** Error de validación (422) - "La contraseña debe tener entre 8 y 15 caracteres, incluyendo al menos una mayúscula..."

---

#### 7. Usuario INCORRECTO - Contraseña sin minúscula ✗

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Carlos",
    "lastname": "Rodríguez",
    "email": "carlos.rodriguez@test.com",
    "password": "PASSWORD123!@#",
    "password_confirmation": "PASSWORD123!@#",
    "user_type": "company"
  }'
```

**Resultado esperado:** Error de validación (422) - "La contraseña debe tener entre 8 y 15 caracteres, incluyendo al menos una minúscula..."

---

#### 8. Usuario INCORRECTO - Contraseña sin número ✗

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Sofía",
    "lastname": "Martínez",
    "email": "sofia.martinez@test.com",
    "password": "MiPassword!@#",
    "password_confirmation": "MiPassword!@#",
    "user_type": "programmer"
  }'
```

**Resultado esperado:** Error de validación (422) - "La contraseña debe tener entre 8 y 15 caracteres, incluyendo al menos un número..."

---

#### 9. Usuario INCORRECTO - Contraseña sin carácter especial ✗

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Diego",
    "lastname": "Fernández",
    "email": "diego.fernandez@test.com",
    "password": "MiPassword123",
    "password_confirmation": "MiPassword123",
    "user_type": "programmer"
  }'
```

**Resultado esperado:** Error de validación (422) - "La contraseña debe tener entre 8 y 15 caracteres, incluyendo al menos un carácter especial..."

---

#### 10. Usuario CORRECTO - Segundo ejemplo ✓

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Carlos",
    "lastname": "Rodríguez",
    "email": "carlos.rodriguez@empresa.com",
    "password": "SecureP@ss9",
    "password_confirmation": "SecureP@ss9",
    "user_type": "company"
  }'
```

**Resultado esperado:** Usuario creado exitosamente (201)

---

### Prueba de Login

#### Login CORRECTO ✓

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "juan.perez@example.com",
    "password": "MiPass123!@#"
  }'
```

**Resultado esperado:** Login exitoso (200) con token

---

#### Login INCORRECTO - Email sin .com ✗

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "usuario@example.org",
    "password": "MiPass123!@#"
  }'
```

**Resultado esperado:** Error de validación (422) - "El correo electrónico debe contener "@" y terminar en ".com"."

---

## Usando Postman o Insomnia

Importa esta colección de ejemplo:

### Endpoint: POST /api/auth/register

**Headers:**
- Content-Type: application/json
- Accept: application/json

**Body (JSON) - Usuario Correcto:**
```json
{
  "name": "Juan",
  "lastname": "Pérez",
  "email": "juan.perez@example.com",
  "password": "MiPass123!@#",
  "password_confirmation": "MiPass123!@#",
  "user_type": "programmer"
}
```

**Body (JSON) - Usuario Incorrecto:**
```json
{
  "name": "María",
  "lastname": "González",
  "email": "maria.gonzalez@example.org",
  "password": "password",
  "password_confirmation": "password",
  "user_type": "company"
}
```

---

## Ejecutar el Seeder

Para ejecutar el seeder que crea usuarios de prueba directamente en la base de datos:

```bash
php artisan db:seed --class=UserSeeder
```

**Nota:** El seeder crea usuarios directamente en la base de datos, por lo que NO pasa por las validaciones del controlador. Las validaciones solo se aplican cuando se usa el endpoint `/api/auth/register`.

---

## Ejecutar Todos los Seeders

```bash
php artisan db:seed
```

---

## Ejemplos de Contraseñas Válidas

- `MiPass123!@#` - 12 caracteres ✓
- `SecureP@ss9` - 10 caracteres ✓
- `Abc123!@#` - 8 caracteres ✓
- `MyP@ssw0rd123` - 13 caracteres ✓

## Ejemplos de Contraseñas Inválidas

- `password` - Sin mayúscula, sin número, sin carácter especial ✗
- `PASSWORD123` - Sin minúscula, sin carácter especial ✗
- `MiPassword` - Sin número, sin carácter especial ✗
- `MiPass123` - Sin carácter especial ✗
- `Pass1!` - Muy corta (solo 6 caracteres) ✗
- `MiPassword123!@#Extra` - Muy larga (20 caracteres) ✗

