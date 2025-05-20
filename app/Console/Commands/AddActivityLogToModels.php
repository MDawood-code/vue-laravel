<?php

namespace App\Console\Commands;

use InvalidArgumentException;
use Exception;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use function Termwind\{render, ask};

class AddActivityLogToModels extends Command
{
    protected $signature = 'models:add-activity-log
        {--backup : Create backups before modifying files}
        {--restore : Restore from the most recent backup}
        {--restore-from= : Restore from a specific backup timestamp (format: Y-m-d_His)}
        {--keep-backups=5 : Number of backups to keep (default: 5)}';

    protected $description = 'Backup and restore models when adding activity log trait.';

    private int $backupFiles = 0;
    private $backupPath;

    public function __construct()
    {
        parent::__construct();
        $this->backupPath = storage_path('app/backups/models');
    }

    private function validateInput(): bool
    {
        try {
            // Validate restore-from timestamp if provided
            $timestamp = $this->option('restore-from');
            throw_if($timestamp !== null && !preg_match('/^\d{4}-\d{2}-\d{2}_\d{6}$/', $timestamp), new InvalidArgumentException(
                "Invalid timestamp format. Expected: YYYY-MM-DD_HHMMSS"
            ));

            // Validate keep-backups option
            $keepBackups = $this->option('keep-backups');
            throw_if($keepBackups !== null && (!is_numeric($keepBackups) || $keepBackups < 1), new InvalidArgumentException(
                "Invalid keep-backups value. Must be a positive number."
            ));

            return true;

        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return false;
        }
    }

    public function handle(): int
    {
        // Add validation at the start
        if (!$this->validateInput()) {
            return 1;
        }

        // Handle restore options first
        if ($this->option('restore') || $this->option('restore-from')) {
            return $this->handleRestore();
        }

        // If backup option is specified, create backups
        if ($this->option('backup')) {
            return $this->handleBackup();
        }

        $this->error('Please specify either --backup or --restore options.');
        return 1;
    }

    private function handleBackup(): int
    {
        $modelPath = app_path('Models');
        if (!File::isDirectory($modelPath)) {
            $this->error('Models directory not found!');
            return 1;
        }

        $files = File::glob($modelPath . '/*.php');
        if (empty($files)) {
            $this->warn('No PHP files found in Models directory.');
            return 0;
        }

        // Clean old backups before creating new one
        $this->cleanOldBackups();

        $backupTimestamp = date('Y-m-d_His');

        foreach ($files as $file) {
            $code = file_get_contents($file);
            $backupFile = "{$this->backupPath}/{$backupTimestamp}/" . basename((string) $file);
            File::makeDirectory(dirname($backupFile), 0755, true, true);
            File::put($backupFile, $code);
            $this->backupFiles++;

            render(<<<HTML
                <div class="px-1 bg-green-300 text-black">
                    ✓ Backup created: <span class="font-bold">{$backupFile}</span>
                </div>
            HTML);
        }

        $this->newLine();
        $this->info("Backup completed. Total files backed up: {$this->backupFiles}");
        return 0;
    }

    private function handleRestore(): int
    {
        if (!File::isDirectory($this->backupPath)) {
            render(<<<'HTML'
                <div class="px-1 bg-red-500 text-white">
                    No backups found!
                </div>
            HTML);
            return 1;
        }

        $timestamp = $this->option('restore-from');

        if (!$timestamp) {
            $backups = $this->listBackups();

            if ($backups === []) {
                render(<<<'HTML'
                    <div class="px-1 bg-red-500 text-white">
                        No backups available!
                    </div>
                HTML);
                return 1;
            }

            render(<<<'HTML'
                <div class="px-1 bg-blue-300 text-black">
                    <span class="font-bold">Available Backups:</span>
                </div>
            HTML);

            // Sort backups in reverse chronological order (newest first)
            $backups = array_reverse($backups);

            foreach ($backups as $index => $backup) {
                $formattedDate = $this->formatTimestamp($backup);
                $number = $index + 1;
                render(<<<HTML
                    <div class="px-1 mb-1">
                        <div class="font-bold">[{$number}] {$formattedDate}</div>
                        <div class="text-gray-500 pl-4">ID: {$backup}</div>
                    </div>
                HTML);
            }

            $selected = $this->ask('Select backup to restore (number):');
            $timestamp = $backups[$selected - 1] ?? null;

            if (!$timestamp) {
                render(<<<'HTML'
                    <div class="px-1 bg-red-500 text-white">
                        Invalid selection!
                    </div>
                HTML);
                return 1;
            }
        }

        $backupDir = "{$this->backupPath}/{$timestamp}";

        if (!File::isDirectory($backupDir)) {
            $this->error("Backup from {$timestamp} not found!");
            return 1;
        }

        if (!$this->confirm("Are you sure you want to restore from backup {$timestamp}?")) {
            $this->info('Restore cancelled.');
            return 0;
        }

        $files = File::files($backupDir);
        $restoredCount = 0;
        $failedCount = 0;

        foreach ($files as $file) {
            $modelFile = app_path('Models/' . $file->getFilename());

            try {
                File::copy($file->getPathname(), $modelFile);
                $restoredCount++;
                $this->info("Restored: " . basename($modelFile));
            } catch (Exception) {
                $failedCount++;
                $this->error("Failed to restore: " . basename($modelFile));
            }
        }

        $this->newLine();
        $this->info('Restore Summary:');
        $this->line("Files restored: {$restoredCount}");
        $this->line("Files failed: {$failedCount}");

        return 0;
    }

    private function listBackups(): array
    {
        if (!File::isDirectory($this->backupPath)) {
            return [];
        }

        return collect(File::directories($this->backupPath))
            ->map(fn($dir): string => basename((string) $dir))
            ->sort()
            ->values()
            ->toArray();
    }

    private function cleanOldBackups(): void
    {
        if (!File::isDirectory($this->backupPath)) {
            return;
        }

        // Get all backup directories sorted by name (timestamp)
        $backups = collect(File::directories($this->backupPath))
            ->map(fn($dir): string => basename((string) $dir))
            ->sort()
            ->values()
            ->toArray();

        $keepBackups = (int)$this->option('keep-backups');

        // If we have reached the limit (considering we're about to add a new backup)
        if (count($backups) >= $keepBackups) {
            // Calculate how many to remove to stay under limit after new backup
            $toRemove = array_slice($backups, 0, count($backups) - $keepBackups + 1);

            foreach ($toRemove as $backup) {
                $backupDir = $this->backupPath . '/' . $backup;
                if (File::deleteDirectory($backupDir)) {
                    $this->info("Removed old backup: {$backup}");
                }
            }
        }
    }

    private function formatTimestamp(string $timestamp): string
    {
        try {
            $date = Carbon::createFromFormat('Y-m-d_His', $timestamp);
            return $date->format('Y-m-d H:i:s') . ' (' . $date->diffForHumans() . ')';
        } catch (Exception) {
            return $timestamp;
        }
    }
}
