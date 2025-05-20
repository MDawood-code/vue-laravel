<?php

namespace App\Console\Commands;

use App\Models\HelpdeskTicket;
use App\Models\User;
use App\Notifications\HelpdeskTicketDelayed;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendDelayedTicketsNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'anypos:send-delayed-tickets-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notifications for late and delayed tickets';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $lateTickets = HelpdeskTicket::query()
            ->where('status', HELPDESK_TICKET_CREATED)
            ->where('created_at', '<=', Carbon::now()->subHours(24))
            ->get();
        $delayedTickets = HelpdeskTicket::query()
            ->where('status', HELPDESK_TICKET_IN_PROGRESS)
            ->where('status_updated_at', '<=', Carbon::now()->subHours(48))
            ->get();

        $lateTickets->each(function (HelpdeskTicket $helpdeskTicket, int $index): void {
            Notification::send(
                User::where('type', USER_TYPE_ADMIN)
                    ->orWhere('type', USER_TYPE_SUPER_ADMIN)
                    ->orWhere('id', $helpdeskTicket->supportAgent?->id)
                    ->get(),
                new HelpdeskTicketDelayed($helpdeskTicket, 'Late', '24 Hours')
            );
        });

        $delayedTickets->each(function (HelpdeskTicket $helpdeskTicket, int $index): void {
            Notification::send(
                User::where('type', USER_TYPE_ADMIN)
                    ->orWhere('type', USER_TYPE_SUPER_ADMIN)
                    ->orWhere('id', $helpdeskTicket->supportAgent?->id)
                    ->get(),
                new HelpdeskTicketDelayed($helpdeskTicket, 'Delayed', '48 Hours')
            );
        });

        return Command::SUCCESS;
    }
}
