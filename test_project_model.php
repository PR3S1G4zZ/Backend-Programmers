<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;

// Inicializar Laravel
$app = require __DIR__.'/bootstrap/app.php';

// Crear una instancia de la aplicación
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Probar el modelo Project
echo "Testing Project Model...\n";
$project = \App\Models\Project::first();
if ($project) {
    echo "Project ID: " . $project->id . "\n";
    echo "Progress Percentage: " . $project->progress_percentage . "\n";
    echo "All Milestones Completed: " . ($project->all_milestones_completed ? "Yes" : "No") . "\n";
} else {
    echo "No projects found\n";
}
