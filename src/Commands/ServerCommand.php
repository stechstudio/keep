<?php

namespace STS\Keep\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;

class ServerCommand extends BaseCommand
{
    public $signature = 'server 
        {--port=4000 : Port to run the server on}
        {--host=localhost : Host to bind to}
        {--no-browser : Don\'t open browser automatically}';

    public $description = 'Start the Keep web UI server';

    protected function process(): int
    {
        $port = $this->option('port');
        $host = $this->option('host');
        $noBrowser = $this->option('no-browser');
        
        // Find available port if default is taken
        $port = $this->findAvailablePort($host, $port);
        
        // Build server command
        $phpBinary = (new PhpExecutableFinder)->find();
        $serverPath = realpath(__DIR__ . '/../Server/server.php');
        
        if (!file_exists($serverPath)) {
            $this->error('Server file not found. Please ensure Keep is properly installed.');
            return self::FAILURE;
        }
        
        // Generate auth token (automatically passed to browser)
        $token = bin2hex(random_bytes(32));
        
        $this->info('');
        $this->info('🚀 Starting Keep Web UI...');
        $this->info('');
        $this->info("URL: <comment>http://{$host}:{$port}</comment>");
        $this->info("Debug Token: <comment>{$token}</comment>");
        $this->info('');
        $this->info('✨ Authentication should be automatic - token is injected into browser.');
        $this->info('If prompted, use the debug token above.');
        $this->info('Press <comment>Ctrl+C</comment> to stop the server.');
        $this->info('');
        
        // Open browser if requested
        if (!$noBrowser) {
            $this->openBrowser("http://{$host}:{$port}");
        }
        
        // Start PHP built-in server
        $process = new Process([
            $phpBinary,
            '-S', "{$host}:{$port}",
            '-t', dirname($serverPath),
            $serverPath
        ]);
        
        $process->setEnv(['KEEP_AUTH_TOKEN' => $token]);
        $process->setTimeout(null);
        $process->setIdleTimeout(null);
        
        // Handle output
        $process->run(function ($type, $buffer) {
            // Filter out the noisy access logs, only show errors
            if (strpos($buffer, ' [200]: ') === false && 
                strpos($buffer, ' [304]: ') === false &&
                strpos($buffer, ' [404]: ') === false) {
                $this->output->write($buffer);
            }
        });
        
        return $process->getExitCode() ?? self::SUCCESS;
    }
    
    protected function findAvailablePort(string $host, int $startPort): int
    {
        for ($port = $startPort; $port < $startPort + 100; $port++) {
            $connection = @fsockopen($host, $port, $errno, $errstr, 0.1);
            if ($connection === false) {
                return $port; // Port is available
            }
            fclose($connection);
        }
        
        throw new \RuntimeException("No available ports found starting from {$startPort}");
    }
    
    protected function openBrowser(string $url): void
    {
        $commands = [
            'Darwin' => 'open',
            'Linux' => 'xdg-open',
            'Windows' => 'start'
        ];
        
        $os = PHP_OS_FAMILY === 'Windows' ? 'Windows' : PHP_OS;
        
        if (isset($commands[$os])) {
            $process = new Process([$commands[$os], $url]);
            $process->run(); // Don't wait for it
        }
    }
}