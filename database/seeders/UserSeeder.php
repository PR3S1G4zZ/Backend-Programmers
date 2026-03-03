<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\CompanyProfile;
use App\Models\Conversation;
use App\Models\DeveloperProfile;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Review;
use App\Models\Skill;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    /**
     * Seed de usuarios, perfiles, proyectos y aplicaciones de demo.
     */
    public function run(): void
    {
        $this->command->info('🧹 Limpiando datos de demo...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Review::truncate();
        Message::truncate();
        Conversation::truncate();
        Application::truncate();
        \App\Models\Milestone::truncate();
        Project::truncate();
        DeveloperProfile::truncate();
        CompanyProfile::truncate();
        DB::table('project_category_project')->truncate();
        DB::table('project_skill')->truncate();
        DB::table('developer_skill')->truncate();
        ProjectCategory::truncate();
        Skill::truncate();
        User::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $faker = Faker::create('es_ES');
        $password = 'Demo1234!';

        $skillPool = [
            'Laravel', 'React', 'Vue', 'Node.js', 'Docker', 'PostgreSQL', 'MySQL', 'AWS', 'TypeScript',
            'Python', 'Figma', 'React Native', 'Kubernetes', 'Terraform', 'Django', 'Next.js',
        ];
        $skills = collect($skillPool)->map(fn ($name) => Skill::create(['name' => $name]));

        $categoryNames = [
            'Desarrollo Web',
            'Desarrollo Mobile',
            'UI/UX Design',
            'Backend/APIs',
            'DevOps',
            'Data Science',
            'AI/ML',
            'Blockchain',
        ];
        $categories = collect($categoryNames)->map(fn ($name) => ProjectCategory::create(['name' => $name]));

        $this->command->info('👥 Creando 40 usuarios variados...');

        $firstNames = [
            'Ana', 'Luis', 'Carlos', 'María', 'Jorge', 'Lucía', 'Sofía', 'Miguel', 'Camila', 'Andrés',
            'Valeria', 'Pablo', 'Diego', 'Laura', 'Daniel', 'Paula', 'Fernando', 'Elena', 'Ricardo', 'Natalia',
        ];
        $lastNames = [
            'García', 'López', 'Martínez', 'Rodríguez', 'González', 'Pérez', 'Sánchez', 'Ramírez', 'Torres', 'Flores',
            'Díaz', 'Vargas', 'Morales', 'Castro', 'Ortega', 'Rojas', 'Navarro', 'Cruz', 'Mendoza', 'Silva',
        ];
        $domains = ['example.com', 'devmail.com', 'empresa.co', 'talento.dev'];
        $usedEmails = [];
        $locations = [
            ['city' => 'Madrid', 'country' => 'España'],
            ['city' => 'Barcelona', 'country' => 'España'],
            ['city' => 'Ciudad de México', 'country' => 'México'],
            ['city' => 'Buenos Aires', 'country' => 'Argentina'],
            ['city' => 'Bogotá', 'country' => 'Colombia'],
            ['city' => 'Miami', 'country' => 'Estados Unidos'],
        ];
        $languagePool = ['Español', 'Inglés', 'Francés', 'Portugués'];

        $makeEmail = function (string $first, string $last) use (&$usedEmails, $domains): string {
            $base = Str::slug($first . '.' . $last, '.');
            $domain = $domains[array_rand($domains)];
            $email = $base . '@' . $domain;
            $suffix = 1;

            while (in_array($email, $usedEmails, true)) {
                $email = $base . $suffix . '@' . $domain;
                $suffix++;
            }

            $usedEmails[] = $email;

            return $email;
        };

        $admins = [
            [
                'name' => 'Admin',
                'lastname' => 'Principal',
                'email' => 'admin@admin.com',
            ],
            [
                'name' => 'Carla',
                'lastname' => 'Suárez',
                'email' => 'carla.admin@devmail.com',
            ],
        ];

        $companies = [
            [
                'name' => 'Demo',
                'lastname' => 'Company',
                'email' => 'demo@company.com',
                'company_name' => 'Demo Company',
            ],
        ];

        $developers = [
            [
                'name' => 'Demo',
                'lastname' => 'Programmer',
                'email' => 'demo@dev.com',
            ],
            [
                'name' => 'Luis',
                'lastname' => 'García',
                'email' => 'luis@gmail.co',
            ],
        ];

        $companyTarget = 10;
        while (count($companies) < $companyTarget) {
            $first = $firstNames[array_rand($firstNames)];
            $last = $lastNames[array_rand($lastNames)];
            $companies[] = [
                'name' => $first,
                'lastname' => $last,
                'email' => $makeEmail($first, $last),
                'company_name' => 'Tech ' . $last . ' ' . $faker->randomElement(['Labs', 'Solutions', 'Studio', 'Group']),
            ];
        }

        $developerTarget = 28;
        while (count($developers) < $developerTarget) {
            $first = $firstNames[array_rand($firstNames)];
            $last = $lastNames[array_rand($lastNames)];
            $developers[] = [
                'name' => $first,
                'lastname' => $last,
                'email' => $makeEmail($first, $last),
            ];
        }

        foreach ($admins as $admin) {
            User::create([
                'name' => $admin['name'],
                'lastname' => $admin['lastname'],
                'email' => $admin['email'],
                'password' => $password,
                'user_type' => 'admin',
                'role' => 'admin',
            ]);
        }

        $companyUsers = collect();
        foreach ($companies as $company) {
            $user = User::create([
                'name' => $company['name'],
                'lastname' => $company['lastname'],
                'email' => $company['email'],
                'password' => $password,
                'user_type' => 'company',
                'role' => 'company',
            ]);

            $companyLocation = $faker->randomElement($locations);
            CompanyProfile::create([
                'user_id' => $user->id,
                'company_name' => $company['company_name'],
                'website' => 'https://' . Str::slug($company['company_name']) . '.com',
                'about' => $faker->sentence(18),
                'location' => $companyLocation['city'] . ', ' . $companyLocation['country'],
                'country' => $companyLocation['country'],
            ]);

            $companyUsers->push($user);
        }

        $developerUsers = collect();
        foreach ($developers as $developer) {
            $user = User::create([
                'name' => $developer['name'],
                'lastname' => $developer['lastname'],
                'email' => $developer['email'],
                'password' => $password,
                'user_type' => 'programmer',
                'role' => 'programmer',
            ]);

            $developerLocation = $faker->randomElement($locations);
            $pickedSkills = $faker->randomElements($skillPool, rand(3, 5));
            $languages = $faker->randomElements($languagePool, rand(1, 3));

            $profile = DeveloperProfile::create([
                'user_id' => $user->id,
                'headline' => $faker->randomElement(['Full Stack Developer', 'Backend Developer', 'Frontend Specialist', 'DevOps Engineer']),
                'skills' => $pickedSkills,
                'bio' => $faker->paragraph(2),
                'links' => [
                    'github' => 'https://github.com/' . Str::slug($user->name . $user->lastname),
                    'linkedin' => 'https://linkedin.com/in/' . Str::slug($user->name . '-' . $user->lastname),
                ],
                'location' => $developerLocation['city'] . ', ' . $developerLocation['country'],
                'country' => $developerLocation['country'],
                'hourly_rate' => $faker->numberBetween(25, 90),
                'availability' => $faker->randomElement(['available', 'busy', 'unavailable']),
                'experience_years' => $faker->numberBetween(1, 10),
                'languages' => $languages,
            ]);

            $developerUsers->push($user);

            $skillIds = $skills->whereIn('name', $pickedSkills)->pluck('id');
            $profile->user->skills()->sync($skillIds);
        }

        // Arrays de datos en español para variedad
        $projectDescriptions = [
            'Buscamos un desarrollador para crear una plataforma integral que gestione el ciclo de vida del talento humano. Debe incluir módulos de reclutamiento, onboarding y evaluación de desempeño.',
            'Necesitamos un experto en backend para optimizar nuestra base de datos y reducir los tiempos de respuesta de la API. El sistema actual está hecho en Laravel y MySQL.',
            'Proyecto para diseñar y desarrollar una aplicación móvil híbrida para el seguimiento de rutas de logística en tiempo real. Integración con Google Maps requerida.',
            'Estamos migrando nuestro ecommerce a una arquitectura de microservicios. Buscamos arquitectos de software con experiencia en AWS y Docker.',
            'Desarrollo de un dashboard interactivo para visualizar KPIs financieros. Debe ser responsivo y permitir exportar reportes en PDF y Excel.',
            'Creación de una landing page de alto impacto para el lanzamiento de un nuevo producto SaaS. Animaciones fluidas y optimización SEO son prioridad.',
            'Sistema de gestión de inventario para una cadena de retail. Debe sincronizarse con el punto de venta y la tienda online.',
            'Implementación de pasarela de pagos y sistema de facturación electrónica para una startup fintech.',
            'Auditoría de seguridad y pentesting para nuestra plataforma web. Se requiere informe detallado de vulnerabilidades y plan de mitigación.',
            'Desarrollo de un chatbot con IA para atención al cliente, integrado con WhatsApp Business API.',
        ];

        $milestoneTitles = [
            'Investigación y Análisis', 'Diseño de Base de Datos', 'Prototipado UI/UX', 'Desarrollo del Backend', 
            'Integración de API', 'Desarrollo del Frontend', 'Pruebas Unitarias', 'Pruebas de Integración', 
            'Despliegue a Staging', 'Corrección de Bugs', 'Optimización de Rendimiento', 'Entrega Final'
        ];

        $milestoneDescriptions = [
            'Análisis detallado de los requerimientos y elaboración del documento de especificaciones técnicas.',
            'Diseño del esquema de base de datos relacional y scripts de migración.',
            'Creación de wireframes y prototipos de alta fidelidad en Figma.',
            'Implementación de la lógica de negocio y endpoints de la API RESTful.',
            'Conexión de los servicios externos y configuración de webhooks.',
            'Maquetación de las vistas y componentes visuales usando React.',
            'Escritura y ejecución de tests para asegurar la calidad del código.',
            'Verificación del flujo completo de la aplicación en un entorno controlado.',
            'Configuración del servidor y despliegue de la versión de prueba.',
            'Ajustes basados en el feedback de la revisión y solución de incidencias.',
            'Mejoras en la velocidad de carga y consumo de recursos.',
            'Puesta en producción y entrega de la documentación técnica y de usuario.'
        ];

        $this->command->info('🧩 Creando proyectos para empresas...');

        $projectTitles = [
            'Plataforma de gestión de talento',
            'Dashboard financiero en tiempo real',
            'Marketplace de servicios digitales',
            'Sistema de reservas multiciudad',
            'App de seguimiento de entregas',
            'Portal de onboarding corporativo',
            'Rediseño UX para plataforma SaaS',
            'Infraestructura DevOps multicloud',
        ];

        $projects = collect();
        foreach ($companyUsers as $companyUser) {
            $projectCount = rand(1, 3);
            for ($i = 0; $i < $projectCount; $i++) {
                $budgetMin = rand(800, 2500);
                $budgetMax = $budgetMin + rand(500, 3000);
                $projectLocation = $faker->randomElement($locations);
                $status = $faker->randomElement(['open', 'in_progress', 'completed']);
                $project = Project::create([
                    'company_id' => $companyUser->id,
                    'title' => $faker->randomElement($projectTitles),
                    'description' => $faker->randomElement($projectDescriptions) . ' ' . $faker->text(200),
                    'budget_min' => $budgetMin,
                    'budget_max' => $budgetMax,
                    'budget_type' => $faker->randomElement(['fixed', 'hourly']),
                    'duration_value' => $faker->numberBetween(2, 12),
                    'duration_unit' => $faker->randomElement(['weeks', 'months']),
                    'location' => $projectLocation['city'] . ', ' . $projectLocation['country'],
                    'remote' => $faker->boolean(65),
                    'level' => $faker->randomElement(['junior', 'mid', 'senior', 'lead']),
                    'priority' => $faker->randomElement(['low', 'medium', 'high', 'urgent']),
                    'featured' => $faker->boolean(30),
                    'deadline' => $faker->dateTimeBetween('now', '+2 months'),
                    'max_applicants' => $faker->numberBetween(8, 30),
                    'tags' => $faker->randomElements(['Remoto', 'Urgente', 'Fintech', 'SaaS', 'Marketplace', 'B2B', 'React', 'Laravel', 'API'], rand(2, 4)),
                    'status' => $status,
                ]);

                $projects->push($project);

                $category = $categories->random();
                $project->categories()->sync([$category->id]);
                $projectSkills = $skills->random(rand(3, 5))->pluck('id');
                $project->skills()->sync($projectSkills);
            }
        }

        $this->command->info('📨 Creando aplicaciones de desarrolladores...');

        foreach ($projects as $project) {
            $applicants = $developerUsers->random(rand(1, min(5, $developerUsers->count())));
            foreach ($applicants as $developer) {
                $createdAt = $faker->dateTimeBetween('-30 days', 'now');
                Application::create([
                    'project_id' => $project->id,
                    'developer_id' => $developer->id,
                    'cover_letter' => $faker->sentence(20),
                    'status' => $faker->randomElement(['sent', 'reviewed', 'accepted', 'rejected']),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }

        $this->command->info('📍 Creando hitos para los proyectos...');

        foreach ($projects as $project) {
            // Only create milestones for projects that are not drafts
            if ($project->status === 'draft') continue;

            $milestoneCount = rand(3, 6);
            $completedMilestones = 0;

            // Determine how many milestones should be completed based on project status
            if ($project->status === 'completed') {
                $completedCount = $milestoneCount;
            } elseif ($project->status === 'in_progress') {
                $completedCount = rand(1, $milestoneCount - 1);
            } else {
                $completedCount = 0;
            }

            for ($i = 1; $i <= $milestoneCount; $i++) {
                $milestoneStatus = 'pending';
                $progressStatus = 'todo';
                
                if ($i <= $completedCount) {
                    $milestoneStatus = 'released'; 
                    $progressStatus = 'completed';
                } elseif ($i === $completedCount + 1 && $project->status === 'in_progress') {
                    $milestoneStatus = 'funded';
                    $progressStatus = 'in_progress';
                }

                \App\Models\Milestone::create([
                    'project_id' => $project->id,
                    'title' => "Hito $i: " . $faker->randomElement($milestoneTitles),
                    'description' => $faker->randomElement($milestoneDescriptions),
                    'amount' => $project->budget_max / $milestoneCount,
                    'status' => $milestoneStatus,
                    'progress_status' => $progressStatus,
                    'order' => $i,
                    'due_date' => $faker->dateTimeBetween($project->created_at, $project->deadline ?? '+2 months'),
                    'deliverables' => $progressStatus === 'completed' ? [$faker->url] : null,
                ]);
            }
        }

        $this->command->info('💬 Creando conversaciones y mensajes...');

        $acceptedApplications = Application::with('project')
            ->where('status', 'accepted')
            ->get();

        foreach ($acceptedApplications as $application) {
            $project = $application->project;
            if (!$project) {
                continue;
            }

            // Conversation created around the time of application acceptance (using app created_at as base)
            $baseTime = Carbon::parse($application->created_at);
            $conversationCreatedAt = $faker->dateTimeBetween($baseTime, 'now');

            $conversation = Conversation::create([
                'project_id' => $project->id,
                'type' => 'project',
                'initiator_id' => $project->company_id,
                'participant_id' => $application->developer_id,
                'created_at' => $conversationCreatedAt,
                'updated_at' => $conversationCreatedAt,
            ]);

            // $conversation->participants()->sync([$project->company_id, $application->developer_id]);

            $messages = [
                [
                    'sender_id' => $project->company_id,
                    'body' => 'Hola, hemos revisado tu perfil y nos gustaría avanzar contigo.',
                ],
                [
                    'sender_id' => $application->developer_id,
                    'body' => '¡Genial! Estoy muy entusiasmado por colaborar.',
                ],
                [
                    'sender_id' => $project->company_id,
                    'body' => 'Te envío los detalles del primer hito.',
                ],
            ];
            
            // ... message creation loop ...
             $msgTime = Carbon::parse($conversationCreatedAt);
            foreach ($messages as $messageData) {
                // Determine random delay for next message (e.g., 2 minutes to 2 hours)
                $msgTime = $msgTime->copy()->addMinutes(rand(2, 120));
                
                // Keep it before "now"
                if ($msgTime->isFuture()) {
                    $msgTime = Carbon::now()->subMinutes(rand(1, 60));
                }

                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $messageData['sender_id'],
                    'content' => $messageData['body'],
                    'type' => 'text',
                    'is_read' => true,
                    'created_at' => $msgTime,
                    'updated_at' => $msgTime,
                ]);
            }
        }

        $this->command->info('⭐ Creando reviews de proyectos completados...');

        $reviewComments = [
            'Excelente profesional, entregó todo a tiempo y con gran calidad.',
            'Muy buena comunicación y disposición para resolver problemas.',
            'El código es limpio y bien estructurado. Recomendado 100%.',
            'Hubo algunos retrasos pero el resultado final fue satisfactorio.',
            'Gran experiencia trabajando juntos, esperamos colaborar nuevamente.',
            'Superó nuestras expectativas en cuanto a funcionalidad y diseño.'
        ];

        $completedProjects = Project::where('status', 'completed')->get();
        foreach ($completedProjects as $project) {
            $application = Application::where('project_id', $project->id)
                ->where('status', 'accepted')
                ->inRandomOrder()
                ->first();

            if (!$application) {
                continue;
            }

            Review::create([
                'project_id' => $project->id,
                'company_id' => $project->company_id,
                'developer_id' => $application->developer_id,
                'rating' => $faker->numberBetween(3, 5),
                'comment' => $faker->randomElement($reviewComments),
            ]);
        }

        $totalUsers = User::count();
        $this->command->info("✅ Seeder completado. Total usuarios creados: {$totalUsers}.");
    }
}
