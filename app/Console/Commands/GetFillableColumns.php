<?php

namespace App\Console\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class GetFillableColumns extends Command
{
    protected $signature = 'model:fillable {model}';

    protected $description = 'Get fillable columns of a model excluding id, created_at, and updated_at';

    public function handle(): void
    {
        $modelName = $this->argument('model');

        // Resolve the model class
        $modelClass = "App\\Models\\{$modelName}";

        if (! class_exists($modelClass)) {
            $this->error("Model {$modelName} does not exist.");

            return;
        }

        // Create an instance of the model
        $modelInstance = new $modelClass;

        if (! ($modelInstance instanceof Model)) {
            $this->error("Model {$modelName} is not a valid Eloquent model.");

            return;
        }

        // Get the table name
        $table = $modelInstance->getTable();

        // Get all columns from the table
        $columns = Schema::getColumnListing($table);

        // Exclude unwanted columns
        $fillable = array_diff($columns, ['id', 'created_at', 'updated_at']);

        $outPut = "protected \$fillable = [\n";
        foreach ($fillable as $column) {
            $outPut .= "\t'{$column}',\n";
        }
        $outPut .= ' ];';

        // Output the fillable array
        $this->info('Fillable columns:');
        $this->line($outPut);
    }
}
