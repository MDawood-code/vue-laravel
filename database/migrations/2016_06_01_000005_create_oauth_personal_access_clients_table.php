<?php

use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database schema.
     *
     * @var Builder
     */
    protected $schema;

    /**
     * Create a new migration instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->schema = Schema::connection($this->getConnection());
    }

    public function up(): void
    {
        $this->schema->create('oauth_personal_access_clients', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('client_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('oauth_personal_access_clients');
    }

    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return config('passport.storage.database.connection');
    }
};
