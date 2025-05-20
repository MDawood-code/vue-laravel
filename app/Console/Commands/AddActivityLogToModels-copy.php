<?php

namespace App\Console\Commands;

use InvalidArgumentException;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\String_;
use Carbon\Carbon;
use Exception;
use PhpParser\Node\Stmt\ClassMethod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PhpParser\Error;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\BuilderFactory;
use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\DummyFile;
use PHP_CodeSniffer\Fixer;
use function Termwind\{render, ask};
use Termwind\Terminal;

class AddActivityLogToModels extends Command
{
    protected $signature = 'models:add-activity-log
        {models?* : Specific model names to process (without .php extension)}
        {--dry-run : Show what would be changed without making changes}
        {--backup : Create backups before modifying files}
        {--restore : Restore from the most recent backup}
        {--restore-from= : Restore from a specific backup timestamp (format: Y-m-d_His)}
        {--keep-backups=5 : Number of backups to keep (default: 5)}';

    protected $description = 'Add Spatie activity log trait to all models. Optionally specify model names to process specific models only.';

    private int $processedFiles = 0;
    private int $modifiedFiles = 0;
    private int $failedFiles = 0;
    private int $backupFiles = 0;
    private $backupPath;

    private const array REQUIRED_TRAITS = [
        'Spatie\Activitylog\Traits\LogsActivity',
    ];

    private const array REQUIRED_INTERFACES = [
        'Spatie\Activitylog\LogOptions',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->backupPath = storage_path('app/backups/models');
    }

    private function validateInput(): bool
    {
        try {
            // Validate model names
            $specificModels = $this->argument('models');
            if (!empty($specificModels)) {
                foreach ($specificModels as $model) {
                    throw_unless(preg_match('/^\w+$/', (string) $model), new InvalidArgumentException(
                        "Invalid model name '{$model}'. Only alphanumeric characters and underscores are allowed."
                    ));

                    throw_if(str_contains((string) $model, '/') || str_contains((string) $model, '\\'), new InvalidArgumentException(
                        "Invalid model name '{$model}'. Directory separators are not allowed."
                    ));
                }
            }

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

    public function handle(): ?int
    {
        // Add validation at the start
        if (!$this->validateInput()) {
            return 1;
        }

        // Handle restore options first
        if ($this->option('restore') || $this->option('restore-from')) {
            return $this->handleRestore();
        }

        $modelPath = app_path('Models');
        $specificModels = $this->argument('models');

        if (!File::isDirectory($modelPath)) {
            $this->error('Models directory not found!');
            return 1;
        }

        $files = File::glob($modelPath . '/*.php');

        if (empty($files)) {
            $this->warn('No PHP files found in Models directory.');
            return 0;
        }

        // Filter files if specific models were provided
        if (!empty($specificModels)) {
            $files = array_filter($files, function($file) use ($specificModels): bool {
                $modelName = pathinfo($file, PATHINFO_FILENAME);
                return in_array($modelName, $specificModels);
            });

            if ($files === []) {
                $this->error('None of the specified models were found!');
                return 1;
            }

            // Check for models that weren't found
            $foundModels = array_map(fn($file): string => pathinfo((string) $file, PATHINFO_FILENAME), $files);

            $notFound = array_diff($specificModels, $foundModels);
            if ($notFound !== []) {
                $this->warn('Some models were not found: ' . implode(', ', $notFound));
            }
        }

        $isDryRun = $this->option('dry-run');
        $shouldBackup = $this->option('backup');

        if ($isDryRun) {
            $this->newLine();
            $this->line("\e[1;33mDRY RUN MODE - No files will be modified\e[0m");
            $this->newLine();
        }

        render(<<<'HTML'
            <div class="px-1 bg-blue-300 text-black">
                <span class="font-bold">Activity Log Trait Installer</span>
            </div>
        HTML);

        $backupTimestamp = date('Y-m-d_His');

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $prettyPrinter = new Standard();
        $builder = new BuilderFactory();
        $nodeFinder = new NodeFinder();

        if ($shouldBackup && !$isDryRun) {
            // Clean old backups before creating new one
            $this->cleanOldBackups();

            // ... proceed with backup creation ...
        }

        foreach ($files as $file) {
            $this->processedFiles++;

            try {
                $code = file_get_contents($file);
                $ast = $parser->parse($code);

                if (!$ast) {
                    $this->error("Could not parse file: " . basename((string) $file));
                    $this->failedFiles++;
                    continue;
                }

                $modified = false;

                // Find namespace node
                $namespaceNode = $nodeFinder->findFirst($ast, fn($node): bool => $node instanceof Namespace_);

                if (!$namespaceNode instanceof Node) {
                    $this->warn("No namespace found in: " . basename((string) $file));
                    continue;
                }

                $classNode = $nodeFinder->findFirst($ast, fn($node): bool => $node instanceof Class_);

                if (!$classNode instanceof Node) {
                    $this->warn("No class found in: " . basename((string) $file));
                    continue;
                }

                // Check and add use statements
                $newUseStatements = [];
                foreach (self::REQUIRED_TRAITS as $trait) {
                    if (!$this->hasUseStatement($namespaceNode->stmts, $trait)) {
                        $newUseStatements[] = $builder->use($trait)->getNode();
                        $modified = true;
                    }
                }

                foreach (self::REQUIRED_INTERFACES as $interface) {
                    if (!$this->hasUseStatement($namespaceNode->stmts, $interface)) {
                        $newUseStatements[] = $builder->use($interface)->getNode();
                        $modified = true;
                    }
                }

                // Find position to insert use statements (after existing use statements or at the start)
                $insertPosition = 0;
                foreach ($namespaceNode->stmts as $i => $stmt) {
                    if ($stmt instanceof Use_) {
                        $insertPosition = $i + 1;
                    } elseif ($stmt instanceof Class_) {
                        break;
                    }
                }

                // Insert all new use statements at once
                if ($newUseStatements !== []) {
                    $newUseStatements[] = new Nop();
                    array_splice($namespaceNode->stmts, $insertPosition, 0, $newUseStatements);
                }

                // Add trait properly aligned with other traits
                if (!$this->hasTraitUse($classNode, 'LogsActivity')) {
                    // First try to find existing trait use statements
                    $traitUseStatements = [];
                    foreach ($classNode->stmts as $stmt) {
                        if ($stmt instanceof TraitUse) {
                            $traitUseStatements[] = $stmt;
                        }
                    }

                    if ($traitUseStatements !== []) {
                        // Check if traits are declared on separate lines
                        $separateLines = false;
                        foreach ($traitUseStatements as $stmt) {
                            // If any trait use has only one trait, assume separate line style
                            if (count($stmt->traits) === 1) {
                                $separateLines = true;
                                break;
                            }
                        }

                        if ($separateLines) {
                            // Add as new separate trait use statement
                            $traitUse = $builder->useTrait('LogsActivity')->getNode();

                            // Find position after last trait use
                            $lastTraitPosition = 0;
                            foreach ($classNode->stmts as $i => $stmt) {
                                if ($stmt instanceof TraitUse) {
                                    $lastTraitPosition = $i;
                                }
                            }

                            // Insert after last trait
                            array_splice($classNode->stmts, $lastTraitPosition + 1, 0, [$traitUse]);
                        } else {
                            // Add to first trait use statement
                            $traitUseStatements[0]->traits[] = new Name('LogsActivity');
                        }
                    } else {
                        // No existing traits, add as first trait
                        $traitUse = $builder->useTrait('LogsActivity')->getNode();
                        array_unshift($classNode->stmts, $traitUse);
                    }

                    $modified = true;
                }

                // Add getActivitylogOptions method at the beginning of the class (after traits)
                if (!$this->hasMethod($classNode, 'getActivitylogOptions')) {
                    $methodCode = $builder->method('getActivitylogOptions')
                        ->makePublic()
                        ->setReturnType('LogOptions')
                        ->addStmt(
                            new Return_(
                                new MethodCall(
                                    new MethodCall(
                                        new StaticCall(
                                            new Name('LogOptions'),
                                            'defaults'
                                        ),
                                        'logOnly',
                                        [new Array_([
                                            new ArrayItem(
                                                new String_('*')
                                            )
                                        ])]
                                    ),
                                    'logOnlyDirty'
                                )
                            )
                        )
                        ->getNode();

                    // Find position after traits
                    $insertPosition = 0;
                    foreach ($classNode->stmts as $i => $stmt) {
                        if ($stmt instanceof TraitUse) {
                            $insertPosition = $i + 1;
                        }
                    }

                    // Insert method after traits with empty line
                    array_splice($classNode->stmts, $insertPosition, 0, [
                        new Nop(),
                        $methodCode,
                        new Nop()
                    ]);

                    $modified = true;
                }

                if ($modified) {
                    $this->modifiedFiles++;
                    $newContent = $prettyPrinter->prettyPrintFile($ast);

                    if ($isDryRun) {
                        $fileName = basename((string) $file);
                        $this->newLine();
                        $this->line("\e[1;34mChanges for: {$fileName}\e[0m");
                        $this->newLine();
                        $this->line($this->formatDiff($code, $newContent));
                    } else {
                        if ($shouldBackup) {
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

                        File::put($file, $newContent);
                        render(<<<HTML
                            <div class="px-1 bg-green-500 text-black">
                                ✓ Updated: <span class="font-bold">{basename($file)}</span>
                            </div>
                        HTML);
                    }
                } else {
                    $this->info("No changes needed for: " . basename((string) $file));
                }

            } catch (Error $error) {
                $this->failedFiles++;
                render(<<<HTML
                    <div class="px-1 bg-red-500 text-white">
                        ✗ Error in {basename($file)}: {$error->getMessage()}
                    </div>
                HTML);
            }
        }

        // Show summary
        $this->newLine();

        $filesStatus = $isDryRun ? 'to be modified' : 'modified';
        $backupLine = $shouldBackup ? "<div>Backup files created: {$this->backupFiles}</div>" : '';

        render(<<<HTML
            <div class="mb-1">
                <div class="px-1 bg-blue-300 text-black font-bold">Summary</div>
                <div class="px-1">
                    <div>Total files processed: {$this->processedFiles}</div>
                    <div>Files {$filesStatus}: {$this->modifiedFiles}</div>
                    {$backupLine}
                    <div>Files failed: {$this->failedFiles}</div>
                </div>
            </div>
        HTML);
        return null;
    }

    private function formatDiff(string $oldContent, string $newContent): string
    {
        $oldLines = explode("\n", $oldContent);
        $newLines = explode("\n", $newContent);

        $diff = [];

        // Add header
        $diff[] = "\e[34m@@ file @@\e[0m\n";

        $changes = [];
        $inChange = false;
        $changeBuffer = [];

        for ($i = 0; $i < max(count($oldLines), count($newLines)); $i++) {
            $oldLine = $oldLines[$i] ?? '';
            $newLine = $newLines[$i] ?? '';

            if ($oldLine !== $newLine) {
                if (!$inChange) {
                    $inChange = true;
                    // Add some context before
                    for ($j = max(0, $i - 2); $j < $i; $j++) {
                        if (isset($oldLines[$j])) {
                            $changeBuffer[] = "\e[90m {$this->escapeLine($oldLines[$j])}\e[0m\n";
                        }
                    }
                }
                if ($oldLine && $oldLine !== $newLine) {
                    $changeBuffer[] = "\e[31m-{$this->escapeLine($oldLine)}\e[0m\n";
                }
                if ($newLine && $oldLine !== $newLine) {
                    $changeBuffer[] = "\e[32m+{$this->escapeLine($newLine)}\e[0m\n";
                }
            } elseif ($inChange) {
                // Add some context after
                for ($j = $i; $j < min($i + 2, count($oldLines)); $j++) {
                    if (isset($oldLines[$j])) {
                        $changeBuffer[] = "\e[90m {$this->escapeLine($oldLines[$j])}\e[0m\n";
                    }
                }
                $changes[] = $changeBuffer;
                $changeBuffer = [];
                $inChange = false;
            }
        }

        if ($changeBuffer !== []) {
            $changes[] = $changeBuffer;
        }

        // Format each change block
        foreach ($changes as $index => $change) {
            if ($index > 0) {
                $diff[] = "\n";
            }
            $diff = array_merge($diff, $change);
            $diff[] = "\n";
        }

        return implode('', $diff);
    }

    private function escapeLine(string $line): string
    {
        // First decode any existing HTML entities
        $line = html_entity_decode($line, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove any special characters that might interfere with terminal output
        $line = str_replace(
            ['&', '<', '>', "'", '"', '→'],
            ['&', '<', '>', "'", '"', '->'],
            $line
        );

        return rtrim($line);
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

    private function formatCode(string $code): string
    {
        // Basic formatting fixes
        $code = preg_replace('/\n{3,}/', "\n\n", $code); // Remove excess blank lines
        $code = preg_replace('/,\s*\]/', "\n    ]", (string) $code); // Format array closing
        $code = preg_replace('/\[\s*([\'"])/', "[\n        $1", (string) $code); // Format array opening
        $code = preg_replace('/,\s*([\'"])/', ",\n        $1", (string) $code); // Format array items

        // Add spacing after namespace
        $code = preg_replace('/^(namespace .*?;)\n/', "$1\n\n", (string) $code, 1);

        // Add spacing before class
        $code = preg_replace('/(use .*?;)\n*(class)/', "$1\n\n$2", (string) $code);

        // Add spacing between use statements and traits
        $code = preg_replace('/(use .*?;)\n*(use \w+(?:,\s*\w+)*;)/', "$1\n\n$2", (string) $code);

        // Add spacing between methods
        $code = preg_replace('/}\n*(public|private|protected)/', "}\n\n$1", (string) $code);

        // Add spacing after doc blocks
        $code = preg_replace('/\*\/\n*(\s*[private|protected|public])/', "*/\n\n$1", (string) $code);

        return $code;
    }

    private function hasUseStatement(array $statements, string $class): bool
    {
        $nodeFinder = new NodeFinder();
        return (bool) $nodeFinder->findFirst($statements, fn($node): bool => $node instanceof Use_ &&
               $node->uses[0]->name->toString() === $class);
    }

    private function hasTraitUse(Class_ $class, string $traitName): bool
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof TraitUse) {
                foreach ($stmt->traits as $trait) {
                    if ($trait->getLast() === $traitName) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function hasMethod(Class_ $class, string $methodName): bool
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof ClassMethod &&
                $stmt->name->toString() === $methodName) {
                return true;
            }
        }
        return false;
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
}
