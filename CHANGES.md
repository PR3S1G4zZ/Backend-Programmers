# Registro de Cambios del Backend

## Fecha: 2026-02-20

Este documento describe los cambios y mejoras implementadas en el Backend durante las fases 1-3 del proyecto.

---

## 1. Fixes Realizados

### 1.1 Fix de Liberación de Pagos en PaymentService

**Problema:** El sistema de pagos no liberaba correctamente los fondos a los desarrolladores al aprobar hitos.

**Solución:** Se modificó el método `releaseMilestone()` en [`PaymentService.php`](Backend/app/Services/PaymentService.php) para garantizar:
- Deducción correcta del `held_balance` del proyecto
- Transferencia del monto neto (después de comisiones) al wallet del desarrollador
- Creación de registros de transacción precisos

### 1.2 Fix de held_balance

**Problema:** El campo `held_balance` no se actualizaba correctamente al financiar proyectos.

**Solución:** Se implementó el uso de métodos atómicos de Eloquent:

```php
// Antes (incorrecto)
$wallet->held_balance = $wallet->held_balance + $amount;

// Después (correcto)
$wallet->increment('held_balance', $amount);
```

Esto garantiza consistencia en entornos concurrentes.

### 1.3 Fix de Argumentos en PaymentService

**Problema:** Los métodos del servicio recibían argumentos incorrectos o insuficientes.

**Solución:** 
- Se pasaron los parámetros correctos a cada método
- Se asegura que el proyecto se pase correctamente a `createTransaction()`
- Se verificó la integridad de las transacciones creadas

### 1.4 Fix de Mass Assignment

**Problema:** Error de mass assignment al crear o actualizar modelos.

**Solución:** Se agregaron los campos necesarios al array `$fillable` en los modelos:

| Modelo | Campos agregados |
|--------|------------------|
| [`Wallet.php`](Backend/app/Models/Wallet.php) | `user_id`, `balance`, `held_balance` |
| [`Transaction.php`](Backend/app/Models/Transaction.php) | `wallet_id`, `amount`, `type`, `description`, `reference_type`, `reference_id` |
| [`Milestone.php`](Backend/app/Models/Milestone.php) | `project_id`, `title`, `description`, `amount`, `status`, `progress_status`, `order`, `due_date`, `deliverables` |
| [`Application.php`](Backend/app/Models/Application.php) | `project_id`, `developer_id`, `cover_letter`, `status` |

### 1.5 Transacción DB en ProfileController

**Problema:** Las operaciones de perfil no se ejecutaban de forma atómica.

**Solución:** Se implementó el uso de transacciones de base de datos en [`ProfileController.php`](Backend/app/Http/Controllers/ProfileController.php):

```php
DB::transaction(function () use ($user, $profileData) {
    $user->update($userData);
    $profile->update($profileData);
});
```

Esto garantiza que si falla alguna operación, se revierten todos los cambios.

### 1.6 Rate Limiting

**Implementación:** Se implementaron límites de tasa en las rutas API del backend en [`routes/api.php`](Backend/routes/api.php):

```php
Route::middleware('throttle:10,1')->group(function () {
    // Rutas protegidas
});
```

Esto previene ataques de fuerza bruta y abuso de la API.

---

## 2. Nuevas Funcionalidades

### 2.1 Sistema de Monedero (Wallet)

**Descripción:** Sistema completo de gestión de fondos para usuarios.

**Características:**
- Recarga de saldo mediante métodos de pago
- Retiros con verificación de fondos disponibles
- Balance disponible vs. balance en garantía (held_balance)
- Historial de transacciones detallado
- Auto-creación de wallet al primer acceso

**Archivos relacionados:**
- [`Wallet.php`](Backend/app/Models/Wallet.php) - Modelo
- [`WalletController.php`](Backend/app/Http/Controllers/WalletController.php) - Controlador
- [`Transaction.php`](Backend/app/Models/Transaction.php) - Modelo de transacciones
- Migraciones: [`2026_02_09_131507_create_wallets_table.php`](Backend/database/migrations/2026_02_09_131507_create_wallets_table.php), [`2026_02_09_131518_create_transactions_table.php`](Backend/database/migrations/2026_02_09_131518_create_transactions_table.php), [`2026_02_09_144539_add_held_balance_to_wallets_table.php`](Backend/database/migrations/2026_02_09_144539_add_held_balance_to_wallets_table.php)

### 2.2 Sistema de Hitos (Milestones)

**Descripción:** Sistema de seguimiento de progreso de proyectos por etapas.

**Características:**
- Crear, actualizar y eliminar hitos
- Estados: `todo`, `in_progress`, `review`, `completed`
- Envío de entregables por desarrolladores
- Aprobación/rechazo por empresas
- Liberación automática de pagos al aprobar
- Cálculo de progreso del proyecto

**Archivos relacionados:**
- [`Milestone.php`](Backend/app/Models/Milestone.php) - Modelo
- [`MilestoneController.php`](Backend/app/Http/Controllers/MilestoneController.php) - Controlador
- [`MilestonePolicy.php`](Backend/app/Policies/MilestonePolicy.php) - Política de acceso
- Migraciones: [`2026_02_09_150446_create_milestones_table.php`](Backend/database/migrations/2026_02_09_150446_create_milestones_table.php), [`2026_02_12_000000_add_fields_to_milestones_table.php`](Backend/database/migrations/2026_02_12_000000_add_fields_to_milestones_table.php), [`2026_02_12_143556_add_deliverables_to_milestones_table.php`](Backend/database/migrations/2026_02_12_143556_add_deliverables_to_milestones_table.php)

### 2.3 Sistema de Reseñas/Reviews

**Descripción:** Sistema de calificación entre empresas y desarrolladores.

**Características:**
- Empresas pueden reseñar desarrolladores
- Una sola review por proyecto/desarrollador (restricción unique)
- Validación de rating (1-5 estrellas)
- Cálculo de rating promedio del desarrollador

**Archivos relacionados:**
- [`Review.php`](Backend/app/Models/Review.php) - Modelo
- [`ReviewController.php`](Backend/app/Http/Controllers/ReviewController.php) - Controlador
- [`ReviewResource.php`](Backend/app/Http/Resources/ReviewResource.php) - Resource
- Migración: [`2026_02_15_000007_create_reviews_table.php`](Backend/database/migrations/2026_02_15_000007_create_reviews_table.php), [`2026_02_20_000001_add_unique_constraint_to_reviews_table.php`](Backend/database/migrations/2026_02_20_000001_add_unique_constraint_to_reviews_table.php)

### 2.4 Sistema de Favoritos

**Descripción:** Sistema para que empresas guarden desarrolladores favoritos.

**Características:**
- Marcar/desmarcar desarrolladores como favoritos
- Listar desarrolladores favoritos
- Toggle de estado favorito

**Archivos relacionados:**
- [`FavoriteController.php`](Backend/app/Http/Controllers/FavoriteController.php) - Controlador
- Migración: [`2026_02_17_130000_create_favorites_table.php`](Backend/database/migrations/2026_02_17_130000_create_favorites_table.php)

### 2.5 Sistema de Mensajería/Mensajes

**Descripción:** Sistema de comunicación entre empresas y desarrolladores.

**Características:**
- Conversaciones por proyecto
- Mensajes en tiempo real
- Historial de conversaciones

**Archivos relacionados:**
- [`Message.php`](Backend/app/Models/Message.php) - Modelo
- [`Conversation.php`](Backend/app/Models/Conversation.php) - Modelo
- [`ConversationController.php`](Backend/app/Http/Controllers/ConversationController.php) - Controlador
- Migraciones: [`2026_02_17_120059_create_messages_table.php`](Backend/database/migrations/2026_02_17_120059_create_messages_table.php), [`2026_02_17_130500_create_conversations_table.php`](Backend/database/migrations/2026_02_17_130500_create_conversations_table.php)

### 2.6 Perfiles de Desarrollador Mejorados

**Descripción:** Expansión del modelo de perfil de desarrollador.

**Campos agregados:**
- Headline y biografía
- Tarifa por hora
- Habilidades (JSON)
- Idiomas (JSON)
- Links profesionales (JSON)
- Años de experiencia
- Disponibilidad
- Foto de perfil

**Archivos relacionados:**
- [`DeveloperProfile.php`](Backend/app/Models/DeveloperProfile.php) - Modelo
- [`DeveloperController.php`](Backend/app/Http/Controllers/DeveloperController.php) - Controlador
- Migraciones: [`2026_02_15_000004_add_profile_fields.php`](Backend/database/migrations/2026_02_15_000004_add_profile_fields.php), [`2026_02_18_122135_add_profile_picture_to_users_table.php`](Backend/database/migrations/2026_02_18_122135_add_profile_picture_to_users_table.php)

### 2.7 Autenticación con Google

**Descripción:** Integración de login con Google OAuth.

**Características:**
- Registro/login con cuenta Google
- Asociación de cuenta Google a usuario existente
- Generación de tokens Sanctum

**Archivos relacionados:**
- [`AuthController.php`](Backend/app/Http/Controllers/AuthController.php) - Métodos de Google OAuth
- Migración: [`2026_02_17_153322_add_google_id_to_users_table.php`](Backend/database/migrations/2026_02_17_153322_add_google_id_to_users_table.php)
- Configuración: [`config/services.php`](Backend/config/services.php)

### 2.8 Sistema de Proyectos Mejorados

**Descripción:** Expansión del sistema de proyectos.

**Características:**
- Categorías de proyectos
- Habilidades requeridas
- Estado "pending_payment" para proyectos esperando financiamiento
- Detalles adicionales del proyecto

**Archivos relacionados:**
- [`Project.php`](Backend/app/Models/Project.php) - Modelo
- [`ProjectController.php`](Backend/app/Http/Controllers/ProjectController.php) - Controlador
- [`ProjectResource.php`](Backend/app/Http/Resources/ProjectResource.php) - Resource
- Migraciones: [`2026_02_15_000002_create_project_categories_tables.php`](Backend/database/migrations/2026_02_15_000002_create_project_categories_tables.php), [`2026_02_15_000005_add_project_fields.php`](Backend/database/migrations/2026_02_15_000005_add_project_fields.php), [`2026_02_15_000006_create_project_skill_table.php`](Backend/database/migrations/2026_02_15_000006_create_project_skill_table.php), [`2026_02_17_120000_add_pending_payment_to_projects_status.php`](Backend/database/migrations/2026_02_17_120000_add_pending_payment_to_projects_status.php)

---

## 3. Tests Creados

### 3.1 PaymentServiceTest (9 tests unitarios)

**Archivo:** [`Backend/tests/Unit/PaymentServiceTest.php`](Backend/tests/Unit/PaymentServiceTest.php)

| Test | Descripción |
|------|-------------|
| `testFundProject()` | Verifica que los fondos se muevan de balance a held_balance |
| `testFundProjectWithInsufficientFunds()` | Verifica excepción con saldo insuficiente |
| `testReleaseMilestone()` | Verifica liberación de fondos a desarrolladores |
| `testReleaseMilestoneWithMultipleDevelopers()` | Verifica distribución entre múltiples devs |
| `testReleaseMilestoneWithInsufficientHeldFunds()` | Verifica excepción con fondos en garantía insuficientes |
| `testReleaseMilestoneWithNoAcceptedDevelopers()` | Verifica excepción sin desarrolladores aceptados |
| `testProcessProjectPayment()` | Verifica el flujo completo de mantener y liberar fondos |
| `testHeldBalanceUpdatesCorrectly()` | Verifica actualización correcta de held_balance |
| `testGetCommissionRate()` | Verifica cálculo correcto de comisiones |

### 3.2 DeveloperControllerTest (tests de feature)

**Archivo:** [`Backend/tests/Feature/DeveloperControllerTest.php`](Backend/tests/Feature/DeveloperControllerTest.php)

| Test | Descripción |
|------|-------------|
| `testIndexReturnsPaginatedDevelopers()` | Verifica paginación |
| `testIndexFiltersBySearch()` | Verifica filtro por nombre/apellido |
| `testIndexExcludesNonDevelopers()` | Verifica que solo muestra programmers |
| `testShowReturnsDeveloperDetails()` | Verifica detalles del desarrollador |
| `testShowIncludesReviewsAndRating()` | Verifica inclusión de reviews |
| `testShowReturns404ForNonExistent()` | Verifica manejo de errores |
| `testIndexIncludesCompletedProjectsCount()` | Verifica proyectos completados |

### 3.3 MilestoneControllerTest (tests de feature)

**Archivo:** [`Backend/tests/Feature/MilestoneControllerTest.php`](Backend/tests/Feature/MilestoneControllerTest.php)

| Test | Descripción |
|------|-------------|
| `testDeveloperCanSubmitMilestone()` | Desarrollador puede enviar hito |
| `testCompanyCanApproveMilestone()` | Empresa puede aprobar hito |
| `testCompanyCanRejectMilestone()` | Empresa puede rechazar hito |
| `testMilestoneApprovalReleasesPayment()` | Verifica liberación de pago |
| `testCompanyCannotApproveNonReviewMilestone()` | Validación de estados |
| `testUnauthorizedUserCannotAccessMilestones()` | Verificación de acceso |
| `testDeveloperCannotApproveMilestone()` | Permisos correctos |
| `testCompanyCanCreateMilestone()` | Creación de hitos |
| `testCompanyCanUpdateMilestone()` | Actualización de hitos |
| `testCompanyCanDeleteMilestone()` | Eliminación de hitos |

### 3.4 WalletControllerTest (tests de feature)

**Archivo:** [`Backend/tests/Feature/WalletControllerTest.php`](Backend/tests/Feature/WalletControllerTest.php)

| Test | Descripción |
|------|-------------|
| `testShowWallet()` | Verifica obtener wallet |
| `testShowWalletCreatesWalletIfNotExists()` | Auto-creación de wallet |
| `testRechargeWallet()` | Recarga de saldo |
| `testRechargeWalletValidation()` | Validación de recarga |
| `testWithdrawFunds()` | Retiro de fondos |
| `testWithdrawWithInsufficientFunds()` | Verifica fondos insuficientes |
| `testWithdrawWithoutPaymentMethod()` | Verifica método de pago requerido |
| `testWithdrawWithHeldBalanceNotAvailable()` | Verifica balance disponible |
| `testWithdrawValidation()` | Validación de retiros |
| `testUnauthenticatedUserCannotAccessWallet()` | Acceso no autorizado |
| `testGetAvailableBalance()` | Cálculo de balance disponible |

### 3.5 ReviewControllerTest (tests de feature)

**Archivo:** [`Backend/tests/Feature/ReviewControllerTest.php`](Backend/tests/Feature/ReviewControllerTest.php)

| Test | Descripción |
|------|-------------|
| `testIndexReturnsDeveloperReviews()` | Verifica listado de reviews |
| `testStoreReviewValidation()` | Validación de creación |
| `testCompanyCanOnlyReviewOnce()` | Restricción única |
| `testCompanyCannotReviewNonOwnedProject()` | Permisos de proyecto |
| `testCompanyCannotReviewDeveloperNotInProject()` | Validación de developer |
| `testCompanyCanSuccessfullyCreateReview()` | Creación exitosa |
| `testShowReview()` | Ver detalles de review |
| `testUnauthenticatedUserCannotAccessReviews()` | Acceso no autorizado |
| `testDeveloperCanOnlySeeTheirReviews()` | Aislamiento de datos |

### 3.6 Factories Creadas

| Factory | Descripción |
|---------|-------------|
| [`UserFactory.php`](Backend/database/factories/UserFactory.php) | Usuarios (programmer, company, admin) |
| [`ProjectFactory.php`](Backend/database/factories/ProjectFactory.php) | Proyectos |
| [`ApplicationFactory.php`](Backend/database/factories/ApplicationFactory.php) | Postulaciones |
| [`MilestoneFactory.php`](Backend/database/factories/MilestoneFactory.php) | Hitos |
| [`WalletFactory.php`](Backend/database/factories/WalletFactory.php) | Billeteras |
| [`TransactionFactory.php`](Backend/database/factories/TransactionFactory.php) | Transacciones |
| [`ReviewFactory.php`](Backend/database/factories/ReviewFactory.php) | Reseñas |
| [`DeveloperProfileFactory.php`](Backend/database/factories/DeveloperProfileFactory.php) | Perfiles de desarrollador |
| [`PaymentMethodFactory.php`](Backend/database/factories/PaymentMethodFactory.php) | Métodos de pago |

---

## 4. Cómo Ejecutar los Tests

```bash
# En el directorio Backend
cd Backend

# Ejecutar todos los tests
php artisan test

# Ejecutar solo tests unitarios
php artisan test --testsuite=Unit

# Ejecutar solo tests de feature
php artisan test --testsuite=Feature

# Ejecutar un test específico
php artisan test --filter=PaymentServiceTest
```

---

## 5. Notas

- Los cambios son retrocompatibles con el código existente
- Los tests unitarios y de feature garantizan la estabilidad del sistema
- El sistema de comisiones calcula: monto < $500 = 20%, monto >= $500 = 15%
- La documentación se actualiza en cada fase del desarrollo
