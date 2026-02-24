<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

class DatabaseBackUp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'database:backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Ensure backup directory exists
        $backupDir = storage_path().'/app/public/backup';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        // Clean old backups
        foreach (glob($backupDir.'/*') as $filename) {
            if (is_file($filename)) {
                @unlink($filename);
            }
        }

        // Use config() instead of env() to get cached values, or fallback to env()
        $db_user = config('database.connections.mysql.username') ?: env('DB_USERNAME');
        $db_pass = config('database.connections.mysql.password') ?: env('DB_PASSWORD');
        $db_host = config('database.connections.mysql.host') ?: env('DB_HOST', '127.0.0.1');
        $db_name = config('database.connections.mysql.database') ?: env('DB_DATABASE');
        
        // Validate required values
        if (empty($db_user) || $db_user === 'ODBC') {
            $this->error('Invalid database username. Please check DB_USERNAME in .env file.');
            $this->error('Current username value: '.($db_user ?: 'empty'));
            $this->error('Host: '.$db_host);
            $this->error('Database: '.($db_name ?: 'empty'));
            $this->line('ERROR_DETAILS: Database username is missing or invalid (got: '.($db_user ?: 'empty').'). Please check your .env file and ensure DB_USERNAME is set correctly.');
            return 1;
        }
        
        if (empty($db_name)) {
            $this->error('Database name is missing. Please check DB_DATABASE in .env file.');
            $this->line('ERROR_DETAILS: Database name is missing');
            return 1;
        }
        
        // Debug output (without password)
        $this->line('Database connection details:');
        $this->line('  Host: '.$db_host);
        $this->line('  User: '.$db_user);
        $this->line('  Database: '.$db_name);
        $this->line('  Password: '.(!empty($db_pass) ? '*** (set)' : '(empty)'));
        
        // Check if we're on Windows
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        // Get mysqldump path
        $dumpPath = env('DUMP_PATH');
        if (empty($dumpPath)) {
            // Try to find mysqldump automatically
            $dumpPath = $this->findMysqldump();
            if (empty($dumpPath)) {
                $this->error('mysqldump not found. Please set DUMP_PATH in .env file.');
                $this->error('');
                $this->error('For Laragon on Windows, add this to your .env file:');
                $this->error('DUMP_PATH="C:\\laragon\\bin\\mysql\\mysql-8.0.30\\bin\\mysqldump.exe"');
                $this->error('');
                $this->error('(Replace mysql-8.0.30 with your actual MySQL version folder name)');
                $this->line('ERROR_DETAILS: mysqldump executable not found. Please set DUMP_PATH in .env file. For Laragon: DUMP_PATH="C:\\laragon\\bin\\mysql\\mysql-VERSION\\bin\\mysqldump.exe"');
                return 1;
            }
        }
        
        // Normalize the path
        $dumpPath = trim($dumpPath);
        
        // If it's just a command name (like "mysqldump" or "mysqldump.exe"), 
        // try to resolve it from PATH (for backward compatibility)
        if ($dumpPath === 'mysqldump' || $dumpPath === 'mysqldump.exe') {
            $resolvedPath = $this->resolveFromPath($dumpPath, $isWindows);
            if ($resolvedPath && file_exists($resolvedPath)) {
                $dumpPath = $resolvedPath;
            } else {
                // If we can't resolve it from PATH, show error
                $this->error('mysqldump not found in PATH. Please set DUMP_PATH in .env file with the full path.');
                $this->error('');
                $this->error('For Laragon on Windows, add this to your .env file:');
                $this->error('DUMP_PATH="C:\\laragon\\bin\\mysql\\mysql-8.0.30\\bin\\mysqldump.exe"');
                $this->line('ERROR_DETAILS: mysqldump executable not found. Please set DUMP_PATH in .env file with the full path.');
                return 1;
            }
        }
        
        // Verify mysqldump exists and is accessible
        if (!file_exists($dumpPath)) {
            $this->error('mysqldump not found at: '.$dumpPath);
            $this->error('');
            $this->error('Please check:');
            $this->error('1. The path in DUMP_PATH is correct');
            $this->error('2. MySQL is installed in Laragon');
            $this->error('3. The MySQL version folder name matches');
            $this->error('');
            $this->error('Common Laragon paths:');
            $this->error('  C:\\laragon\\bin\\mysql\\mysql-8.0.30\\bin\\mysqldump.exe');
            $this->error('  C:\\laragon\\bin\\mysql\\mysql-8.0.24\\bin\\mysqldump.exe');
            $this->error('  (Check your actual folder name in C:\\laragon\\bin\\mysql\\)');
            $this->line('ERROR_DETAILS: mysqldump executable not found at: '.$dumpPath.'. Please verify the path in DUMP_PATH is correct.');
            return 1;
        }
        
        // On Windows, verify it's an .exe file
        if ($isWindows && !preg_match('/\.exe$/i', $dumpPath)) {
            // Try adding .exe
            if (file_exists($dumpPath.'.exe')) {
                $dumpPath = $dumpPath.'.exe';
            }
        }

        $filename = 'backup-'.Carbon::now()->format('Y-m-d-H-i-s').'.sql';
        $filePath = $backupDir.'/'.$filename;

        // Create a temporary config file for mysqldump (more secure than command line password)
        $configFile = $backupDir.'/'.'.my.cnf.'.time().'.tmp';
        $configContent = "[client]\n";
        $configContent .= "user=".$db_user."\n";
        if (!empty($db_pass)) {
            $configContent .= "password=".$db_pass."\n";
        }
        $configContent .= "host=".$db_host."\n";
        
        // Write config file
        if (@file_put_contents($configFile, $configContent) === false) {
            $this->error('Failed to create temporary config file');
            $this->line('ERROR_DETAILS: Cannot create temporary MySQL config file');
            return 1;
        }
        
        // Set permissions (readable only by owner)
        @chmod($configFile, 0600);

        // Build command with proper escaping
        $quote = function ($s) use ($isWindows) {
            if ($isWindows) {
                // Windows: wrap in double quotes, escape internal quotes
                $s = str_replace('"', '""', $s);
                return '"'.$s.'"';
            }
            // Unix: use escapeshellarg
            return escapeshellarg($s);
        };

        // Build command using --defaults-extra-file (more secure)
        $command = $quote($dumpPath);
        $command .= ' --defaults-extra-file='.$quote($configFile);
        $command .= ' --single-transaction';
        $command .= ' --routines';
        $command .= ' --triggers';
        $command .= ' '.$quote($db_name);
        
        // Redirect output to file
        $command .= ' > '.$quote($filePath).' 2>&1';
        
        // Build command for display (without password)
        $commandDisplay = $quote($dumpPath);
        $commandDisplay .= ' --defaults-extra-file='.$quote($configFile);
        $commandDisplay .= ' --single-transaction';
        $commandDisplay .= ' --routines';
        $commandDisplay .= ' --triggers';
        $commandDisplay .= ' '.$quote($db_name);
        $commandDisplay .= ' > '.$quote($filePath).' 2>&1';
        
        // Log command for debugging (without password)
        $this->line('Executing: '.$commandDisplay);

        // Execute command and capture both stdout and stderr
        $returnVar = null;
        $output = [];
        $errorOutput = [];
        
        // On Windows, we need to handle stderr differently
        if ($isWindows) {
            // Use proc_open for better error handling on Windows
            $descriptorspec = [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout
                2 => ['pipe', 'w'],  // stderr
            ];
            
            $process = proc_open($command, $descriptorspec, $pipes);
            
            if (is_resource($process)) {
                // Close stdin
                fclose($pipes[0]);
                
                // Read stdout
                $stdout = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                
                // Read stderr
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                
                // Get exit code
                $returnVar = proc_close($process);
                
                if (!empty($stdout)) {
                    $output[] = $stdout;
                }
                if (!empty($stderr)) {
                    $errorOutput[] = $stderr;
                }
            } else {
                $this->error('Failed to execute backup command');
                return 1;
            }
        } else {
            // Unix/Linux: redirect stderr to stdout
            exec($command.' 2>&1', $output, $returnVar);
            $errorOutput = $output; // On Unix, errors are in output
        }

        // Check if backup was successful
        if ($returnVar !== 0) {
            $errorMsg = '';
            if (!empty($errorOutput)) {
                $errorMsg = implode("\n", $errorOutput);
            } elseif (!empty($output)) {
                $errorMsg = implode("\n", $output);
            }
            
            // Also check the file for error messages (mysqldump writes errors to file)
            if (file_exists($filePath)) {
                $fileContent = @file_get_contents($filePath);
                if (!empty($fileContent) && (stripos($fileContent, 'error') !== false || stripos($fileContent, 'access denied') !== false)) {
                    $errorMsg = trim($fileContent);
                }
            }
            
            $this->error('Backup failed with exit code: '.$returnVar);
            
            if (!empty($errorMsg)) {
                $this->error('Error: '.trim($errorMsg));
            } else {
                $this->error('No error output captured. Please check:');
                $this->error('1. DUMP_PATH is correct: '.($dumpPath ?: 'not set'));
                $this->error('2. Database credentials in .env are correct');
                $this->error('3. MySQL server is running');
                $this->error('4. User has backup permissions');
                $this->error('5. Database name is correct: '.$db_name);
            }
            
            // Delete empty file if it was created
            if (file_exists($filePath) && filesize($filePath) === 0) {
                @unlink($filePath);
            }
            
            // Clean up temporary config file
            if (file_exists($configFile)) {
                @unlink($configFile);
            }
            
            // Store error in output for controller to read
            $fullError = 'Exit code: '.$returnVar;
            if (!empty($errorMsg)) {
                $fullError .= "\n".trim($errorMsg);
            }
            $this->line('ERROR_DETAILS: '.$fullError);
            
            return 1;
        }

        // Verify file was created and has content
        if (!file_exists($filePath)) {
            $this->error('Backup file was not created.');
            return 1;
        }

        $fileSize = filesize($filePath);
        if ($fileSize === 0) {
            $this->error('Backup file is empty. Check database credentials and mysqldump path.');
            @unlink($filePath);
            return 1;
        }

        // Clean up temporary config file
        if (file_exists($configFile)) {
            @unlink($configFile);
        }
        
        $this->info('Backup created successfully: '.$filename.' ('.number_format($fileSize / 1024, 2).' KB)');
        return 0;
    }

    /**
     * Try to find mysqldump executable
     */
    private function findMysqldump(): string
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        // Common paths to check
        $paths = [];
        
        if ($isWindows) {
            // Windows common paths - check Laragon first (most common for this project)
            // Try multiple Laragon path patterns
            $laragonBase = 'C:\\laragon\\bin\\mysql\\';
            if (is_dir($laragonBase)) {
                // Get all MySQL version folders
                $mysqlDirs = glob($laragonBase . 'mysql-*', GLOB_ONLYDIR);
                if (!empty($mysqlDirs)) {
                    // Sort to get the latest version first
                    rsort($mysqlDirs);
                    foreach ($mysqlDirs as $mysqlDir) {
                        $dumpPath = $mysqlDir . '\\bin\\mysqldump.exe';
                        if (file_exists($dumpPath)) {
                            return $dumpPath;
                        }
                    }
                }
                
                // Also try without version number (some installations)
                $dumpPath = $laragonBase . 'mysql\\bin\\mysqldump.exe';
                if (file_exists($dumpPath)) {
                    return $dumpPath;
                }
            }
            
            // Other Windows paths
            $paths = [
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\wamp64\\bin\\mysql\\mysql8.0\\bin\\mysqldump.exe',
                'C:\\wamp\\bin\\mysql\\mysql8.0\\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysqldump.exe',
                'C:\\Program Files (x86)\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
            ];
        } else {
            // Unix/Linux common paths
            $paths = [
                '/usr/bin/mysqldump',
                '/usr/local/bin/mysqldump',
                '/opt/mysql/bin/mysqldump',
            ];
        }

        // Check hardcoded paths
        foreach ($paths as $path) {
            if ($isWindows) {
                // On Windows, check if file exists
                if (file_exists($path)) {
                    return $path;
                }
            } else {
                // On Unix, check if executable
                if (is_executable($path)) {
                    return $path;
                }
            }
        }

        // Last resort: try to find it in PATH using where/which
        // But only if we haven't found it in common locations
        // Note: We prefer explicit paths over PATH because PATH might have issues
        $output = [];
        $command = $isWindows ? 'where mysqldump.exe 2>nul' : 'which mysqldump 2>&1';
        exec($command, $output, $returnVar);
        
        if ($returnVar === 0 && !empty($output[0])) {
            $foundPath = trim($output[0]);
            // Make sure it's a full path, not just the command name
            // On Windows, 'where' should return full paths, but verify
            if ($foundPath !== 'mysqldump' && $foundPath !== 'mysqldump.exe') {
                // Check if it contains a path separator (indicates it's a full path)
                if (strpos($foundPath, '/') !== false || strpos($foundPath, '\\') !== false || ($isWindows && strpos($foundPath, ':') !== false)) {
                    // Verify it actually exists and is executable
                    if (file_exists($foundPath)) {
                        return $foundPath;
                    }
                }
            }
        }

        // If we still haven't found it, return empty string
        // This will trigger the error message asking user to set DUMP_PATH
        return '';
    }

    /**
     * Resolve a command name from PATH
     */
    private function resolveFromPath(string $command, bool $isWindows): ?string
    {
        $output = [];
        $cmd = $isWindows ? 'where '.$command.' 2>nul' : 'which '.$command.' 2>&1';
        exec($cmd, $output, $returnVar);
        
        if ($returnVar === 0 && !empty($output[0])) {
            $foundPath = trim($output[0]);
            // Make sure it's a full path (contains path separators)
            if (strpos($foundPath, '/') !== false || strpos($foundPath, '\\') !== false || ($isWindows && strpos($foundPath, ':') !== false)) {
                if (file_exists($foundPath)) {
                    return $foundPath;
                }
            }
        }
        
        return null;
    }
}
