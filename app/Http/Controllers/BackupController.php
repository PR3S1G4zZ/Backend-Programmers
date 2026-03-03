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
        
        throw new \Exception("mysqldump executable not found.");
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
        
        throw new \Exception("mysql executable not found.");
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
                // If mysql.exe fails due to Winsock or PATH missing, use PDO unprepared execution natively.
                $this->executeFallbackRestore($tempPath);
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
        $tables = DB::select('SHOW TABLES');
        $dbName = env('DB_DATABASE', 'laravel');
        $property = 'Tables_in_' . $dbName;
        
        $sql = "-- Fallback PHP Native Backup\n";
        $sql .= "-- Generated by FlexWork\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        foreach ($tables as $table) {
            // Some environments use different casing or properties for SHOW TABLES results
            $tableName = isset($table->$property) ? $table->$property : (array_values((array)$table)[0]);
            
            // Get create table syntax
            $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
            $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            $sql .= array_values((array)$createTable[0])[1] . ";\n\n";
            
            // Get data
            $rows = DB::table($tableName)->get();
            foreach ($rows as $row) {
                $rowArray = (array)$row;
                $keys = array_keys($rowArray);
                $values = array_values($rowArray);
                
                $escapedValues = array_map(function($val) {
                    if (is_null($val)) return 'NULL';
                    $val = str_replace(["\\", "'", "\n", "\r"], ["\\\\", "\\'", "\\n", "\\r"], $val);
                    return "'" . $val . "'";
                }, $values);
                
                $sql .= "INSERT INTO `{$tableName}` (`" . implode("`, `", $keys) . "`) VALUES (" . implode(", ", $escapedValues) . ");\n";
            }
            $sql .= "\n";
        }
        
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        File::put($filePath, $sql);
    }

    private function executeFallbackRestore(string $filePath)
    {
        $sql = File::get($filePath);
        DB::unprepared($sql);
    }
}
