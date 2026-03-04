<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackupController extends Controller
{
    private $backupDisk = 'local';
    private $backupDir = 'backups';

    public function __construct()
    {
        if (!Storage::disk($this->backupDisk)->exists($this->backupDir)) {
            Storage::disk($this->backupDisk)->makeDirectory($this->backupDir);
        }
    }

    private function getMysqlDumpPath(): string
    {
        $paths = [
            'mysqldump', // in PATH
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe'
        ];

        foreach ($paths as $path) {
            if ($path === 'mysqldump') {
                $process = Process::fromShellCommandline('mysqldump --version');
                $process->run();
                if ($process->isSuccessful()) {
                    return 'mysqldump';
                }
                continue;
            }
            if (File::exists($path)) {
                return $path;
            }
        }
        
        // Also check if we might be in a pg environment and user asked for mysqldump loosely
        $pgDumpProcess = Process::fromShellCommandline('pg_dump --version');
        $pgDumpProcess->run();
        if ($pgDumpProcess->isSuccessful()) {
            return 'pg_dump';
        }

        return '';
    }

    private function getMysqlPath(): string
    {
        $paths = [
            'mysql', // in PATH
            'C:\\xampp\\mysql\\bin\\mysql.exe',
            'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysql.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysql.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe'
        ];

        foreach ($paths as $path) {
            if ($path === 'mysql') {
                $process = Process::fromShellCommandline('mysql --version');
                $process->run();
                if ($process->isSuccessful()) {
                    return 'mysql';
                }
                continue;
            }
            if (File::exists($path)) {
                return $path;
            }
        }
        
        $psqlProcess = Process::fromShellCommandline('psql --version');
        $psqlProcess->run();
        if ($psqlProcess->isSuccessful()) {
            return 'psql';
        }

        return '';
    }

    public function index(): JsonResponse
    {
        $files = Storage::disk($this->backupDisk)->files($this->backupDir);
        $backups = [];

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $backups[] = [
                    'name' => basename($file),
                    'size' => Storage::disk($this->backupDisk)->size($file),
                    'date' => Carbon::createFromTimestamp(Storage::disk($this->backupDisk)->lastModified($file))->toIso8601String(),
                ];
            }
        }

        // Sort by date descending
        usort($backups, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));

        return response()->json([
            'success' => true,
            'backups' => $backups,
            'last_backup' => count($backups) > 0 ? $backups[0] : null
        ]);
    }

    public function create(): JsonResponse
    {
        try {
            $filename = 'backup_' . env('DB_DATABASE') . '_' . date('Y_m_d_H_i_s') . '.sql';
            $dirPath = storage_path('app/' . $this->backupDir);
            
            if (!File::exists($dirPath)) {
                File::makeDirectory($dirPath, 0755, true);
            }
            
            $filePath = $dirPath . DIRECTORY_SEPARATOR . $filename;

            $dumpPath = $this->getMysqlDumpPath();
            $dbHost = env('DB_HOST', '127.0.0.1');
            $dbPort = env('DB_PORT', '3306');
            $dbUser = env('DB_USERNAME', 'root');
            $dbPass = env('DB_PASSWORD', '');
            $dbName = env('DB_DATABASE', 'laravel');
            $dbConnection = env('DB_CONNECTION', config('database.default'));

            if (!$dumpPath) {
                $this->generateFallbackBackup($filePath);
            } else {
                if ($dbConnection === 'pgsql') {
                $command = sprintf(
                    'PGPASSWORD=%s %s -h %s -p %s -U %s -F c -f "%s" %s',
                    escapeshellarg($dbPass),
                    $dumpPath === 'pg_dump' ? 'pg_dump' : $dumpPath,
                    escapeshellarg($dbHost),
                    escapeshellarg($dbPort),
                    escapeshellarg($dbUser),
                    $filePath,
                    escapeshellarg($dbName)
                );
            } else {
                // Build command using --result-file instead of > to avoid shell redirection path issues on Windows
                $command = sprintf(
                    '"%s" --host=%s --port=%s --user=%s %s --result-file="%s" %s',
                    $dumpPath,
                    escapeshellarg($dbHost),
                    escapeshellarg($dbPort),
                    escapeshellarg($dbUser),
                    $dbPass ? '--password=' . escapeshellarg($dbPass) : '',
                    $filePath,
                    escapeshellarg($dbName)
                );
            }

            // Build process environment ensuring SYSTEMROOT exists for WinSock/TCP connections (bug in php built-in server)
            $env = array_merge($_SERVER, $_ENV);
            $env['SYSTEMROOT'] = getenv('SYSTEMROOT') ?: 'C:\\Windows';
            $env['SystemDrive'] = getenv('SystemDrive') ?: 'C:';

            // Execute without shell wrapping issues, inheriting the full environment, but set CWD to mysql/bin
            // so it can dynamically load caching_sha2_password or other WinSock DLLs natively.
            $process = Process::fromShellCommandline($command, dirname($dumpPath), $env);
            $process->setTimeout(300); // 5 minutes max
            
                try {
                    $process->run();
                    if (!$process->isSuccessful()) {
                        throw new ProcessFailedException($process);
                    }
                } catch (\Exception $processException) {
                    // If the mysqldump binary fails due to WinSock (10106) or PATH errors, use pure PHP fallback
                    $this->generateFallbackBackup($filePath);
                }
            }

            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'create_backup',
                'details' => 'Generó respaldo de base de datos: ' . $filename,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Respaldo generado exitosamente',
                'filename' => $filename
            ]);

        } catch (\Exception $e) {
            $errorMessage = mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            return response()->json([
                'success' => false,
                'message' => 'Error al generar respaldo: ' . $errorMessage
            ], 500);
        }
    }

    public function restore(Request $request): JsonResponse
    {
        $request->validate([
            'backup_file' => 'required|file|mimetypes:text/plain,application/sql,application/octet-stream'
        ]);

        try {
            $file = $request->file('backup_file');
            $tempPath = $file->getRealPath();

            $mysqlPath = $this->getMysqlPath();
            $dbHost = env('DB_HOST', '127.0.0.1');
            $dbPort = env('DB_PORT', '3306');
            $dbUser = env('DB_USERNAME', 'root');
            $dbPass = env('DB_PASSWORD', '');
            $dbName = env('DB_DATABASE', 'laravel');
            $dbConnection = env('DB_CONNECTION', config('database.default'));

            if (!$mysqlPath) {
                $this->executeFallbackRestore($tempPath);
            } else {
                if ($dbConnection === 'pgsql') {
                 // For postgres, we either use pg_restore or psql depending on dump format
                 $command = sprintf(
                    'PGPASSWORD=%s psql -h %s -p %s -U %s -d %s -f "%s"',
                    escapeshellarg($dbPass),
                    escapeshellarg($dbHost),
                    escapeshellarg($dbPort),
                    escapeshellarg($dbUser),
                    escapeshellarg($dbName),
                    $tempPath
                );
            } else {
                // The command needs to read from the temp file
                $command = sprintf(
                    '"%s" --host=%s --port=%s --user=%s %s %s < "%s"',
                    $mysqlPath,
                    escapeshellarg($dbHost),
                    escapeshellarg($dbPort),
                    escapeshellarg($dbUser),
                    $dbPass ? '--password=' . escapeshellarg($dbPass) : '',
                    escapeshellarg($dbName),
                    $tempPath
                );
            }

            $env = array_merge($_SERVER, $_ENV);
            $env['SYSTEMROOT'] = getenv('SYSTEMROOT') ?: 'C:\\Windows';
            $env['SystemDrive'] = getenv('SystemDrive') ?: 'C:';

            // Set CWD to mysql bin folder to load native DLL plugins on XAMPP Windows
            $process = Process::fromShellCommandline($command, dirname($mysqlPath), $env);
            $process->setTimeout(600); // 10 minutes max for large databases
            
                try {
                    $process->run();
                    if (!$process->isSuccessful()) {
                        throw new ProcessFailedException($process);
                    }
                } catch (\Exception $processException) {
                    // If mysql.exe fails due to Winsock or PATH missing, or pg_restore isn't available
                    $this->executeFallbackRestore($tempPath);
                }
            }

            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'restore_backup',
                'details' => 'Restauró base de datos desde archivo subido',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Base de datos restaurada exitosamente'
            ]);

        } catch (\Exception $e) {
            $errorMessage = mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            return response()->json([
                'success' => false,
                'message' => 'Error al restaurar base de datos: ' . $errorMessage
            ], 500);
        }
    }

    public function download($filename)
    {
        // Debug logging
        Log::info('Download backup attempt: ' . $filename);
        
        // Simple security check
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            Log::warning('Invalid filename attempt: ' . $filename);
            abort(403, 'Archivo no válido');
        }

        $path = $this->backupDir . '/' . $filename;
        
        // Debug: log the path being checked
        Log::info('Checking path: ' . $path);
        
        if (!Storage::disk($this->backupDisk)->exists($path)) {
            Log::error('Backup file not found: ' . $path);
            abort(404, 'Respaldo no encontrado');
        }

        // Try-catch around ActivityLog to prevent crashes
        try {
            $userId = Auth::id();
            Log::info('User ID for activity log: ' . ($userId ?? 'null'));
            
            if ($userId) {
                ActivityLog::create([
                    'user_id' => $userId,
                    'action' => 'download_backup',
                    'details' => 'Descargó respaldo de base de datos: ' . $filename,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent()
                ]);
            }
        } catch (\Exception $e) {
            // Log but don't fail the download if activity log fails
            Log::error('ActivityLog creation failed: ' . $e->getMessage());
        }

        $absolutePath = Storage::disk($this->backupDisk)->path($path);
        
        Log::info('Absolute path: ' . $absolutePath);
        Log::info('File exists: ' . (file_exists($absolutePath) ? 'yes' : 'no'));
        Log::info('File size: ' . filesize($absolutePath));

        // Use readfile instead of response()->download() for more reliable file serving
        // and avoid issues with headers in API responses
        $headers = [
            'Content-Type' => 'application/sql',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => filesize($absolutePath),
        ];
        
        Log::info('Sending file with headers: ' . json_encode($headers));
        
        return response(file_get_contents($absolutePath), 200, $headers);
    }

    private function generateFallbackBackup(string $filePath)
    {
        $dbConnection = env('DB_CONNECTION', config('database.default'));
        $dbName = env('DB_DATABASE', 'laravel');
        $tables = [];

        if ($dbConnection === 'pgsql') {
            $query = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'";
            $tablesData = DB::select($query);
            foreach ($tablesData as $table) {
                $tables[] = $table->table_name;
            }
        } else {
            $tablesData = DB::select('SHOW TABLES');
            $property = 'Tables_in_' . $dbName;
            foreach ($tablesData as $table) {
                // Some environments use different casing or properties for SHOW TABLES results
                $tables[] = isset($table->$property) ? $table->$property : (array_values((array)$table)[0]);
            }
        }
        
        $sql = "-- Fallback PHP Native Backup\n";
        $sql .= "-- Generated by FlexWork\n\n";

        if ($dbConnection === 'pgsql') {
            // PostgreSQL Disable Triggers/Constraints
            $sql .= "SET session_replication_role = 'replica';\n\n";
        } else {
            // MySQL Disable Checks
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        }
        
        foreach ($tables as $tableName) {
            
            // Get create table syntax if possible, though dumping structure via raw PHP across DBs is complex.
            // For Postgres, it's very difficult to get the full CREATE TABLE statement in one query.
            // We'll rely on TRUNCATE/DELETE for restoring if we can't get CREATE statements.
            
            if ($dbConnection === 'mysql') {
                $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
                $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $sql .= array_values((array)$createTable[0])[1] . ";\n\n";
            } else if ($dbConnection === 'pgsql') {
                // Just clear the table before insert since we likely pre-created the schema via artisan migrate
                $sql .= "TRUNCATE TABLE \"{$tableName}\" CASCADE;\n\n";
            }
            
            // Get data
            $rows = DB::table($tableName)->get();
            foreach ($rows as $row) {
                $rowArray = (array)$row;
                $keys = array_keys($rowArray);
                $values = array_values($rowArray);
                
                $escapedValues = array_map(function($val) use ($dbConnection) {
                    if (is_null($val)) return 'NULL';
                    if ($dbConnection === 'pgsql') {
                        // Basic postgres escaping
                        $val = str_replace("'", "''", $val);
                        return "'" . $val . "'";
                    } else {
                        // MySQL escaping
                        $val = str_replace(["\\", "'", "\n", "\r"], ["\\\\", "\\'", "\\n", "\\r"], $val);
                        return "'" . $val . "'";
                    }
                }, $values);
                
                if ($dbConnection === 'pgsql') {
                    $sql .= "INSERT INTO \"{$tableName}\" (\"" . implode('", "', $keys) . "\") VALUES (" . implode(", ", $escapedValues) . ");\n";
                } else {
                    $sql .= "INSERT INTO `{$tableName}` (`" . implode("`, `", $keys) . "`) VALUES (" . implode(", ", $escapedValues) . ");\n";
                }
            }
            $sql .= "\n";
        }
        
        if ($dbConnection === 'pgsql') {
            $sql .= "SET session_replication_role = 'origin';\n";
        } else {
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        }

        File::put($filePath, $sql);
    }

    private function executeFallbackRestore(string $filePath)
    {
        $sql = File::get($filePath);
        DB::unprepared($sql);
    }
}
