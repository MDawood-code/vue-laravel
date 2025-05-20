<?php

use App\Models\IssueType;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_tickets', function (Blueprint $table): void {
            $table->id();
            $table->text('description');
            $table->string('attachment')->nullable();
            $table->tinyInteger('status')->default(HELPDESK_TICKET_CREATED);
            $table->foreignId('created_by');
            $table->foreignId('assigned_to')->nullable();
            $table->foreignIdFor(IssueType::class)->nullable();
            $table->text('issue_comment')->nullable();
            $table->timestamp('status_updated_at')->default(Carbon::now()->toDateTimeString());
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_tickets');
    }
};
