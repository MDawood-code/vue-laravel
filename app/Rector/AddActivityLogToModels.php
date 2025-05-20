<?php

namespace App\Rector;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Nop;
use Spatie\Activitylog\LogOptions;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Name;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Namespace_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Rector\NodeTypeResolver\NodeTypeResolver;
use Rector\PhpParser\Node\BetterNodeFinder;

final class AddActivityLogToModels extends AbstractRector
{
    public function __construct(
        private readonly BetterNodeFinder $betterNodeFinder
    ) {}

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Class_) {
            return null;
        }

        // Check if the class is a model
        if (!$this->isModelClass($node)) {
            return null;
        }

        // Handle backup if enabled
        if (getenv('BACKUP') && $this->file) {
            $this->handleBackup(
                (int) getenv('KEEP_BACKUPS') ?: 5
            );
        }

        // Add trait if not present
        $this->addTrait($node);

        // Add method if not present
        $this->addMethod($node);

        return $node;
    }

    private function isModelClass(Class_ $class): bool
    {
        // Check if class extends Illuminate\Database\Eloquent\Model
        if (!$class->extends instanceof Name) {
            return false;
        }

        $parentClass = $class->extends->toString();

        // Check direct Model class
        if ($parentClass === Model::class) {
            return true;
        }

        // Get namespace node to check for Model class alias
        $namespace = $this->betterNodeFinder->findFirstInstanceOf(
            $this->file->getNewStmts(),
            Namespace_::class
        );

        if ($namespace instanceof Node) {
            // Check if Model is aliased in use statements
            foreach ($namespace->stmts as $stmt) {
                if ($stmt instanceof Use_) {
                    foreach ($stmt->uses as $use) {
                        if ($use->name->toString() === Model::class) {
                            // If Model is aliased and matches parent class
                            if ($use->alias && $use->alias->toString() === $parentClass) {
                                return true;
                            }
                            // If Model is used directly and matches parent class
                            if (!$use->alias && $parentClass === 'Model') {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    private function addTrait(Class_ $class): void
    {
        $traitName = LogsActivity::class;

        // First check if trait already exists in any use statement
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof TraitUse) {
                foreach ($stmt->traits as $trait) {
                    if ($trait->toString() === $traitName) {
                        return;
                    }
                }
                // If we found a trait use statement but didn't return,
                // add our trait to the existing statement
                $stmt->traits[] = new FullyQualified($traitName);
                return;
            }
        }

        // If no trait use statement exists, create a new one
        $traitUseStmt = new TraitUse([
            new FullyQualified($traitName)
        ]);

        // Add the new trait use statement at the beginning of the class
        array_unshift($class->stmts, $traitUseStmt);
    }

    private function addMethod(Class_ $class): void
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && $stmt->name->toString() === 'getActivitylogOptions') {
                return;
            }
        }

        // Add a blank line by inserting a Nop (no operation) statement
        $class->stmts[] = new Nop();

        $method = new ClassMethod('getActivitylogOptions', [
            'flags' => Class_::MODIFIER_PUBLIC,
            'returnType' => new FullyQualified(LogOptions::class),
            'stmts' => [
                new Return_(
                    new MethodCall(
                        new MethodCall(
                            new StaticCall(
                                new FullyQualified(LogOptions::class),
                                'defaults'
                            ),
                            'logOnly',
                            [
                                new Arg(
                                    new Array_([
                                        new ArrayItem(new String_('*'))
                                    ])
                                )
                            ]
                        ),
                        'logOnlyDirty'
                    )
                ),
            ],
        ]);

        $class->stmts[] = $method;
    }

    private function handleBackup(int $keepBackups): void
    {
        if (!method_exists($this, 'enableBackup')) {
            return;
        }

        $this->enableBackup(true, $keepBackups);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add Spatie Activitylog imports, trait, and method to model classes.',
            [
                new CodeSample(
                    // code before
                    <<<'CODE_SAMPLE'
class User extends Model
{
}
CODE_SAMPLE
                    ,
                    // code after
                    <<<'CODE_SAMPLE'
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['*'])
            ->logOnlyDirty();
    }
}
CODE_SAMPLE
                ),
            ]
        );
    }
}
