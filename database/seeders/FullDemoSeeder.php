<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Skill;
use App\Models\ProjectCategory;
use App\Models\DeveloperProfile;
use App\Models\CompanyProfile;
use App\Models\Wallet;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\Application;
use App\Models\Milestone;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Review;
use App\Models\Transaction;
use App\Models\PortfolioProject;
use App\Models\UserPreference;
use App\Models\ActivityLog;
use App\Models\SystemSetting;
use App\Models\Favorite;

class FullDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('╔════════════════════════════════════════════════════════════╗');
        $this->command->info('║     SEEDER DE DEMOSTRACIÓN COMPLETA - MODO EXTENDIDO      ║');
        $this->command->info('╚════════════════════════════════════════════════════════════╝');
        
        // 1. Truncar todas las tablas
        $this->truncateTables();
        
        // 2. Crear Skills (27 habilidades principales)
        $skills = $this->createSkills();
        
        // 3. Crear ProjectCategories (12 categorías)
        $categories = $this->createCategories();
        
        // 4. Crear Admins (10 admins del sistema)
        $admins = $this->createAdmins();
        
        // 5. Crear Companies + CompanyProfiles (50 empresas)
        $companies = $this->createCompanies();
        
        // 6. Crear Developers + DeveloperProfiles (200 desarrolladores)
        $developers = $this->createDevelopers($skills);
        
        // 7. Crear Wallets para todos
        $this->createWallets($admins, $companies, $developers);
        
        // 8. Crear PaymentMethods
        $this->createPaymentMethods($companies, $developers);
        
        // 9. Crear Projects (150+ proyectos)
        $projects = $this->createProjects($companies, $categories, $skills, $developers);
        
        // 10. Crear Applications (múltiples por proyecto)
        $this->createApplications($projects, $developers);
        
        // 11. Crear Milestones
        $this->createMilestones($projects);
        
        // 12. Crear Conversations + Messages
        $this->createConversationsAndMessages($projects, $developers);
        
        // 13. Crear Reviews
        $this->createReviews($projects);
        
        // 14. Crear Transactions (muchas más para el dashboard financiero)
        $this->createTransactions($projects, $admins);
        
        // 15. Crear PortfolioProjects (para developers destacados)
        $this->createPortfolios($developers);
        
        // 16. Crear Favorites (empresas favs de developers)
        $this->createFavorites($companies, $developers);
        
        // 17. Crear UserPreferences
        $this->createUserPreferences(array_merge($admins, $companies, $developers));
        
        // 18. Crear ActivityLogs (muchos más para el mapa de calor)
        $this->createActivityLogs($admins, $companies, $developers, $projects);
        
        // 19. Crear SystemSettings
        $this->createSystemSettings();
        
        // 20. Distribuir timestamps para métricas realistas (mapa de calor)
        $this->spreadTimestamps();
        
        // 21. Crear PlatformCommissions de prueba (proyectos completados)
        $this->createPlatformCommissions($projects, $companies, $developers);
        
        $this->command->info('');
        $this->command->info('╔════════════════════════════════════════════════════════════╗');
        $this->command->info('║           SEEDER COMPLETADO EXITOSAMENTE                 ║');
        $this->command->info('╚════════════════════════════════════════════════════════════╝');
        
        // Resumen final
        $this->command->info('📊 RESUMEN DE DATOS GENERADOS:');
        $this->command->info('   - Administradores: ' . count($admins));
        $this->command->info('   - Empresas: ' . count($companies));
        $this->command->info('   - Desarrolladores: ' . count($developers));
        $this->command->info('   - Proyectos: ' . count($projects));
        $this->command->info('   - Habilidades: ' . count($skills));
        $this->command->info('   - Categorías: ' . count($categories));
    }


    private function truncateTables(): void
    {
        $this->command->info('🧹 Limpiando base de datos...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        DB::table('favorites')->truncate();
        DB::table('activity_logs')->truncate();
        DB::table('user_preferences')->truncate();
        DB::table('system_settings')->truncate();
        DB::table('portfolio_projects')->truncate();
        DB::table('transactions')->truncate();
        DB::table('payment_methods')->truncate();
        DB::table('messages')->truncate();
        DB::table('conversations')->truncate();
        DB::table('reviews')->truncate();
        DB::table('milestones')->truncate();
        DB::table('project_skill')->truncate();
        DB::table('project_category_project')->truncate();
        DB::table('applications')->truncate();
        DB::table('projects')->truncate();
        DB::table('developer_skill')->truncate();
        DB::table('developer_profiles')->truncate();
        DB::table('company_profiles')->truncate();
        DB::table('wallets')->truncate();
        DB::table('personal_access_tokens')->truncate();
        DB::table('users')->truncate();
        DB::table('skills')->truncate();
        DB::table('project_categories')->truncate();
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function createSkills(): array
    {
        $this->command->info('🎯 Creando 27 habilidades...');
        
        $skillsData = [
            'Laravel', 'React', 'Vue.js', 'Node.js', 'Docker', 'PostgreSQL', 
            'MySQL', 'AWS', 'TypeScript', 'Python', 'Figma', 'React Native', 
            'Kubernetes', 'Terraform', 'Django', 'Next.js', 'Angular', 'Flutter', 
            'Swift', 'Kotlin', 'Go', 'Rust', 'MongoDB', 'Redis', 'GraphQL', 
            'TailwindCSS', 'PHP'
        ];
        
        $skills = [];
        foreach ($skillsData as $skillName) {
            $skill = Skill::create(['name' => $skillName]);
            $skills[$skillName] = $skill->id;
        }
        
        return $skills;
    }

    private function createCategories(): array
    {
        $this->command->info('📂 Creando 12 categorías de proyectos...');
        
        $categoriesData = [
            ['name' => 'Desarrollo Web', 'description' => 'Proyectos de desarrollo web completo', 'color' => '#3B82F6', 'icon' => 'globe'],
            ['name' => 'Desarrollo Mobile', 'description' => 'Aplicaciones móviles para iOS y Android', 'color' => '#10B981', 'icon' => 'device-mobile'],
            ['name' => 'UI/UX Design', 'description' => 'Diseño de interfaces y experiencia de usuario', 'color' => '#8B5CF6', 'icon' => 'palette'],
            ['name' => 'Backend/APIs', 'description' => 'Desarrollo de APIs y servicios backend', 'color' => '#F59E0B', 'icon' => 'server'],
            ['name' => 'DevOps', 'description' => 'Automatización, CI/CD e infraestructura', 'color' => '#EF4444', 'icon' => 'cloud'],
            ['name' => 'Data Science', 'description' => 'Análisis de datos y business intelligence', 'color' => '#06B6D4', 'icon' => 'chart-bar'],
            ['name' => 'AI/ML', 'description' => 'Inteligencia artificial y machine learning', 'color' => '#EC4899', 'icon' => 'cpu-chip'],
            ['name' => 'Blockchain', 'description' => 'Desarrollo blockchain y Web3', 'color' => '#F97316', 'icon' => 'cube-transparent'],
            ['name' => 'E-commerce', 'description' => 'Tiendas online y plataformas de venta', 'color' => '#84CC16', 'icon' => 'shopping-cart'],
            ['name' => 'Ciberseguridad', 'description' => 'Seguridad informática y auditoría', 'color' => '#6366F1', 'icon' => 'shield-check'],
            ['name' => 'Cloud Computing', 'description' => 'Servicios en la nube y arquitectura cloud', 'color' => '#14B8A6', 'icon' => 'cloud-arrow-up'],
            ['name' => 'QA/Testing', 'description' => 'Pruebas de software y control de calidad', 'color' => '#A855F7', 'icon' => 'beaker'],
        ];
        
        $categories = [];
        foreach ($categoriesData as $cat) {
            $category = ProjectCategory::create($cat);
            $categories[$category->name] = $category->id;
        }
        
        return $categories;
    }

    private function createAdmins(): array
    {
        $this->command->info('👑 Creando 10 administradores...');
        
        $admins = [];
        $adminsData = [
            ['name' => 'Admin', 'lastname' => 'Principal', 'email' => 'admin@admin.com'],
            ['name' => 'Carlos', 'lastname' => 'Supervisor', 'email' => 'carlos.supervisor@programmers.com'],
            ['name' => 'María', 'lastname' => 'Support', 'email' => 'maria.soporte@programmers.com'],
            ['name' => 'Luis', 'lastname' => 'Finance', 'email' => 'luis.finance@programmers.com'],
            ['name' => 'Ana', 'lastname' => 'Moderator', 'email' => 'ana.moderator@programmers.com'],
            ['name' => 'Pedro', 'lastname' => 'Security', 'email' => 'pedro.security@programmers.com'],
            ['name' => 'Laura', 'lastname' => 'Quality', 'email' => 'laura.quality@programmers.com'],
            ['name' => 'Miguel', 'lastname' => 'TechLead', 'email' => 'miguel.techlead@programmers.com'],
            ['name' => 'Sofía', 'lastname' => 'Operations', 'email' => 'sofia.operations@programmers.com'],
            ['name' => 'Roberto', 'lastname' => 'Compliance', 'email' => 'roberto.compliance@programmers.com'],
        ];
        
        foreach ($adminsData as $adminData) {
            $user = User::create([
                'name' => $adminData['name'],
                'lastname' => $adminData['lastname'],
                'email' => $adminData['email'],
                'password' => 'Demo1234!',
                'user_type' => 'admin',
                'role' => 'admin',
            ]);
            
            DB::table('users')->where('id', $user->id)->update(['email_verified_at' => Carbon::now()]);
            $admins[] = $user->id;
        }
        
        return $admins;
    }

    private function createCompanies(): array
    {
        $this->command->info('🏢 Creando 50 empresas y perfiles...');
        
        $companies = [];
        
        // 20 empresas con datos detallados
        $companiesData = [
            ['name' => 'Carlos', 'lastname' => 'Ramírez', 'email' => 'carlos@technova.com', 'company' => 'TechNova Solutions', 'website' => 'https://technova.com', 'about' => 'Empresa líder en desarrollo de soluciones tecnológicas innovadoras para empresas Fortune 500.', 'location' => 'Madrid', 'country' => 'España'],
            ['name' => 'Ana', 'lastname' => 'Reyes', 'email' => 'ana@byteforge.mx', 'company' => 'ByteForge Labs', 'website' => 'https://byteforge.mx', 'about' => 'Laboratorio de innovación digital enfocado en crear productos tecnológicos disruptivos.', 'location' => 'Ciudad de México', 'country' => 'México'],
            ['name' => 'Pablo', 'lastname' => 'Torres', 'email' => 'pablo@cloudpeak.ar', 'company' => 'CloudPeak Studios', 'website' => 'https://cloudpeak.ar', 'about' => 'Estudio de desarrollo especializado en aplicaciones cloud-native y microservicios.', 'location' => 'Buenos Aires', 'country' => 'Argentina'],
            ['name' => 'Laura', 'lastname' => 'Gómez', 'email' => 'laura@datastream.co', 'company' => 'DataStream Corp', 'website' => 'https://datastream.co', 'about' => 'Corporación dedicada al procesamiento de datos y analytics empresarial.', 'location' => 'Bogotá', 'country' => 'Colombia'],
            ['name' => 'Juan', 'lastname' => 'Méndez', 'email' => 'juan@innocode.cl', 'company' => 'InnoCode Group', 'website' => 'https://innocode.cl', 'about' => 'Grupo de desarrollo de software con enfoque en soluciones empresariales a medida.', 'location' => 'Santiago', 'country' => 'Chile'],
            ['name' => 'Marta', 'lastname' => 'Leiva', 'email' => 'marta@nexgen.pe', 'company' => 'NexGen Digital', 'website' => 'https://nexgen.pe', 'about' => 'Agencia digital de nueva generación especializada en e-commerce y marketing digital.', 'location' => 'Lima', 'country' => 'Perú'],
            ['name' => 'Fernando', 'lastname' => 'Villa', 'email' => 'fernando@appventure.co', 'company' => 'AppVenture Tech', 'website' => 'https://appventure.co', 'about' => 'Compañía de tecnología enfocada en desarrollo de aplicaciones móviles.', 'location' => 'Medellín', 'country' => 'Colombia'],
            ['name' => 'Julia', 'lastname' => 'Santos', 'email' => 'julia@codecraft.uy', 'company' => 'CodeCraft Studios', 'website' => 'https://codecraft.uy', 'about' => 'Estudio boutique de desarrollo de software con estándares de calidad excepcionales.', 'location' => 'Montevideo', 'country' => 'Uruguay'],
            ['name' => 'Ricardo', 'lastname' => 'Ortega', 'email' => 'ricardo@pixelworks.mx', 'company' => 'PixelWorks Agency', 'website' => 'https://pixelworks.mx', 'about' => 'Agencia creativa digital especializada en branding y desarrollo de aplicaciones.', 'location' => 'Guadalajara', 'country' => 'México'],
            ['name' => 'Sofía', 'lastname' => 'Delgado', 'email' => 'sofia@quantumdev.co', 'company' => 'QuantumDev Solutions', 'website' => 'https://quantumdev.co', 'about' => 'Empresa de desarrollo enfocada en soluciones de IA y machine learning.', 'location' => 'Bogotá', 'country' => 'Colombia'],
            ['name' => 'Diego', 'lastname' => 'Fuentes', 'email' => 'diego@novatech.ar', 'company' => 'NovaTech Argentina', 'website' => 'https://novatech.ar', 'about' => 'Compañía de consultoría tecnológica y transformación digital.', 'location' => 'Córdoba', 'country' => 'Argentina'],
            ['name' => 'Valentina', 'lastname' => 'Herrera', 'email' => 'valentina@startuplab.pe', 'company' => 'StartupLab Perú', 'website' => 'https://startuplab.pe', 'about' => 'Incubadora y aceleradora de startups tecnológicas.', 'location' => 'Lima', 'country' => 'Perú'],
            ['name' => 'Alejandro', 'lastname' => 'Medina', 'email' => 'alejandro@cyberguard.es', 'company' => 'CyberGuard Security', 'website' => 'https://cyberguard.es', 'about' => 'Empresa líder en ciberseguridad y auditoría informática.', 'location' => 'Barcelona', 'country' => 'España'],
            ['name' => 'Gabriela', 'lastname' => 'Rojas', 'email' => 'gabriela@greencode.cl', 'company' => 'GreenCode Technologies', 'website' => 'https://greencode.cl', 'about' => 'Desarrollo de software sustentable con enfoque en eficiencia energética.', 'location' => 'Santiago', 'country' => 'Chile'],
            ['name' => 'Martín', 'lastname' => 'Aguilar', 'email' => 'martin@finflow.uy', 'company' => 'FinFlow Fintech', 'website' => 'https://finflow.uy', 'about' => 'Fintech innovadora en pagos digitales y plataformas peer-to-peer.', 'location' => 'Montevideo', 'country' => 'Uruguay'],
            ['name' => 'Carolina', 'lastname' => 'Castillo', 'email' => 'carolina@edutech.mx', 'company' => 'EduTech México', 'website' => 'https://edutech.mx', 'about' => 'Plataforma educativa con soluciones de e-learning y gamificación.', 'location' => 'Monterrey', 'country' => 'México'],
            ['name' => 'Emilio', 'lastname' => 'Vargas', 'email' => 'emilio@logismart.co', 'company' => 'LogiSmart Solutions', 'website' => 'https://logismart.co', 'about' => 'Empresa de logística inteligente y software de gestión de cadena de suministro.', 'location' => 'Medellín', 'country' => 'Colombia'],
            ['name' => 'Natalia', 'lastname' => 'Cruz', 'email' => 'natalia@healthdev.ar', 'company' => 'HealthDev Labs', 'website' => 'https://healthdev.ar', 'about' => 'Laboratorio de innovación en salud digital y telemedicina.', 'location' => 'Buenos Aires', 'country' => 'Argentina'],
            ['name' => 'Felipe', 'lastname' => 'Salazar', 'email' => 'felipe@retailpro.pe', 'company' => 'RetailPro Digital', 'website' => 'https://retailpro.pe', 'about' => 'Consultora especializada en comercio electrónico y marketing digital.', 'location' => 'Lima', 'country' => 'Perú'],
            ['name' => 'Lorena', 'lastname' => 'Montoya', 'email' => 'lorena@gameforge.es', 'company' => 'GameForge Studios', 'website' => 'https://gameforge.es', 'about' => 'Estudio de desarrollo de videojuegos y experiencias interactivas.', 'location' => 'Madrid', 'country' => 'España'],
        ];
        
        foreach ($companiesData as $data) {
            $user = User::create([
                'name' => $data['name'],
                'lastname' => $data['lastname'],
                'email' => $data['email'],
                'password' => 'Demo1234!',
                'user_type' => 'company',
                'role' => 'company',
            ]);
            
            DB::table('users')->where('id', $user->id)->update(['email_verified_at' => Carbon::now()]);
            
            CompanyProfile::create([
                'user_id' => $user->id,
                'company_name' => $data['company'],
                'website' => $data['website'],
                'about' => $data['about'],
                'location' => $data['location'],
                'country' => $data['country'],
            ]);
            
            $companies[] = $user->id;
        }
        
        // 30 empresas adicionales generadas programáticamente
        $this->command->info('   Generando 30 empresas adicionales...');
        
        $additionalCompanies = [
            ['name' => 'Empresa', 'company' => 'TechFlow Solutions', 'location' => 'Miami', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'DigitalWave Agency', 'location' => 'São Paulo', 'country' => 'Brasil'],
            ['name' => 'Empresa', 'company' => 'CloudNine Systems', 'location' => 'Toronto', 'country' => 'Canadá'],
            ['name' => 'Empresa', 'company' => 'InnovateLab Corp', 'location' => 'Londres', 'country' => 'Reino Unido'],
            ['name' => 'Empresa', 'company' => 'DataPulse Analytics', 'location' => 'San Francisco', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'QuantumCode Tech', 'location' => 'Austin', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'NexusDev Studios', 'location' => 'Seattle', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'ApexDigital Solutions', 'location' => 'Dallas', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'ByteForce Labs', 'location' => 'Chicago', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'CodeNest Agency', 'location' => 'Boston', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'DevStream Inc', 'location' => 'Denver', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'TechPulse Corp', 'location' => 'Portland', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'InnovateTech Solutions', 'location' => 'Phoenix', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'CodeWave Agency', 'location' => 'Atlanta', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'DevForce Labs', 'location' => 'Philadelphia', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'TechNova Digital', 'location' => 'Miami', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'CloudCode Systems', 'location' => 'Orlando', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'InnovateDev Studio', 'location' => 'San Diego', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'DigitalForce Corp', 'location' => 'Houston', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'TechStream Agency', 'location' => 'New York', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'CodePulse Labs', 'location' => 'Los Angeles', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'DevNexus Solutions', 'location' => 'San Jose', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'InnovateCode Inc', 'location' => 'Washington', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'TechForge Digital', 'location' => 'Las Vegas', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'CloudDev Agency', 'location' => 'Philadelphia', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'CodeStream Labs', 'location' => 'Columbus', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'DevTech Solutions', 'location' => 'Charlotte', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'TechWave Corp', 'location' => 'Indianapolis', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'InnovateLabs Agency', 'location' => 'Seattle', 'country' => 'Estados Unidos'],
            ['name' => 'Empresa', 'company' => 'DigitalCode Systems', 'location' => 'Denver', 'country' => 'Estados Unidos'],
        ];
        
        $firstNames = ['Juan', 'Pedro', 'Manuel', 'José', 'Antonio', 'Francisco', 'Luis', 'Carlos', 'Miguel', 'Javier'];
        $lastNames = ['García', 'López', 'Martínez', 'Rodríguez', 'González', 'Pérez', 'Sánchez', 'Ramírez', 'Torres', 'Flores'];
        
        foreach ($additionalCompanies as $index => $data) {
            $firstName = $firstNames[$index % count($firstNames)];
            $lastName = $lastNames[$index % count($lastNames)];
            
            $user = User::create([
                'name' => $firstName,
                'lastname' => $lastName,
                'email' => strtolower($firstName) . '.' . strtolower($lastName) . ($index + 1) . '@company.com',
                'password' => 'Demo1234!',
                'user_type' => 'company',
                'role' => 'company',
            ]);
            
            DB::table('users')->where('id', $user->id)->update(['email_verified_at' => Carbon::now()]);
            
            CompanyProfile::create([
                'user_id' => $user->id,
                'company_name' => $data['company'],
                'website' => 'https://' . strtolower(str_replace(' ', '', $data['company'])) . '.com',
                'about' => 'Empresa de tecnología y desarrollo de software soluciones innovadoras.',
                'location' => $data['location'],
                'country' => $data['country'],
            ]);
            
            $companies[] = $user->id;
        }
        
        $this->command->info('   ✓ Total empresas creadas: ' . count($companies));
        
        return $companies;
    }

    private function createDevelopers(array $skills): array
    {
        $this->command->info('👨‍💻 Creando 200 desarrolladores y perfiles...');
        
        $developers = [];
        
        // 50 desarrolladores con datos detallados
        $developersData = [
            ['name' => 'Andrés', 'lastname' => 'García', 'email' => 'andres@devmail.com', 'headline' => 'Full Stack Developer | React + Laravel', 'bio' => 'Desarrollador Full Stack con 6 años de experiencia construyendo aplicaciones web escalables.', 'hourly_rate' => 65, 'availability' => 'available', 'experience_years' => 6, 'languages' => ['Español', 'Inglés'], 'location' => 'Bogotá', 'country' => 'Colombia', 'skill_names' => ['Laravel', 'React', 'TypeScript', 'TailwindCSS', 'MySQL']],
            ['name' => 'Valentina', 'lastname' => 'López', 'email' => 'valentina@devmail.com', 'headline' => 'Frontend Specialist | React & Next.js', 'bio' => 'Especialista en frontend con pasión por crear interfaces accesibles.', 'hourly_rate' => 55, 'availability' => 'available', 'experience_years' => 4, 'languages' => ['Español', 'Inglés', 'Portugués'], 'location' => 'Buenos Aires', 'country' => 'Argentina', 'skill_names' => ['React', 'Next.js', 'TypeScript', 'TailwindCSS', 'Figma']],
            ['name' => 'Santiago', 'lastname' => 'Martínez', 'email' => 'santiago@devmail.com', 'headline' => 'Senior Backend Engineer | Node.js + Docker', 'bio' => 'Ingeniero backend senior con experiencia en arquitecturas de microservicios.', 'hourly_rate' => 95, 'availability' => 'busy', 'experience_years' => 9, 'languages' => ['Español', 'Inglés'], 'location' => 'Santiago', 'country' => 'Chile', 'skill_names' => ['Node.js', 'Docker', 'Kubernetes', 'PostgreSQL', 'AWS', 'TypeScript']],
            ['name' => 'Camila', 'lastname' => 'Rodríguez', 'email' => 'camila@devmail.com', 'headline' => 'DevOps Engineer | AWS & Kubernetes', 'bio' => 'Ingeniera DevOps con experiencia en automatización de infraestructura.', 'hourly_rate' => 85, 'availability' => 'available', 'experience_years' => 7, 'languages' => ['Español', 'Inglés'], 'location' => 'Lima', 'country' => 'Perú', 'skill_names' => ['Docker', 'Kubernetes', 'AWS', 'Terraform', 'Python']],
            ['name' => 'Mateo', 'lastname' => 'Hernández', 'email' => 'mateo@devmail.com', 'headline' => 'Mobile Developer | Flutter & React Native', 'bio' => 'Desarrollador móvil multiplataforma con apps de alto impacto.', 'hourly_rate' => 70, 'availability' => 'available', 'experience_years' => 5, 'languages' => ['Español', 'Inglés'], 'location' => 'Ciudad de México', 'country' => 'México', 'skill_names' => ['Flutter', 'React Native', 'TypeScript', 'Swift', 'Kotlin']],
            ['name' => 'Isabella', 'lastname' => 'Torres', 'email' => 'isabella@devmail.com', 'headline' => 'UI/UX Developer | Figma & React', 'bio' => 'Diseñadora y desarrolladora UI/UX con enfoque centrado en el usuario.', 'hourly_rate' => 60, 'availability' => 'busy', 'experience_years' => 5, 'languages' => ['Español', 'Inglés', 'Francés'], 'location' => 'Madrid', 'country' => 'España', 'skill_names' => ['Figma', 'React', 'TailwindCSS', 'Next.js', 'Vue.js']],
            ['name' => 'Sebastián', 'lastname' => 'Ramírez', 'email' => 'sebastian@devmail.com', 'headline' => 'Data Engineer | Python & AWS', 'bio' => 'Ingeniero de datos especializado en pipelines ETL y arquitecturas cloud.', 'hourly_rate' => 80, 'availability' => 'available', 'experience_years' => 6, 'languages' => ['Español', 'Inglés'], 'location' => 'Medellín', 'country' => 'Colombia', 'skill_names' => ['Python', 'AWS', 'PostgreSQL', 'MongoDB', 'Docker']],
            ['name' => 'Lucía', 'lastname' => 'Pérez', 'email' => 'lucia@devmail.com', 'headline' => 'Cloud Architect | AWS & Terraform', 'bio' => 'Arquitecta cloud con certificaciones en AWS y GCP.', 'hourly_rate' => 110, 'availability' => 'available', 'experience_years' => 10, 'languages' => ['Español', 'Inglés'], 'location' => 'Montevideo', 'country' => 'Uruguay', 'skill_names' => ['AWS', 'Terraform', 'Docker', 'Kubernetes', 'Python', 'Go']],
            ['name' => 'Daniel', 'lastname' => 'Morales', 'email' => 'daniel@devmail.com', 'headline' => 'Blockchain Developer | Solidity & Web3', 'bio' => 'Desarrollador blockchain con experiencia en smart contracts.', 'hourly_rate' => 100, 'availability' => 'unavailable', 'experience_years' => 4, 'languages' => ['Español', 'Inglés'], 'location' => 'Bogotá', 'country' => 'Colombia', 'skill_names' => ['TypeScript', 'Node.js', 'React', 'MongoDB', 'GraphQL']],
            ['name' => 'Paula', 'lastname' => 'Sánchez', 'email' => 'paula@devmail.com', 'headline' => 'QA Automation Engineer', 'bio' => 'Ingeniera de QA con enfoque en automatización de pruebas.', 'hourly_rate' => 55, 'availability' => 'available', 'experience_years' => 5, 'languages' => ['Español', 'Inglés'], 'location' => 'Santiago', 'country' => 'Chile', 'skill_names' => ['Python', 'TypeScript', 'Docker', 'PostgreSQL', 'Node.js']],
            ['name' => 'Nicolás', 'lastname' => 'Flores', 'email' => 'nicolas@devmail.com', 'headline' => 'AI/ML Engineer | Python & TensorFlow', 'bio' => 'Ingeniero de Machine Learning con experiencia en NLP y visión por computadora.', 'hourly_rate' => 105, 'availability' => 'busy', 'experience_years' => 7, 'languages' => ['Español', 'Inglés', 'Alemán'], 'location' => 'Buenos Aires', 'country' => 'Argentina', 'skill_names' => ['Python', 'AWS', 'PostgreSQL', 'Docker', 'MongoDB']],
            ['name' => 'María', 'lastname' => 'Vargas', 'email' => 'maria@devmail.com', 'headline' => 'Full Stack Developer | MERN Stack', 'bio' => 'Desarrolladora Full Stack con dominio del stack MERN.', 'hourly_rate' => 75, 'availability' => 'busy', 'experience_years' => 8, 'languages' => ['Español', 'Inglés'], 'location' => 'Ciudad de México', 'country' => 'México', 'skill_names' => ['React', 'Node.js', 'MongoDB', 'TypeScript', 'AWS', 'GraphQL']],
            ['name' => 'Alejandro', 'lastname' => 'Castro', 'email' => 'alejandro@devmail.com', 'headline' => 'Senior PHP Developer | Laravel Expert', 'bio' => 'Desarrollador PHP senior con más de 10 años de experiencia en Laravel.', 'hourly_rate' => 70, 'availability' => 'available', 'experience_years' => 11, 'languages' => ['Español', 'Inglés'], 'location' => 'Lima', 'country' => 'Perú', 'skill_names' => ['PHP', 'Laravel', 'MySQL', 'Vue.js', 'Docker', 'Redis']],
            ['name' => 'Sofía', 'lastname' => 'Mendoza', 'email' => 'sofia@devmail.com', 'headline' => 'React Native Developer | Mobile Expert', 'bio' => 'Desarrolladora mobile especializada en React Native.', 'hourly_rate' => 65, 'availability' => 'available', 'experience_years' => 4, 'languages' => ['Español', 'Inglés', 'Italiano'], 'location' => 'Bogotá', 'country' => 'Colombia', 'skill_names' => ['React Native', 'TypeScript', 'React', 'Node.js', 'Figma']],
            ['name' => 'Ricardo', 'lastname' => 'Navarro', 'email' => 'ricardo@devmail.com', 'headline' => 'Python Backend Developer | Django & FastAPI', 'bio' => 'Desarrollador backend Python con experiencia en Django y FastAPI.', 'hourly_rate' => 80, 'availability' => 'available', 'experience_years' => 8, 'languages' => ['Español', 'Inglés'], 'location' => 'Madrid', 'country' => 'España', 'skill_names' => ['Python', 'Django', 'PostgreSQL', 'Docker', 'Redis', 'AWS']],
            ['name' => 'Elena', 'lastname' => 'Jiménez', 'email' => 'elena@devmail.com', 'headline' => 'Vue.js Developer | Frontend Expert', 'bio' => 'Desarrolladora Vue.js con experiencia en aplicaciones SPA.', 'hourly_rate' => 60, 'availability' => 'available', 'experience_years' => 5, 'languages' => ['Español', 'Inglés'], 'location' => 'Barcelona', 'country' => 'España', 'skill_names' => ['Vue.js', 'JavaScript', 'TailwindCSS', 'Node.js', 'MySQL']],
            ['name' => 'Gabriel', 'lastname' => 'Ruiz', 'email' => 'gabriel@devmail.com', 'headline' => 'Go Backend Developer | Microservices', 'bio' => 'Desarrollador Go especializado en microservicios y APIs de alto rendimiento.', 'hourly_rate' => 90, 'availability' => 'available', 'experience_years' => 6, 'languages' => ['Español', 'Inglés'], 'location' => 'Guadalajara', 'country' => 'México', 'skill_names' => ['Go', 'Rust', 'Docker', 'Kubernetes', 'PostgreSQL']],
            ['name' => 'Carmen', 'lastname' => 'Ortiz', 'email' => 'carmen@devmail.com', 'headline' => 'Angular Developer | Enterprise Apps', 'bio' => 'Desarrolladora Angular con experiencia en aplicaciones enterprise.', 'hourly_rate' => 65, 'availability' => 'busy', 'experience_years' => 7, 'languages' => ['Español', 'Inglés'], 'location' => 'Monterrey', 'country' => 'México', 'skill_names' => ['Angular', 'TypeScript', 'RxJS', 'Node.js', 'MongoDB']],
            ['name' => 'Diego', 'lastname' => 'Vega', 'email' => 'diego@devmail.com', 'headline' => 'Swift iOS Developer | Apple Platforms', 'bio' => 'Desarrollador iOS nativo con apps en el App Store.', 'hourly_rate' => 85, 'availability' => 'available', 'experience_years' => 6, 'languages' => ['Español', 'Inglés'], 'location' => 'Miami', 'country' => 'Estados Unidos', 'skill_names' => ['Swift', 'iOS', 'Objective-C', 'CoreData', 'SwiftUI']],
            ['name' => 'Ana', 'lastname' => 'Campos', 'email' => 'ana@devmail.com', 'headline' => 'Kotlin Android Developer', 'bio' => 'Desarrolladora Android nativa con experiencia en apps de alta demanda.', 'hourly_rate' => 75, 'availability' => 'available', 'experience_years' => 5, 'languages' => ['Español', 'Inglés'], 'location' => 'Cali', 'country' => 'Colombia', 'skill_names' => ['Kotlin', 'Android', 'Java', 'Firebase', 'REST APIs']],
            ['name' => 'Jorge', 'lastname' => 'Reyes', 'email' => 'jorge@devmail.com', 'headline' => 'Database Administrator | PostgreSQL & MySQL', 'bio' => 'DBA especializado en optimización y administración de bases de datos.', 'hourly_rate' => 70, 'availability' => 'available', 'experience_years' => 8, 'languages' => ['Español', 'Inglés'], 'location' => 'Quito', 'country' => 'Ecuador', 'skill_names' => ['PostgreSQL', 'MySQL', 'Redis', 'MongoDB', 'AWS']],
            ['name' => 'Laura', 'lastname' => 'Mora', 'email' => 'laura@devmail.com', 'headline' => 'GraphQL API Developer', 'bio' => 'Desarrolladora especializada en APIs GraphQL y Apollo.', 'hourly_rate' => 70, 'availability' => 'available', 'experience_years' => 4, 'languages' => ['Español', 'Inglés'], 'location' => 'Santiago', 'country' => 'Chile', 'skill_names' => ['GraphQL', 'Node.js', 'React', 'TypeScript', 'PostgreSQL']],
            ['name' => 'Oscar', 'lastname' => 'Herrera', 'email' => 'oscar@devmail.com', 'headline' => 'Rust Systems Developer', 'bio' => 'Desarrollador Rust para sistemas de alto rendimiento.', 'hourly_rate' => 95, 'availability' => 'unavailable', 'experience_years' => 5, 'languages' => ['Español', 'Inglés'], 'location' => 'Buenos Aires', 'country' => 'Argentina', 'skill_names' => ['Rust', 'Go', 'Docker', 'PostgreSQL', 'Linux']],
            ['name' => 'Patricia', 'lastname' => 'Guzmán', 'email' => 'patricia@devmail.com', 'headline' => 'WordPress Developer | CMS Expert', 'bio' => 'Desarrolladora WordPress con plugins personalizados.', 'hourly_rate' => 45, 'availability' => 'available', 'experience_years' => 6, 'languages' => ['Español', 'Inglés'], 'location' => 'Lima', 'country' => 'Perú', 'skill_names' => ['PHP', 'WordPress', 'MySQL', 'JavaScript', 'CSS']],
            ['name' => 'Fernando', 'lastname' => 'Luna', 'email' => 'fernando@devmail.com', 'headline' => 'ElasticSearch Developer', 'bio' => 'Desarrollador especializado en búsquedas y Big Data con ElasticSearch.', 'hourly_rate' => 80, 'availability' => 'available', 'experience_years' => 5, 'languages' => ['Español', 'Inglés'], 'location' => 'Bogotá', 'country' => 'Colombia', 'skill_names' => ['ElasticSearch', 'Python', 'Docker', 'AWS', 'Kibana']],
            ['name' => 'Sandra', 'lastname' => 'Aguilar', 'email' => 'sandra@devmail.com', 'headline' => 'JAMStack Developer | Static Sites', 'bio' => 'Desarrolladora JAMStack especializada en sitios estáticos de alto rendimiento.', 'hourly_rate' => 55, 'availability' => 'available', 'experience_years' => 4, 'languages' => ['Español', 'Inglés'], 'location' => 'Medellín', 'country' => 'Colombia', 'skill_names' => ['Next.js', 'Gatsby', 'React', 'TailwindCSS', 'Netlify']],
            ['name' => 'Hugo', 'lastname' => 'Soto', 'email' => 'hugo@devmail.com', 'headline' => 'Firebase Developer | Serverless', 'bio' => 'Desarrollador Firebase y arquitecturas serverless.', 'hourly_rate' => 65, 'availability' => 'available', 'experience_years' => 5, 'languages' => ['Español', 'Inglés'], 'location' => 'Ciudad de México', 'country' => 'México', 'skill_names' => ['Firebase', 'React', 'Node.js', 'TypeScript', 'Cloud Functions']],
            ['name' => 'Verónica', 'lastname' => 'Rivas', 'email' => 'veronica@devmail.com', 'headline' => 'Django REST Framework Expert', 'bio' => 'Desarrolladora Django con APIs REST robustas.', 'hourly_rate' => 70, 'availability' => 'busy', 'experience_years' => 6, 'languages' => ['Español', 'Inglés'], 'location' => 'Valencia', 'country' => 'España', 'skill_names' => ['Django', 'Python', 'PostgreSQL', 'Redis', 'Docker']],
            ['name' => 'Arturo', 'lastname' => 'Mejía', 'email' => 'arturo@devmail.com', 'headline' => 'Infrastructure as Code | Terraform', 'bio' => 'Ingeniero de infraestructura como código con Terraform.', 'hourly_rate' => 85, 'availability' => 'available', 'experience_years' => 7, 'languages' => ['Español', 'Inglés'], 'location' => 'Guadalajara', 'country' => 'México', 'skill_names' => ['Terraform', 'AWS', 'Kubernetes', 'Docker', 'Ansible']],
            ['name' => 'Gloria', 'lastname' => 'Cortés', 'email' => 'gloria@devmail.com', 'headline' => 'Shopify Developer | E-commerce', 'bio' => 'Desarrolladora Shopify especializada en tiendas online.', 'hourly_rate' => 60, 'availability' => 'available', 'experience_years' => 5, 'languages' => ['Español', 'Inglés'], 'location' => 'Miami', 'country' => 'Estados Unidos', 'skill_names' => ['Shopify', 'Liquid', 'JavaScript', 'CSS', 'Ruby']],
            ['name' => 'Miguel', 'lastname' => 'Arias', 'email' => 'miguel@devmail.com', 'headline' => 'Redis & Caching Expert', 'bio' => 'Especialista en Redis y estrategias de caché para alto rendimiento.', 'hourly_rate' => 75, 'availability' => 'available', 'experience_years' => 6, 'languages' => ['Español', 'Inglés'], 'location' => 'Buenos Aires', 'country' => 'Argentina', 'skill_names' => ['Redis', 'Node.js', 'Python', 'Docker', 'AWS']],
            ['name' => 'Rosa', 'lastname' => 'Miranda', 'email' => 'rosa@devmail.com', 'headline' => 'TailwindCSS UI Designer', 'bio' => 'Diseñadora UI especializada en TailwindCSS y sistemas de diseño.', 'hourly_rate' => 55, 'availability' => 'available', 'experience_years' => 3, 'languages' => ['Español', 'Inglés'], 'location' => 'Barcelona', 'country' => 'España', 'skill_names' => ['TailwindCSS', 'React', 'Figma', 'HTML', 'CSS']],
            ['name' => 'Eduardo', 'lastname' => 'Bermúdez', 'email' => 'eduardo@devmail.com', 'headline' => 'WebSockets Real-time Developer', 'bio' => 'Desarrollador especializado en aplicaciones real-time con WebSockets.', 'hourly_rate' => 80, 'availability' => 'available', 'experience_years' => 7, 'languages' => ['Español', 'Inglés'], 'location' => 'Santiago', 'country' => 'Chile', 'skill_names' => ['Node.js', 'Socket.io', 'React', 'Redis', 'MongoDB']],
            ['name' => 'Teresa', 'lastname' => 'Gálvez', 'email' => 'teresa@devmail.com', 'headline' => 'Cybersecurity Consultant', 'bio' => 'Consultora de ciberseguridad y auditorías de aplicaciones.', 'hourly_rate' => 100, 'availability' => 'busy', 'experience_years' => 9, 'languages' => ['Español', 'Inglés'], 'location' => 'Madrid', 'country' => 'España', 'skill_names' => ['Python', 'Linux', 'AWS', 'Docker', 'Penetration Testing']],
            ['name' => 'Roberto', 'lastname' => 'Espinosa', 'email' => 'roberto@devmail.com', 'headline' => 'Unity Game Developer', 'bio' => 'Desarrollador de videojuegos con Unity.', 'hourly_rate' => 75, 'availability' => 'available', 'experience_years' => 6, 'languages' => ['Español', 'Inglés'], 'location' => 'Bogotá', 'country' => 'Colombia', 'skill_names' => ['Unity', 'C#', 'Game Development', '3D', 'AR/VR']],
            ['name' => 'Claudia', 'lastname' => 'Acosta', 'email' => 'claudia@devmail.com', 'headline' => 'Data Visualization | D3.js', 'bio' => 'Especialista en visualizaciones de datos con D3.js y Chart.js.', 'hourly_rate' => 70, 'availability' => 'available', 'experience_years' => 5, 'languages' => ['Español', 'Inglés'], 'location' => 'Ciudad de México', 'country' => 'México', 'skill_names' => ['D3.js', 'React', 'JavaScript', 'Python', 'PostgreSQL']],
            ['name' => 'Javier', 'lastname' => 'Oliva', 'email' => 'javier@devmail.com', 'headline' => 'CI/CD Pipeline Engineer', 'bio' => 'Ingeniero de pipelines CI/CD y automatización de despliegues.', 'hourly_rate' => 80, 'availability' => 'available', 'experience_years' => 6, 'languages' => ['Español', 'Inglés'], 'location' => 'Lima', 'country' => 'Perú', 'skill_names' => ['Docker', 'Kubernetes', 'Jenkins', 'GitHub Actions', 'AWS']],
            ['name' => 'Silvia', 'lastname' => 'León', 'email' => 'silvia@devmail.com', 'headline' => 'Stripe Payment Integration', 'bio' => 'Desarrolladora especializada en integraciones de pago con Stripe.', 'hourly_rate' => 70, 'availability' => 'available', 'experience_years' => 5, 'languages' => ['Español', 'Inglés'], 'location' => 'Montevideo', 'country' => 'Uruguay', 'skill_names' => ['Stripe', 'Node.js', 'React', 'PostgreSQL', 'REST APIs']],
            ['name' => 'Alberto', 'lastname' => 'Padilla', 'email' => 'alberto@devmail.com', 'headline' => 'Serverless Architect | AWS Lambda', 'bio' => 'Arquitecto serverless especializado en Lambda y servicios AWS.', 'hourly_rate' => 95, 'availability' => 'busy', 'experience_years' => 8, 'languages' => ['Español', 'Inglés'], 'location' => 'Seattle', 'country' => 'Estados Unidos', 'skill_names' => ['AWS Lambda', 'Node.js', 'Python', 'DynamoDB', 'API Gateway']],
            ['name' => 'Beatriz', 'lastname' => 'Sanabria', 'email' => 'beatriz@devmail.com', 'headline' => 'NestJS Backend Developer', 'bio' => 'Desarrolladora NestJS con experiencia en arquitecturas escalables.', 'hourly_rate' => 75, 'availability' => 'available', 'experience_years' => 5, 'languages' => ['Español', 'Inglés'], 'location' => 'Asunción', 'country' => 'Paraguay', 'skill_names' => ['NestJS', 'TypeScript', 'PostgreSQL', 'Docker', 'Redis']],
            ['name' => 'Sergio', 'lastname' => 'Barrera', 'email' => 'sergio@devmail.com', 'headline' => 'PWA Progressive Web Apps', 'bio' => 'Desarrollador de Progressive Web Apps conService Workers.', 'hourly_rate' => 65, 'availability' => 'available', 'experience_years' => 5, 'languages' => ['Español', 'Inglés'], 'location' => 'Córdoba', 'country' => 'Argentina', 'skill_names' => ['PWA', 'React', 'JavaScript', 'Service Workers', 'IndexedDB']],
            ['name' => 'Mariana', 'lastname' => 'Quintana', 'email' => 'mariana@devmail.com', 'headline' => 'Storybook Component Library', 'bio' => 'Desarrolladora de bibliotecas de componentes con Storybook.', 'hourly_rate' => 60, 'availability' => 'available', 'experience_years' => 4, 'languages' => ['Español', 'Inglés'], 'location' => 'Bogotá', 'country' => 'Colombia', 'skill_names' => ['Storybook', 'React', 'TypeScript', 'TailwindCSS', 'Figma']],
            ['name' => 'Tomás', 'lastname' => 'Salas', 'email' => 'tomas@devmail.com', 'headline' => 'MongoDB Atlas Expert', 'bio' => 'Especialista en MongoDB Atlas y bases de datos NoSQL.', 'hourly_rate' => 80, 'availability' => 'available', 'experience_years' => 7, 'languages' => ['Español', 'Inglés'], 'location' => 'Santiago', 'country' => 'Chile', 'skill_names' => ['MongoDB', 'Node.js', 'Express', 'TypeScript', 'AWS']],
            ['name' => 'Adriana', 'lastname' => 'Peralta', 'email' => 'adriana@devmail.com', 'headline' => 'Email Marketing Developer', 'bio' => 'Desarrolladora de templates de email y automatización de marketing.', 'hourly_rate' => 45, 'availability' => 'available', 'experience_years' => 4, 'languages' => ['Español', 'Inglés'], 'location' => 'Guadalajara', 'country' => 'México', 'skill_names' => ['HTML Email', 'MJML', 'JavaScript', 'CSS', 'Mailchimp']],
            ['name' => 'Félix', 'lastname' => 'Cáceres', 'email' => 'felix@devmail.com', 'headline' => 'gRPC API Developer', 'bio' => 'Desarrollador de APIs de alto rendimiento con gRPC.', 'hourly_rate' => 85, 'availability' => 'available', 'experience_years' => 6, 'languages' => ['Español', 'Inglés'], 'location' => 'Buenos Aires', 'country' => 'Argentina', 'skill_names' => ['gRPC', 'Go', 'Node.js', 'Protocol Buffers', 'Docker']],
            ['name' => 'Daniela', 'lastname' => 'Oviedo', 'email' => 'daniela@devmail.com', 'headline' => 'Microservices Architect', 'bio' => 'Arquitecta de microservicios con experiencia en Kubernetes.', 'hourly_rate' => 110, 'availability' => 'busy', 'experience_years' => 10, 'languages' => ['Español', 'Inglés', 'Portugués'], 'location' => 'São Paulo', 'country' => 'Brasil', 'skill_names' => ['Kubernetes', 'Docker', 'Go', 'Node.js', 'AWS', 'Terraform']],
            ['name' => 'Gustavo', 'lastname' => 'Uribe', 'email' => 'gustavo@devmail.com', 'headline' => 'Testing Specialist | Jest & Cypress', 'bio' => 'Especialista en testing con Jest y Cypress para aplicaciones modernas.', 'hourly_rate' => 60, 'availability' => 'available', 'experience_years' => 5, 'languages' => ['Español', 'Inglés'], 'location' => 'Medellín', 'country' => 'Colombia', 'skill_names' => ['Jest', 'Cypress', 'React', 'TypeScript', 'Node.js']],
            ['name' => 'Eugenia', 'lastname' => 'Benítez', 'email' => 'eugenia@devmail.com', 'headline' => 'Headless CMS Developer', 'bio' => 'Desarrolladora de CMS headless como Strapi y Contentful.', 'hourly_rate' => 65, 'availability' => 'available', 'experience_years' => 4, 'languages' => ['Español', 'Inglés'], 'location' => 'Montevideo', 'country' => 'Uruguay', 'skill_names' => ['Strapi', 'Contentful', 'Next.js', 'GraphQL', 'Node.js']],
            ['name' => 'Mauricio', 'lastname' => 'Sandoval', 'email' => 'mauricio@devmail.com', 'headline' => 'Real-time Notifications | Firebase', 'bio' => 'Desarrollador de sistemas de notificaciones en tiempo real.', 'hourly_rate' => 70, 'availability' => 'available', 'experience_years' => 5, 'languages' => ['Español', 'Inglés'], 'location' => 'Lima', 'country' => 'Perú', 'skill_names' => ['Firebase', 'Node.js', 'React', 'Cloud Functions', 'WebSockets']],
            ['name' => 'Yolanda', 'lastname' => 'Estrada', 'email' => 'yolanda@devmail.com', 'headline' => 'Accessibility A11y Specialist', 'bio' => 'Especialista en accesibilidad web y cumplimiento WCAG.', 'hourly_rate' => 55, 'availability' => 'available', 'experience_years' => 4, 'languages' => ['Español', 'Inglés'], 'location' => 'Barcelona', 'country' => 'España', 'skill_names' => ['Accessibility', 'HTML', 'CSS', 'React', 'Screen Readers']],
            ['name' => 'Cristian', 'lastname' => 'Domínguez', 'email' => 'cristian@devmail.com', 'headline' => 'Performance Optimization Expert', 'bio' => 'Especialista en optimización de rendimiento web.', 'hourly_rate' => 85, 'availability' => 'available', 'experience_years' => 8, 'languages' => ['Español', 'Inglés'], 'location' => 'Miami', 'country' => 'Estados Unidos', 'skill_names' => ['Performance', 'Lighthouse', 'React', 'Webpack', 'AWS']],
        ];
        
        foreach ($developersData as $data) {
            $user = User::create([
                'name' => $data['name'],
                'lastname' => $data['lastname'],
                'email' => $data['email'],
                'password' => 'Demo1234!',
                'user_type' => 'programmer',
                'role' => 'programmer',
            ]);
            
            DB::table('users')->where('id', $user->id)->update(['email_verified_at' => Carbon::now()]);
            
            DeveloperProfile::create([
                'user_id' => $user->id,
                'headline' => $data['headline'],
                'skills' => json_encode($data['skill_names']),
                'bio' => $data['bio'],
                'links' => json_encode([]),
                'location' => $data['location'],
                'country' => $data['country'],
                'hourly_rate' => $data['hourly_rate'],
                'availability' => $data['availability'],
                'experience_years' => $data['experience_years'],
                'languages' => json_encode($data['languages']),
            ]);
            
            foreach ($data['skill_names'] as $skillName) {
                if (isset($skills[$skillName])) {
                    DB::table('developer_skill')->insert([
                        'developer_id' => $user->id,
                        'skill_id' => $skills[$skillName],
                        'proficiency' => rand(3, 5),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }
            }
            
            $developers[] = $user->id;
        }
        
        // 150 desarrolladores adicionales generados programáticamente
        $this->command->info('   Generando 150 desarrolladores adicionales...');
        
        $firstNames = [
            'Miguel', 'Gabriel', 'Tomás', 'Emilio', 'Joaquín', 'Federico', 'Martín', 'Lucas', 'Hugo', 'Óscar',
            'Rafael', 'Simón', 'Adrián', 'Bruno', 'Iván', 'Esteban', 'Rodrigo', 'Manuel', 'Gonzalo', 'Patricio',
            'Álvaro', 'Javier', 'Enrique', 'Carlos', 'Eduardo', 'Felipe', 'Francisco', 'Ignacio', 'Roberto', 'Ernesto',
            'Humberto', 'Elena', 'Claudia', 'Natalia', 'Fernanda', 'Daniela', 'Carolina', 'Mariana', 'Paola', 'Lorena',
            'Mónica', 'Patricia', 'Gabriela', 'Andrea', 'Diana', 'Silvia', 'Rosa', 'Sara', 'Renata', 'Jimena',
            'Catalina', 'Verónica', 'Alejandra', 'Teresa', 'Pilar', 'Ximena', 'Julia', 'Alicia', 'Irene', 'Inés',
            'Victoria', 'Carmen', 'Luciana', 'Emilia', 'Sofía', 'Isidora', 'Valentina', 'María', 'Esperanza', 'Consuelo',
            'Juan', 'Pedro', 'José', 'Antonio', 'Luis', 'Miguel', 'Juan Carlos', 'José Luis', 'Francisco', 'José María',
            'Andrés', 'Alejandro', 'Sergio', 'Javier', 'Carlos', 'David', 'Fernando', 'Jorge', 'Alberto', 'Roberto',
            'Raúl', 'Antonio', 'Francisco Javier', 'Juan Antonio', 'Óscar', 'Rafael', 'Enrique', 'Pablo', 'Santiago', 'Diego',
            'Marcos', 'Iván', 'Rubén', 'Adrián', 'Mario', 'Óliver', 'Bruno', 'Thiago', 'Matías', 'Benjamín',
            'Samuel', 'Daniel', 'Sebastián', 'Emilio', 'Joaquín', 'Gabriel', 'Nicolás', 'Eduardo', 'Cristian', 'Kevin',
            'Alex', 'Brian', 'Marco', 'Ángel', 'Israel', 'Erik', 'Guillermo', 'Víctor', 'Hugo', 'Damián'
        ];
        
        $lastNames = [
            'López', 'Martínez', 'González', 'Rodríguez', 'Hernández', 'Pérez', 'García', 'Sánchez', 'Ramírez', 'Torres',
            'Flores', 'Rivera', 'Gómez', 'Díaz', 'Cruz', 'Morales', 'Ortiz', 'Gutiérrez', 'Chávez', 'Ramos',
            'Vásquez', 'Castillo', 'Jiménez', 'Vargas', 'Rojas', 'Herrera', 'Medina', 'Aguilar', 'Peña', 'Reyes',
            'Salazar', 'Delgado', 'Fuentes', 'Navarro', 'Montoya', 'Cardenas', 'Molina', 'Arias', 'Silva', 'Orozco',
            'Sandoval', 'Estrada', 'Cortés', 'Acosta', 'León', 'Bermúdez', 'Espinosa', 'Gálvez', 'Quintana', 'Cáceres'
        ];
        
        $locations = [
            ['location' => 'Bogotá', 'country' => 'Colombia'],
            ['location' => 'Medellín', 'country' => 'Colombia'],
            ['location' => 'Cali', 'country' => 'Colombia'],
            ['location' => 'Ciudad de México', 'country' => 'México'],
            ['location' => 'Guadalajara', 'country' => 'México'],
            ['location' => 'Monterrey', 'country' => 'México'],
            ['location' => 'Buenos Aires', 'country' => 'Argentina'],
            ['location' => 'Córdoba', 'country' => 'Argentina'],
            ['location' => 'Santiago', 'country' => 'Chile'],
            ['location' => 'Valparaíso', 'country' => 'Chile'],
            ['location' => 'Lima', 'country' => 'Perú'],
            ['location' => 'Arequipa', 'country' => 'Perú'],
            ['location' => 'Madrid', 'country' => 'España'],
            ['location' => 'Barcelona', 'country' => 'España'],
            ['location' => 'Montevideo', 'country' => 'Uruguay'],
            ['location' => 'Quito', 'country' => 'Ecuador'],
            ['location' => 'San José', 'country' => 'Costa Rica'],
            ['location' => 'Panamá', 'country' => 'Panamá'],
            ['location' => 'Santo Domingo', 'country' => 'República Dominicana'],
            ['location' => 'Asunción', 'country' => 'Paraguay'],
            ['location' => 'Miami', 'country' => 'Estados Unidos'],
            ['location' => 'Los Angeles', 'country' => 'Estados Unidos'],
            ['location' => 'New York', 'country' => 'Estados Unidos'],
            ['location' => 'Seattle', 'country' => 'Estados Unidos'],
            ['location' => 'São Paulo', 'country' => 'Brasil'],
        ];
        
        $skillNames = array_keys($skills);
        
        $headlineTemplates = [
            'Full Stack Developer | %s + %s',
            'Senior %s Developer',
            '%s Engineer | Cloud & DevOps',
            'Frontend Specialist | %s & %s',
            'Backend Developer | %s + %s',
            'Mobile Developer | %s & %s',
            '%s Architect | Enterprise Solutions',
            'Software Engineer | %s Stack',
            '%s Developer | Agile & Scrum',
            'Tech Lead | %s + %s',
        ];
        
        $bioTemplates = [
            'Desarrollador con %d años de experiencia especializado en %s.',
            'Ingeniero de software con %d años creando soluciones con %s.',
            'Profesional con %d años en desarrollo de software, experto en %s.',
            'Desarrollador experimentado con %d años usando %s.',
            'Con %d años de experiencia en %s, me especializo en crear soluciones escalables.',
            'Ingeniero con %d años de trayectoria en %s.',
        ];
        
        $languageOptions = [
            ['Español', 'Inglés'],
            ['Español', 'Inglés', 'Portugués'],
            ['Español', 'Inglés', 'Francés'],
            ['Español'],
            ['Español', 'Inglés', 'Italiano'],
        ];
        
        $availabilities = ['available', 'available', 'available', 'available', 'busy', 'busy', 'unavailable'];
        
        for ($i = 0; $i < 150; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            
            // Generate unique valid email
            $baseEmail = strtolower($firstName) . '.' . strtolower($lastName);
            // Remove any non-alphanumeric characters
            $baseEmail = preg_replace('/[^a-z0-9]/i', '', $baseEmail);
            $email = $baseEmail . ($i + 51) . '@devmail.com';
            
            $loc = $locations[array_rand($locations)];
            $expYears = rand(1, 15);
            $hourlyRate = rand(30, 150);
            $availability = $availabilities[array_rand($availabilities)];
            
            $numSkills = rand(3, 6);
            $shuffledSkills = $skillNames;
            shuffle($shuffledSkills);
            $devSkills = array_slice($shuffledSkills, 0, $numSkills);
            
            $headlineTemplate = $headlineTemplates[array_rand($headlineTemplates)];
            $headline = sprintf($headlineTemplate, $devSkills[0], $devSkills[1] ?? $devSkills[0]);
            
            $bioTemplate = $bioTemplates[array_rand($bioTemplates)];
            $bio = sprintf($bioTemplate, $expYears, implode(', ', array_slice($devSkills, 0, 3)));
            
            $langs = $languageOptions[array_rand($languageOptions)];
            
            $user = User::create([
                'name' => $firstName,
                'lastname' => $lastName,
                'email' => $email,
                'password' => 'Demo1234!',
                'user_type' => 'programmer',
                'role' => 'programmer',
            ]);
            
            DB::table('users')->where('id', $user->id)->update(['email_verified_at' => Carbon::now()]);
            
            DeveloperProfile::create([
                'user_id' => $user->id,
                'headline' => $headline,
                'skills' => json_encode($devSkills),
                'bio' => $bio,
                'links' => json_encode([]),
                'location' => $loc['location'],
                'country' => $loc['country'],
                'hourly_rate' => $hourlyRate,
                'availability' => $availability,
                'experience_years' => $expYears,
                'languages' => json_encode($langs),
            ]);
            
            foreach ($devSkills as $skillName) {
                if (isset($skills[$skillName])) {
                    DB::table('developer_skill')->insert([
                        'developer_id' => $user->id,
                        'skill_id' => $skills[$skillName],
                        'proficiency' => rand(2, 5),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }
            }
            
            $developers[] = $user->id;
        }
        
        $this->command->info('   ✓ Total desarrolladores creados: ' . count($developers));
        
        return $developers;
    }

    private function createWallets(array $admins, array $companies, array $developers): void
    {
        $this->command->info('💰 Creando wallets para todos los usuarios...');
        
        // Wallets para admins
        foreach ($admins as $adminId) {
            Wallet::create([
                'user_id' => $adminId,
                'balance' => rand(5000, 50000),
                'held_balance' => 0.00,
            ]);
        }
        
        // Wallets para companies
        foreach ($companies as $index => $companyId) {
            $balance = rand(5000, 50000);
            $heldBalance = rand(1000, 15000);
            
            Wallet::create([
                'user_id' => $companyId,
                'balance' => $balance,
                'held_balance' => $heldBalance,
            ]);
        }
        
        // Wallets para developers
        foreach ($developers as $index => $developerId) {
            $balance = rand(500, 15000);
            
            Wallet::create([
                'user_id' => $developerId,
                'balance' => $balance,
                'held_balance' => 0.00,
            ]);
        }
    }

    private function createPaymentMethods(array $companies, array $developers): void
    {
        $this->command->info('💳 Creando métodos de pago...');
        
        $banks = ['Banco Santander', 'Banco BBVA', 'Banco HSBC', 'Banco Citibank', 'Banco Scotiabank', 'Banco Itaú', 'Banco Chile'];
        $brands = ['Visa', 'Mastercard', 'American Express', 'Discover'];
        
        // Payment methods para companies
        foreach ($companies as $companyId) {
            PaymentMethod::create([
                'user_id' => $companyId,
                'type' => 'bank_account',
                'details' => json_encode([
                    'bank' => $banks[array_rand($banks)],
                    'last_four' => str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                    'holder' => 'Empresa S.A.S.'
                ]),
                'is_default' => true,
            ]);
            
            if (rand(0, 1)) {
                PaymentMethod::create([
                    'user_id' => $companyId,
                    'type' => 'credit_card',
                    'details' => json_encode([
                        'brand' => $brands[array_rand($brands)],
                        'last_four' => str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                        'exp' => rand(1, 12) . '/' . rand(26, 30)
                    ]),
                    'is_default' => false,
                ]);
            }
        }
        
        // Payment methods para developers
        foreach ($developers as $developerId) {
            PaymentMethod::create([
                'user_id' => $developerId,
                'type' => 'paypal',
                'details' => json_encode(['email' => 'dev' . $developerId . '@paypal.com']),
                'is_default' => true,
            ]);
            
            if (rand(0, 1)) {
                PaymentMethod::create([
                    'user_id' => $developerId,
                    'type' => 'bank_account',
                    'details' => json_encode([
                        'bank' => $banks[array_rand($banks)],
                        'last_four' => str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                        'holder' => 'Desarrollador'
                    ]),
                    'is_default' => false,
                ]);
            }
        }
    }

    private function createProjects(array $companies, array $categories, array $skills, array $developers): array
    {
        $this->command->info('📋 Creando 150+ proyectos...');
        
        $projects = [];
        
        // 60 proyectos con datos específicos
        $projectTemplates = [
            ['title' => 'Plataforma E-commerce con React', 'category' => ['Desarrollo Web', 'E-commerce'], 'skills' => ['React', 'Node.js', 'PostgreSQL', 'Stripe']],
            ['title' => 'API REST para App de Delivery', 'category' => ['Backend/APIs', 'Desarrollo Mobile'], 'skills' => ['Node.js', 'PostgreSQL', 'Docker', 'Redis']],
            ['title' => 'Diseño UI/UX para App de Fitness', 'category' => ['UI/UX Design', 'Desarrollo Mobile'], 'skills' => ['Figma', 'React Native', 'Swift']],
            ['title' => 'Sistema ERP Empresarial', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['Laravel', 'Vue.js', 'MySQL', 'Docker']],
            ['title' => 'Chatbot con IA para Soporte', 'category' => ['AI/ML', 'Backend/APIs'], 'skills' => ['Python', 'Node.js', 'TensorFlow', 'MongoDB']],
            ['title' => 'Dashboard de Analytics en Tiempo Real', 'category' => ['Desarrollo Web', 'Data Science'], 'skills' => ['React', 'D3.js', 'PostgreSQL', 'WebSockets']],
            ['title' => 'App de Reservas de Restaurantes', 'category' => ['Desarrollo Mobile', 'Desarrollo Web'], 'skills' => ['Flutter', 'Node.js', 'MongoDB', 'Google Maps']],
            ['title' => 'Plataforma de Gestión de Inventarios', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['React', 'Python', 'PostgreSQL', 'AWS']],
            ['title' => 'Sistema de Tracking GPS para Flotas', 'category' => ['Desarrollo Mobile', 'Backend/APIs'], 'skills' => ['React Native', 'Node.js', 'PostgreSQL', 'Google Maps API']],
            ['title' => 'Portal de Telemedicina', 'category' => ['Desarrollo Web', 'Desarrollo Mobile'], 'skills' => ['React', 'Laravel', 'MySQL', 'WebRTC']],
            ['title' => 'Marketplace de Servicios Freelance', 'category' => ['Desarrollo Web', 'E-commerce'], 'skills' => ['Vue.js', 'Node.js', 'MongoDB', 'Stripe']],
            ['title' => 'Sistema de Gestión de Documentos', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['React', 'Python', 'PostgreSQL', 'AWS S3']],
            ['title' => 'App de Educación Interactiva', 'category' => ['Desarrollo Mobile', 'UI/UX Design'], 'skills' => ['Flutter', 'Firebase', 'React', 'Node.js']],
            ['title' => 'Plataforma de Crowdfunding', 'category' => ['Desarrollo Web', 'E-commerce'], 'skills' => ['Laravel', 'Vue.js', 'MySQL', 'PayPal']],
            ['title' => 'Sistema CRM Personalizado', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['React', 'Node.js', 'PostgreSQL', 'GraphQL']],
            ['title' => 'App de Delivery con IA', 'category' => ['Desarrollo Mobile', 'AI/ML'], 'skills' => ['React Native', 'Python', 'TensorFlow', 'PostgreSQL']],
            ['title' => 'Plataforma de Gestión de Eventos', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['Next.js', 'Node.js', 'MongoDB', 'Stripe']],
            ['title' => 'Sistema de Facturación Electrónica', 'category' => ['Backend/APIs', 'Desarrollo Web'], 'skills' => ['PHP', 'Laravel', 'MySQL', 'PDF']],
            ['title' => 'Red Social para Profesionales', 'category' => ['Desarrollo Web', 'Desarrollo Mobile'], 'skills' => ['React', 'Node.js', 'PostgreSQL', 'Redis']],
            ['title' => 'Plataforma de Video Conferencias', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['React', 'Node.js', 'WebRTC', 'AWS']],
            ['title' => 'Sistema de Control de Acceso IoT', 'category' => ['Backend/APIs', 'DevOps'], 'skills' => ['Python', 'Node.js', 'AWS IoT', 'Docker']],
            ['title' => 'Plataforma de Aprendizaje Automático', 'category' => ['AI/ML', 'Data Science'], 'skills' => ['Python', 'TensorFlow', 'AWS', 'PostgreSQL']],
            ['title' => 'Dashboard de Business Intelligence', 'category' => ['Data Science', 'Desarrollo Web'], 'skills' => ['Python', 'React', 'PostgreSQL', 'D3.js']],
            ['title' => 'Sistema de Recomendaciones', 'category' => ['AI/ML', 'Backend/APIs'], 'skills' => ['Python', 'Node.js', 'MongoDB', 'Redis']],
            ['title' => 'App de Finanzas Personales', 'category' => ['Desarrollo Mobile', 'Desarrollo Web'], 'skills' => ['React Native', 'Node.js', 'PostgreSQL', 'Charts']],
            ['title' => 'Plataforma de Blogs with CMS', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['Next.js', 'Node.js', 'MongoDB', 'AWS S3']],
            ['title' => 'Sistema de Gestión de Proyectos', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['Vue.js', 'Laravel', 'MySQL', 'Docker']],
            ['title' => 'Marketplace de Productos Digitales', 'category' => ['Desarrollo Web', 'E-commerce'], 'skills' => ['React', 'Node.js', 'MongoDB', 'Stripe']],
            ['title' => 'App de Seguimiento de Hábitos', 'category' => ['Desarrollo Mobile', 'UI/UX Design'], 'skills' => ['Swift', 'iOS', 'CoreData', 'HealthKit']],
            ['title' => 'Plataforma de Subastas Online', 'category' => ['Desarrollo Web', 'E-commerce'], 'skills' => ['React', 'Node.js', 'PostgreSQL', 'WebSockets']],
            ['title' => 'Sistema de Reservas Hotel', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['Laravel', 'Vue.js', 'MySQL', 'Calendar API']],
            ['title' => 'App de Control de Gastos', 'category' => ['Desarrollo Mobile', 'Desarrollo Web'], 'skills' => ['Flutter', 'Firebase', 'Dart', 'Charts']],
            ['title' => 'Plataforma de Membresías', 'category' => ['Desarrollo Web', 'E-commerce'], 'skills' => ['Next.js', 'Stripe', 'PostgreSQL', 'AWS']],
            ['title' => 'Sistema de Gestión de Taller', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['PHP', 'Laravel', 'MySQL', 'Bootstrap']],
            ['title' => 'App de Recipes & Meal Planning', 'category' => ['Desarrollo Mobile', 'UI/UX Design'], 'skills' => ['React Native', 'Firebase', 'Figma', 'Nutritional API']],
            ['title' => 'Plataforma de Encuestas Online', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['Vue.js', 'Node.js', 'MongoDB', 'Chart.js']],
            ['title' => 'Sistema de Booking de Citas', 'category' => ['Desarrollo Web', 'Desarrollo Mobile'], 'skills' => ['React', 'Node.js', 'PostgreSQL', 'Twilio']],
            ['title' => 'App de Meditation & Sleep', 'category' => ['Desarrollo Mobile', 'UI/UX Design'], 'skills' => ['Flutter', 'Firebase', 'Audio API', 'HealthKit']],
            ['title' => 'Plataforma de Donaciones', 'category' => ['Desarrollo Web', 'E-commerce'], 'skills' => ['React', 'Node.js', 'PostgreSQL', 'Stripe']],
            ['title' => 'Sistema de Inventario con QR', 'category' => ['Backend/APIs', 'Desarrollo Mobile'], 'skills' => ['Python', 'React Native', 'PostgreSQL', 'QR Scanner']],
            ['title' => 'App de Travel Planner', 'category' => ['Desarrollo Mobile', 'UI/UX Design'], 'skills' => ['Swift', 'iOS', 'MapKit', 'REST APIs']],
            ['title' => 'Plataforma de Cursos Online', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['Laravel', 'Vue.js', 'MySQL', 'Video Streaming']],
            ['title' => 'Sistema de Gestión de Biblioteca', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['Django', 'Python', 'PostgreSQL', 'ISBN API']],
            ['title' => 'App de Pet Care & Tracking', 'category' => ['Desarrollo Mobile', 'UI/UX Design'], 'skills' => ['Kotlin', 'Android', 'Firebase', 'Maps API']],
            ['title' => 'Plataforma de Freelancers', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['Next.js', 'Node.js', 'MongoDB', 'Stripe Connect']],
            ['title' => 'Sistema de Punto de Venta', 'category' => ['Backend/APIs', 'Desarrollo Web'], 'skills' => ['Electron', 'Node.js', 'SQLite', 'Receipt Printer']],
            ['title' => 'App de Workout & Fitness', 'category' => ['Desarrollo Mobile', 'Desarrollo Web'], 'skills' => ['Flutter', 'Firebase', 'Health APIs', 'Charts']],
            ['title' => 'Plataforma de Newsletter', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['Node.js', 'React', 'MongoDB', 'Mailgun']],
            ['title' => 'Sistema de Gestión de Gimnasio', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['PHP', 'Laravel', 'MySQL', 'SMS API']],
            ['title' => 'App de Task Management', 'category' => ['Desarrollo Mobile', 'Desarrollo Web'], 'skills' => ['React Native', 'Firebase', 'Redux', 'Notifications']],
            ['title' => 'Plataforma de Reviews & Ratings', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['Vue.js', 'Node.js', 'PostgreSQL', 'ElasticSearch']],
            ['title' => 'Sistema de Alquiler de Autos', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['React', 'Node.js', 'MongoDB', 'Maps API']],
            ['title' => 'App de Music Streaming', 'category' => ['Desarrollo Mobile', 'UI/UX Design'], 'skills' => ['Swift', 'iOS', 'Audio API', 'CloudKit']],
            ['title' => 'Plataforma de Foros & Comunidad', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['Discourse', 'Ruby', 'PostgreSQL', 'Redis']],
            ['title' => 'Sistema de Payroll & Nóminas', 'category' => ['Backend/APIs', 'Desarrollo Web'], 'skills' => ['Java', 'Spring Boot', 'PostgreSQL', 'PDF Generator']],
            ['title' => 'App de Real Estate & Propiedades', 'category' => ['Desarrollo Mobile', 'Desarrollo Web'], 'skills' => ['Flutter', 'Firebase', 'Maps API', 'Image Upload']],
            ['title' => 'Plataforma de Subscripciones', 'category' => ['Desarrollo Web', 'E-commerce'], 'skills' => ['Next.js', 'Stripe Subscriptions', 'PostgreSQL', 'Webhooks']],
            ['title' => 'Sistema de Tickets & Soporte', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['React', 'Node.js', 'MongoDB', 'Email Service']],
            ['title' => 'App de Weather & Climate', 'category' => ['Desarrollo Mobile', 'UI/UX Design'], 'skills' => ['Kotlin', 'Android', 'Weather API', 'Widgets']],
            ['title' => 'Plataforma de Podcast Hosting', 'category' => ['Desarrollo Web', 'Backend/APIs'], 'skills' => ['Node.js', 'React', 'AWS S3', 'Audio Processing']],
        ];
        
        $levels = ['entry', 'mid', 'senior', 'lead'];
        $priorities = ['low', 'medium', 'high', 'urgent'];
        $statuses = ['open', 'open', 'open', 'in_progress', 'in_progress', 'completed', 'completed', 'draft', 'pending_payment', 'cancelled'];
        
        $categoryNames = array_keys($categories);
        
        for ($i = 0; $i < 60; $i++) {
            $template = $projectTemplates[$i % count($projectTemplates)];
            $companyId = $companies[array_rand($companies)];
            $status = $statuses[array_rand($statuses)];
            
            $budgetMin = rand(2000, 25000);
            $budgetMax = $budgetMin + rand(2000, 15000);
            
            $project = Project::create([
                'company_id' => $companyId,
                'title' => $template['title'] . ' #' . ($i + 1),
                'description' => 'Desarrollo completo de ' . strtolower($template['title']) . ' con las últimas tecnologías. Incluye frontend moderno, backend escalable y panel de administración.',
                'budget_min' => $budgetMin,
                'budget_max' => $budgetMax,
                'budget_type' => rand(0, 1) ? 'fixed' : 'hourly',
                'duration_value' => rand(1, 6),
                'duration_unit' => rand(0, 1) ? 'weeks' : 'months',
                'location' => null,
                'remote' => rand(0, 4) > 0,
                'level' => $levels[array_rand($levels)],
                'priority' => $priorities[array_rand($priorities)],
                'featured' => rand(0, 7) === 0,
                'deadline' => in_array($status, ['completed', 'cancelled']) ? Carbon::now()->subWeeks(rand(1, 12)) : Carbon::now()->addWeeks(rand(2, 16)),
                'max_applicants' => rand(8, 30),
                'tags' => json_encode(array_slice($template['skills'], 0, 3)),
                'status' => $status,
            ]);
            
            $projectData = ['id' => $project->id, 'status' => $status];
            
            if (in_array($status, ['in_progress', 'completed', 'pending_payment'])) {
                $devId = $developers[array_rand($developers)];
                $projectData['developer_id'] = $devId;
            }
            
            $projects[$i + 1] = $projectData;
            
            // Categories
            foreach ($template['category'] as $catName) {
                if (isset($categories[$catName])) {
                    DB::table('project_category_project')->insert([
                        'project_id' => $project->id,
                        'project_category_id' => $categories[$catName],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }
            }
            
            // Skills
            foreach ($template['skills'] as $skillName) {
                if (isset($skills[$skillName])) {
                    DB::table('project_skill')->insert([
                        'project_id' => $project->id,
                        'skill_id' => $skills[$skillName],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }
            }
        }
        
        // 90 proyectos adicionales generados
        $this->command->info('   Generando 90 proyectos adicionales...');
        
        $additionalTitles = [
            'Plataforma de Gestión de Proyectos', 'App de Comidas a Domicilio', 'Sistema de Citas Médicas',
            'Plataforma de Aprendizaje Online', 'App de Seguimiento de Proyectos', 'Sistema de Reservas de Vuelos',
            'Plataforma de Donativos', 'App de Control de Inventario', 'Sistema de Gestión de Hotels',
            'Plataforma de Marketplace B2B', 'App de Fitness Social', 'Sistema de Encuestas y Votaciones',
            'Plataforma de Blogs Profesionales', 'App de Marketplace de Servicios', 'Sistema de Gestión de RRHH',
            'Plataforma de Reservas de Restaurantes', 'App de Carpooling', 'Sistema de Tickets de Soporte',
            'Plataforma de Membresías Premium', 'App de Marketplace de Productos', 'Sistema de Facturación',
            'Plataforma de Eventos Virtuales', 'App de Entregas', 'Sistema de Seguimiento de Flotas',
            'Plataforma de Subscripciones', 'App de Cuidado de Mascotas', 'Sistema de Inventario',
            'Plataforma de Aprendizaje', 'App de Viajes', 'Sistema de Reservas',
            'Plataforma de Reviews', 'App de Finanzas', 'Sistema de Pagos',
            'Plataforma de Comunidades', 'App de Productividad', 'Sistema de CRM',
            'Plataforma de Contenido', 'App de Streaming', 'Sistema de Analytics',
            'Plataforma de Social Media', 'App de Networking', 'Sistema de E-commerce',
            'Plataforma de Education', 'App de Health', 'Sistema de Booking',
            'Plataforma de Marketplace', 'App de Commerce', 'Sistema de Management',
            'Plataforma de Services', 'App de Logistics', 'Systema de Delivery',
        ];
        
        for ($i = 60; $i < 150; $i++) {
            $companyId = $companies[array_rand($companies)];
            $status = $statuses[array_rand($statuses)];
            
            $budgetMin = rand(2000, 20000);
            $budgetMax = $budgetMin + rand(2000, 12000);
            
            $shuffledCats = $categoryNames;
            shuffle($shuffledCats);
            $projCats = array_slice($shuffledCats, 0, rand(1, 2));
            
            $skillKeys = array_keys($skills);
            shuffle($skillKeys);
            $projSkills = array_slice($skillKeys, 0, rand(3, 5));
            
            $project = Project::create([
                'company_id' => $companyId,
                'title' => $additionalTitles[($i - 60) % count($additionalTitles)],
                'description' => 'Proyecto de desarrollo de software con tecnologías modernas. Se requiere profesional experimentado.',
                'budget_min' => $budgetMin,
                'budget_max' => $budgetMax,
                'budget_type' => rand(0, 1) ? 'fixed' : 'hourly',
                'duration_value' => rand(1, 6),
                'duration_unit' => rand(0, 1) ? 'weeks' : 'months',
                'location' => null,
                'remote' => rand(0, 4) > 0,
                'level' => $levels[array_rand($levels)],
                'priority' => $priorities[array_rand($priorities)],
                'featured' => rand(0, 10) === 0,
                'deadline' => in_array($status, ['completed', 'cancelled']) ? Carbon::now()->subWeeks(rand(1, 10)) : Carbon::now()->addWeeks(rand(2, 14)),
                'max_applicants' => rand(5, 25),
                'tags' => json_encode(array_slice($projSkills, 0, 3)),
                'status' => $status,
            ]);
            
            $projectData = ['id' => $project->id, 'status' => $status];
            
            if (in_array($status, ['in_progress', 'completed', 'pending_payment'])) {
                $devId = $developers[array_rand($developers)];
                $projectData['developer_id'] = $devId;
            }
            
            $projects[$i + 1] = $projectData;
            
            foreach ($projCats as $catName) {
                if (isset($categories[$catName])) {
                    DB::table('project_category_project')->insert([
                        'project_id' => $project->id,
                        'project_category_id' => $categories[$catName],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }
            }
            
            foreach ($projSkills as $skillName) {
                if (isset($skills[$skillName])) {
                    DB::table('project_skill')->insert([
                        'project_id' => $project->id,
                        'skill_id' => $skills[$skillName],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }
            }
        }
        
        $this->command->info('   ✓ Total proyectos creados: ' . count($projects));
        
        return $projects;
    }

    private function createApplications(array $projects, array $developers): void
    {
        $this->command->info('📨 Creando aplicaciones...');
        
        $coverLetters = [
            "Hola, me interesa mucho este proyecto. Tengo experiencia directa con las tecnologías requeridas y he trabajado en proyectos similares.",
            "Buenos días. He revisado los requisitos del proyecto y creo que mi perfil es ideal. Puedo comenzar de inmediato.",
            "¡Hola! Este proyecto me parece muy interesante. Cuento con años de experiencia en las tecnologías mencionadas.",
            "Estimado cliente. He analizado su proyecto y estoy muy motivado para participar.",
            "Me interesa participar en este proyecto. He desarrollado aplicaciones similares y puedo aportar valor desde el primer día.",
            "Después de revisar sus requisitos, creo que soy un excelente candidato para este proyecto.",
            "¡Hola! Este proyecto es exactamente lo que busco. Puedo entregar resultados de alta calidad.",
            "Saludos. Me especializo en proyectos similares y puedo ofrecerles una solución robusta y escalable.",
            "He revisado detalladamente los requisitos y estoy seguro de poder completar el proyecto con éxito.",
            "Buen día. Me encantaría formar parte de este proyecto. Mi enfoque en la calidad se ajusta a lo que buscan.",
        ];
        
        $statuses = ['pending', 'sent', 'reviewed', 'accepted', 'rejected'];
        
        foreach ($projects as $projectNum => $project) {
            $projectModel = Project::find($project['id']);
            if (!$projectModel) continue;
            
            $numApplications = rand(3, 8);
            $availableDevelopers = $developers;
            shuffle($availableDevelopers);
            
            // Skip if project already has a developer assigned
            $assignedDeveloperId = isset($project['developer_id']) ? $project['developer_id'] : null;
            
            // Create accepted application for projects with assigned developer
            if ($assignedDeveloperId) {
                Application::create([
                    'project_id' => $project['id'],
                    'developer_id' => $assignedDeveloperId,
                    'cover_letter' => $coverLetters[array_rand($coverLetters)],
                    'status' => 'accepted',
                ]);
            }
            
            for ($j = 0; $j < $numApplications; $j++) {
                $developerId = $availableDevelopers[$j];
                
                // Skip if this developer is already assigned to this project
                if ($developerId === $assignedDeveloperId) continue;
                
                // Determine status
                if ($j === 0 && isset($project['developer_id'])) {
                    // First application gets accepted if project has developer
                    $status = 'accepted';
                } elseif ($assignedDeveloperId && $j < 3) {
                    $status = 'rejected';
                } elseif ($projectModel->status === 'open') {
                    $status = rand(0, 1) ? 'pending' : 'sent';
                } else {
                    $status = $statuses[array_rand($statuses)];
                }
                
                Application::create([
                    'project_id' => $project['id'],
                    'developer_id' => $developerId,
                    'cover_letter' => $coverLetters[array_rand($coverLetters)],
                    'status' => $status,
                ]);
            }
        }
    }

    private function createMilestones(array $projects): void
    {
        $this->command->info('🎯 Creando milestones...');
        
        $milestoneTitles = [
            'Investigación y Análisis', 'Diseño de Base de Datos', 'Prototipado UI/UX',
            'Desarrollo del Backend', 'Integración de API', 'Desarrollo del Frontend',
            'Pruebas Unitarias', 'Pruebas de Integración', 'Despliegue a Staging',
            'Corrección de Bugs', 'Optimización de Rendimiento', 'Entrega Final'
        ];
        
        foreach ($projects as $projectNum => $project) {
            $projectModel = Project::find($project['id']);
            if (!$projectModel || $project['status'] === 'draft') continue;
            
            $milestoneCount = rand(3, 6);
            $budgetPerMilestone = $projectModel->budget_max / $milestoneCount;
            
            $completedMilestones = 0;
            if ($project['status'] === 'completed') {
                $completedMilestones = $milestoneCount;
            } elseif ($project['status'] === 'in_progress') {
                $completedMilestones = rand(1, $milestoneCount - 1);
            } elseif ($project['status'] === 'pending_payment') {
                $completedMilestones = $milestoneCount;
            }
            
            for ($i = 1; $i <= $milestoneCount; $i++) {
                $milestoneStatus = 'pending';
                $progressStatus = 'todo';
                
                if ($i <= $completedMilestones) {
                    $milestoneStatus = 'released';
                    $progressStatus = 'completed';
                } elseif ($i === $completedMilestones + 1 && $project['status'] === 'in_progress') {
                    $milestoneStatus = 'funded';
                    $progressStatus = 'in_progress';
                } elseif ($project['status'] === 'pending_payment') {
                    $milestoneStatus = 'funded';
                    $progressStatus = 'completed';
                }
                
                Milestone::create([
                    'project_id' => $project['id'],
                    'title' => "Hito $i: " . $milestoneTitles[array_rand($milestoneTitles)],
                    'description' => 'Descripción del milestone ' . $i . ' del proyecto.',
                    'amount' => $budgetPerMilestone,
                    'status' => $milestoneStatus,
                    'progress_status' => $progressStatus,
                    'order' => $i,
                    'due_date' => Carbon::now()->addDays(rand(7, 30)),
                    'deliverables' => $progressStatus === 'completed' ? json_encode(['Entregable 1', 'Entregable 2']) : null,
                ]);
            }
        }
    }

    private function createConversationsAndMessages(array $projects, array $developers): void
    {
        $this->command->info('💬 Creando conversaciones y mensajes...');
        
        $messageTemplates = [
            'Hola, me interesa tu perfil para este proyecto.',
            '¿Tienes disponibilidad para iniciar pronto?',
            'He revisado tu portafolio y me parece excelente.',
            'Podemos agendar una llamada para discutir los detalles.',
            '¿Tienes experiencia con las tecnologías requeridas?',
            'El presupuesto está sujeto a negociación.',
            'Necesitamos comenzar lo antes posible.',
            'Tengo más detalles sobre el proyecto.',
            '¿Cuál es tu disponibilidad esta semana?',
            'Excelente, quedamos en contacto.',
        ];
        
        $conversationCount = 0;
        
        foreach ($projects as $projectNum => $project) {
            if (!isset($project['developer_id'])) continue;
            
            $projectModel = Project::find($project['id']);
            if (!$projectModel) continue;
            
            $companyId = $projectModel->company_id;
            $developerId = $project['developer_id'];
            
            // Create conversation
            $conversation = Conversation::create([
                'project_id' => $project['id'],
                'type' => 'project',
                'initiator_id' => $companyId,
                'participant_id' => $developerId,
            ]);
            
            $conversationCount++;
            
            // Create messages
            $numMessages = rand(4, 10);
            $msgTime = Carbon::now()->subDays(rand(1, 30));
            
            for ($m = 0; $m < $numMessages; $m++) {
                $senderId = ($m % 2 === 0) ? $companyId : $developerId;
                
                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $senderId,
                    'content' => $messageTemplates[array_rand($messageTemplates)],
                    'type' => 'text',
                    'is_read' => rand(0, 1) === 1,
                ]);
                
                $msgTime = $msgTime->addHours(rand(1, 48));
            }
        }
        
        // Create some direct conversations
        for ($i = 0; $i < 30; $i++) {
            $companyId = $developers[array_rand($developers)];
            $developerId = $developers[array_rand($developers)];
            
            if ($companyId === $developerId) continue;
            
            $conversation = Conversation::create([
                'project_id' => null,
                'type' => 'direct',
                'initiator_id' => $companyId,
                'participant_id' => $developerId,
            ]);
            
            for ($m = 0; $m < rand(2, 5); $m++) {
                $senderId = ($m % 2 === 0) ? $companyId : $developerId;
                
                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $senderId,
                    'content' => $messageTemplates[array_rand($messageTemplates)],
                    'type' => 'text',
                    'is_read' => rand(0, 1) === 1,
                ]);
            }
        }
    }

    private function createReviews(array $projects): void
    {
        $this->command->info('⭐ Creando reviews...');
        
        $reviewComments = [
            'Excelente profesional, entregó todo a tiempo y con gran calidad.',
            'Muy buena comunicación y disposición para resolver problemas.',
            'El código es limpio y bien estructurado. Recomendado 100%.',
            'Hubo algunos retrasos pero el resultado final fue satisfactorio.',
            'Gran experiencia trabajando juntos, esperamos colaborar nuevamente.',
            'Superó nuestras expectativas en cuanto a funcionalidad y diseño.',
            'Profesional muy competente, lo recomiendo.',
            'Trabajo de alta calidad, muy satisfecho con el resultado.',
            'Buena experiencia, cumplió con todos los requisitos.',
            'Excelente trabajo, sin duda volvería a contratar.',
        ];
        
        foreach ($projects as $projectNum => $project) {
            if ($project['status'] !== 'completed' || !isset($project['developer_id'])) continue;
            
            $projectModel = Project::find($project['id']);
            if (!$projectModel) continue;
            
            Review::create([
                'project_id' => $project['id'],
                'company_id' => $projectModel->company_id,
                'developer_id' => $project['developer_id'],
                'rating' => rand(3, 5),
                'comment' => $reviewComments[array_rand($reviewComments)],
            ]);
        }
    }

    private function createTransactions(array $projects, array $admins): void
    {
        $this->command->info('💵 Creando transacciones financieras...');
        
        $adminWallet = Wallet::where('user_id', $admins[0])->first();
        
        foreach ($projects as $projectNum => $project) {
            $projectModel = Project::find($project['id']);
            if (!$projectModel) continue;
            
            $companyId = $projectModel->company_id;
            $companyWallet = Wallet::where('user_id', $companyId)->first();
            if (!$companyWallet) continue;
            
            // Deposit
            $depositAmount = $projectModel->budget_min * (in_array($project['status'], ['completed', 'pending_payment']) ? 1 : 0.5);
            
            Transaction::create([
                'wallet_id' => $companyWallet->id,
                'amount' => $depositAmount,
                'type' => 'deposit',
                'description' => 'Depósito para proyecto: ' . $projectModel->title,
                'reference_type' => 'App\Models\Project',
                'reference_id' => $project['id'],
            ]);
            
            $companyWallet->balance += $depositAmount;
            $companyWallet->save();
            
            // Create escrow deposits for milestones
            $milestones = Milestone::where('project_id', $project['id'])->whereIn('status', ['funded', 'released'])->get();
            
            foreach ($milestones as $milestone) {
                Transaction::create([
                    'wallet_id' => $companyWallet->id,
                    'amount' => -$milestone->amount,
                    'type' => 'escrow_deposit',
                    'description' => 'Depósito en escrow para milestone: ' . $milestone->title,
                    'reference_type' => 'App\Models\Milestone',
                    'reference_id' => $milestone->id,
                ]);
                
                $companyWallet->held_balance += $milestone->amount;
                $companyWallet->balance -= $milestone->amount;
                $companyWallet->save();
                
                // If milestone is released, process payment to developer
                if ($milestone->status === 'released' && isset($project['developer_id'])) {
                    $developerWallet = Wallet::where('user_id', $project['developer_id'])->first();
                    if ($developerWallet) {
                        $developerAmount = $milestone->amount * 0.90;
                        $commissionAmount = $milestone->amount * 0.10;
                        
                        // Payment to developer
                        Transaction::create([
                            'wallet_id' => $developerWallet->id,
                            'amount' => $developerAmount,
                            'type' => 'payment_received',
                            'description' => 'Pago por milestone: ' . $milestone->title,
                            'reference_type' => 'App\Models\Milestone',
                            'reference_id' => $milestone->id,
                        ]);
                        
                        $developerWallet->balance += $developerAmount;
                        $developerWallet->save();
                        
                        // Commission to admin
                        if ($adminWallet) {
                            Transaction::create([
                                'wallet_id' => $adminWallet->id,
                                'amount' => $commissionAmount,
                                'type' => 'commission',
                                'description' => 'Comisión del proyecto: ' . $projectModel->title,
                                'reference_type' => 'App\Models\Project',
                                'reference_id' => $project['id'],
                            ]);
                            
                            $adminWallet->balance += $commissionAmount;
                            $adminWallet->save();
                        }
                        
                        // Release escrow
                        Transaction::create([
                            'wallet_id' => $companyWallet->id,
                            'amount' => 0,
                            'type' => 'escrow_release',
                            'description' => 'Liberación de escrow para milestone: ' . $milestone->title,
                            'reference_type' => 'App\Models\Milestone',
                            'reference_id' => $milestone->id,
                        ]);
                        
                        $companyWallet->held_balance -= $milestone->amount;
                        $companyWallet->save();
                    }
                }
            }
        }
    }

    private function createPortfolios(array $developers): void
    {
        $this->command->info('🎨 Creando portfolios...');
        
        $portfolioTitles = [
            'Plataforma E-commerce Enterprise',
            'Sistema de Gestión de Proyectos',
            'App de Delivery con IA',
            'Dashboard de Analytics',
            'Plataforma de E-learning',
            'API REST de Alto Rendimiento',
            'App Mobile de Finanzas',
            'Sistema de Reservas',
            'Plataforma de Marketplace',
            'Dashboard IoT',
        ];
        
        $technologies = [
            ['React', 'Node.js', 'PostgreSQL', 'AWS'],
            ['Vue.js', 'Laravel', 'MySQL', 'Docker'],
            ['Flutter', 'Firebase', 'Google Maps'],
            ['React Native', 'Node.js', 'MongoDB'],
            ['Next.js', 'TypeScript', 'TailwindCSS'],
            ['Angular', 'Spring Boot', 'PostgreSQL'],
            ['Django', 'Python', 'AWS', 'Redis'],
            ['React', 'Node.js', 'GraphQL', 'Apollo'],
            ['Flutter', 'Firebase', 'Stripe'],
            ['Swift', 'iOS', 'CoreData'],
        ];
        
        // Create portfolios for first 60 developers
        for ($i = 0; $i < min(60, count($developers)); $i++) {
            $developerId = $developers[$i];
            
            // 0-3 portfolio projects per developer
            $numPortfolios = rand(0, 3);
            
            for ($p = 0; $p < $numPortfolios; $p++) {
                $techIndex = rand(0, count($technologies) - 1);
                
                PortfolioProject::create([
                    'user_id' => $developerId,
                    'title' => $portfolioTitles[rand(0, count($portfolioTitles) - 1)] . ' Project',
                    'description' => 'Proyecto de desarrollo completo con funcionalidades avanzadas y diseño moderno.',
                    'project_url' => rand(0, 1) ? 'https://project-demo.com' : null,
                    'github_url' => rand(0, 1) ? 'https://github.com/user/project' : null,
                    'technologies' => json_encode($technologies[$techIndex]),
                    'completion_date' => Carbon::now()->subMonths(rand(1, 12))->format('F Y'),
                    'client' => rand(0, 1) ? 'Cliente ' . rand(1, 50) : null,
                    'featured' => rand(0, 3) === 0,
                    'views' => rand(50, 500),
                    'likes' => rand(10, 100),
                ]);
            }
        }
    }

    private function createFavorites(array $companies, array $developers): void
    {
        $this->command->info('❤️ Creando favoritos...');
        
        // Each company favorites 5-15 developers
        foreach ($companies as $companyId) {
            $numFavorites = rand(5, 15);
            $favoritedDevs = [];
            
            for ($i = 0; $i < $numFavorites; $i++) {
                $developerId = $developers[array_rand($developers)];
                
                if (!in_array($developerId, $favoritedDevs)) {
                    $favoritedDevs[] = $developerId;
                    
                    Favorite::firstOrCreate([
                        'company_id' => $companyId,
                        'developer_id' => $developerId,
                    ]);
                }
            }
        }
    }

    private function createUserPreferences(array $users): void
    {
        $this->command->info('⚙️ Creando preferencias de usuario...');
        
        $themes = ['dark', 'light', 'terminal'];
        $languages = ['es', 'en', 'pt'];
        $colors = ['#00FF85', '#3B82F6', '#10B981', '#8B5CF6', '#F59E0B', '#EF4444'];
        
        foreach ($users as $userId) {
            UserPreference::create([
                'user_id' => $userId,
                'theme' => $themes[array_rand($themes)],
                'language' => $languages[array_rand($languages)],
                'accent_color' => $colors[array_rand($colors)],
                'two_factor_enabled' => rand(0, 1) === 1,
            ]);
        }
    }

    private function createActivityLogs(array $admins, array $companies, array $developers, array $projects): void
    {
        $this->command->info('📝 Creando logs de actividad (mapa de calor)...');
        
        $allUsers = array_merge($admins, $companies, $developers);
        
        $actions = [
            'login', 'logout', 'profile_view', 'profile_update', 'project_created',
            'project_updated', 'project_viewed', 'application_sent', 'application_accepted',
            'application_rejected', 'milestone_completed', 'payment_received', 'message_sent',
            'conversation_started', 'review_created', 'favorite_added', 'portfolio_updated'
        ];
        
        $ips = [
            '192.168.1.10', '192.168.1.15', '10.0.0.5', '10.0.0.12',
            '181.50.10.5', '181.70.20.15', '200.50.30.25', '45.33.32.156',
            '89.216.48.10', '190.190.200.5'
        ];
        
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64) Firefox/121.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Edge/120.0.0.0',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1',
        ];
        
        // Generate many activity logs for heatmap
        $logCount = 0;
        
        foreach ($allUsers as $userId) {
            // 10-30 activities per user for heatmap
            $numActivities = rand(10, 30);
            
            for ($i = 0; $i < $numActivities; $i++) {
                $action = $actions[array_rand($actions)];
                $daysAgo = rand(0, 60);
                
                // Peak hours: 70% chance to be in peak hours
                if (rand(1, 100) <= 70) {
                    $hour = rand(1, 2) == 1 ? rand(9, 13) : rand(14, 18);
                } else {
                    $hour = rand(0, 23);
                }
                
                $createdAt = Carbon::now()->subDays($daysAgo)->setTime($hour, rand(0, 59), rand(0, 59));
                
                ActivityLog::create([
                    'user_id' => $userId,
                    'action' => $action,
                    'details' => "Acción: $action realizada por usuario",
                    'ip_address' => $ips[array_rand($ips)],
                    'user_agent' => $userAgents[array_rand($userAgents)],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
                
                $logCount++;
            }
        }
        
        $this->command->info('   ✓ Total logs de actividad creados: ' . $logCount);
    }

    private function createSystemSettings(): void
    {
        $this->command->info('🔧 Creando settings del sistema...');
        
        $settings = [
            ['key' => 'commission_rate', 'value' => '10', 'type' => 'integer', 'group' => 'marketplace'],
            ['key' => 'platform_name', 'value' => 'Programmers', 'type' => 'string', 'group' => 'general'],
            ['key' => 'platform_email', 'value' => 'info@programmers.com', 'type' => 'string', 'group' => 'general'],
            ['key' => 'min_project_budget', 'value' => '100', 'type' => 'integer', 'group' => 'marketplace'],
            ['key' => 'max_project_budget', 'value' => '100000', 'type' => 'integer', 'group' => 'marketplace'],
            ['key' => 'max_applications_per_project', 'value' => '50', 'type' => 'integer', 'group' => 'marketplace'],
            ['key' => 'allow_registration', 'value' => 'true', 'type' => 'boolean', 'group' => 'general'],
            ['key' => 'maintenance_mode', 'value' => 'false', 'type' => 'boolean', 'group' => 'general'],
            ['key' => 'default_currency', 'value' => 'USD', 'type' => 'string', 'group' => 'marketplace'],
            ['key' => 'escrow_enabled', 'value' => 'true', 'type' => 'boolean', 'group' => 'marketplace'],
            ['key' => 'min_withdrawal_amount', 'value' => '50', 'type' => 'integer', 'group' => 'marketplace'],
            ['key' => 'payment_processing_fee', 'value' => '2.9', 'type' => 'string', 'group' => 'marketplace'],
        ];
        
        foreach ($settings as $setting) {
            SystemSetting::create($setting);
        }
    }

    private function spreadTimestamps(): void
    {
        $this->command->info('📅 Distribuyendo timestamps para métricas del dashboard (mapa de calor)...');
        
        // Users: last 90 days
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            $date = Carbon::now()->subDays(rand(0, 90))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
            DB::table('users')->where('id', $user->id)->update([
                'created_at' => $date,
                'updated_at' => clone $date,
            ]);
        }
        
        // Projects: last 90 days
        $projects = DB::table('projects')->get();
        foreach ($projects as $project) {
            $created_at = Carbon::now()->subDays(rand(10, 90))->subHours(rand(0, 23));
            $updated_at = clone $created_at;
            if ($project->status === 'completed') {
                $updated_at = (clone $created_at)->addDays(rand(5, 45));
                if ($updated_at > Carbon::now()) $updated_at = Carbon::now();
            }
            DB::table('projects')->where('id', $project->id)->update([
                'created_at' => $created_at,
                'updated_at' => $updated_at,
            ]);
        }
        
        // Applications: 0 - 60 days
        $applications = DB::table('applications')->get();
        foreach ($applications as $app) {
            $date = Carbon::now()->subDays(rand(0, 60))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
            DB::table('applications')->where('id', $app->id)->update([
                'created_at' => $date,
                'updated_at' => clone $date,
            ]);
        }
        
        // Messages: 0 - 60 days with peak hours
        $messages = DB::table('messages')->get();
        foreach ($messages as $msg) {
            $daysAgo = rand(0, 60);
            // Peak hours logic: 70% chance to be in peak hours (9-13 or 14-18)
            if (rand(1, 100) <= 70) {
                $hour = rand(1, 2) == 1 ? rand(9, 13) : rand(14, 18);
            } else {
                $hour = rand(0, 23);
            }
            $date = Carbon::now()->subDays($daysAgo)->setTime($hour, rand(0, 59), rand(0, 59));
            DB::table('messages')->where('id', $msg->id)->update([
                'created_at' => $date,
                'updated_at' => clone $date,
            ]);
        }
        
        // Transactions: 0 - 60 days
        $transactions = DB::table('transactions')->get();
        foreach ($transactions as $tx) {
            $date = Carbon::now()->subDays(rand(0, 60))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
            DB::table('transactions')->where('id', $tx->id)->update([
                'created_at' => $date,
                'updated_at' => clone $date,
            ]);
        }
        
        // Reviews: 0 - 30 days
        $reviews = DB::table('reviews')->get();
        foreach ($reviews as $rev) {
            $date = Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23));
            DB::table('reviews')->where('id', $rev->id)->update([
                'created_at' => $date,
                'updated_at' => clone $date,
            ]);
        }
        
        // Activity logs: 0 - 90 days with peak hours
        $activityLogs = DB::table('activity_logs')->get();
        foreach ($activityLogs as $log) {
            $daysAgo = rand(0, 90);
            // Peak hours: 70% chance
            if (rand(1, 100) <= 70) {
                $hour = rand(1, 2) == 1 ? rand(9, 13) : rand(14, 18);
            } else {
                $hour = rand(0, 23);
            }
            $date = Carbon::now()->subDays($daysAgo)->setTime($hour, rand(0, 59), rand(0, 59));
            DB::table('activity_logs')->where('id', $log->id)->update([
                'created_at' => $date,
                'updated_at' => clone $date,
            ]);
        }
        
        // Conversations
        $conversations = DB::table('conversations')->get();
        foreach ($conversations as $conv) {
            $date = Carbon::now()->subDays(rand(5, 60))->subHours(rand(0, 23));
            DB::table('conversations')->where('id', $conv->id)->update([
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
        
        // Milestones
        $milestones = DB::table('milestones')->get();
        foreach ($milestones as $milestone) {
            $date = Carbon::now()->subDays(rand(10, 60))->subHours(rand(0, 23));
            DB::table('milestones')->where('id', $milestone->id)->update([
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
        
        $this->command->info('   ✓ Timestamps distribuidos correctamente para el mapa de calor');
    }
    
    /**
     * Crear PlatformCommissions de prueba para el dashboard
     */
    private function createPlatformCommissions(array $projects, array $companies, array $developers): void
    {
        $this->command->info('💰 Creando comisiones de prueba para el dashboard...');
        
        // Buscar proyectos completados o en progreso que tengan aplicaciones aceptadas
        $completedProjects = \App\Models\Project::where('status', 'in_progress')
            ->orWhere('status', 'completed')
            ->whereHas('applications', function ($query) {
                $query->where('status', 'accepted');
            })
            ->with(['applications' => function ($query) {
                $query->where('status', 'accepted');
            }])
            ->limit(20)
            ->get();
        
        $commissionCount = 0;
        foreach ($completedProjects as $project) {
            $application = $project->applications->first();
            if (!$application) continue;
            
            $totalAmount = $project->budget_max ?? $project->budget_min ?? 1000;
            $heldAmount = $totalAmount * 0.5;
            $commissionRate = $totalAmount < 500 ? 0.20 : 0.15;
            $commissionAmount = $heldAmount * $commissionRate;
            $netAmount = $heldAmount - $commissionAmount;
            
            // Algunos proyectos completados, otros pendientes
            $status = rand(1, 100) <= 70 ? 'released' : 'pending';
            
            \App\Models\PlatformCommission::create([
                'project_id' => $project->id,
                'company_id' => $project->company_id,
                'developer_id' => $application->developer_id,
                'total_amount' => $totalAmount,
                'held_amount' => $heldAmount,
                'commission_rate' => $commissionRate,
                'commission_amount' => $status === 'released' ? $commissionAmount : 0,
                'net_amount' => $status === 'released' ? $netAmount : 0,
                'status' => $status,
            ]);
            
            $commissionCount++;
        }
        
        $this->command->info('   ✓ Comisiones de prueba creadas: ' . $commissionCount);
    }
}
