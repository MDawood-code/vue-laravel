<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\HelpdeskTicket\UpdateHelpdeskTicketRequest;
use App\Http\Resources\HelpdeskTicketCollection;
use App\Http\Resources\HelpdeskTicketResource;
use App\Models\HelpdeskTicket;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin
 *
 * @subgroup HelpdeskTicket
 *
 * @subgroupDescription APIs for managing HelpdeskTicket
 */
class HelpdeskTicketController extends Controller
{
    /**
     * Display a listing of the helpdesk tickets.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('adminViewAny', HelpdeskTicket::class);

        $new_tickets_count = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_CREATED)->count();
        $in_progress_tickets_count = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_IN_PROGRESS)->count();
        $done_tickets_count = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_DONE)->count();
        $closed_tickets_count = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_CLOSED)->count();

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk Tickets Counts',
            'data' => [
                'new_tickets_count' => $new_tickets_count,
                'in_progress_tickets_count' => $in_progress_tickets_count,
                'done_tickets_count' => $done_tickets_count,
                'closed_tickets_count' => $closed_tickets_count,
            ],
        ]);
    }

    /**
     * Display a listing of the new helpdesk tickets.
     */
    public function newTickets(Request $request): JsonResponse
    {
        $this->authorize('adminViewAny', HelpdeskTicket::class);

        $new_tickets = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_CREATED)->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk New Tickets',
            'data' => new HelpdeskTicketCollection($new_tickets),
        ]);
    }

    /**
     * Display a listing of the in-progress helpdesk tickets.
     */
    public function inProgressTickets(Request $request): JsonResponse
    {
        $this->authorize('adminViewAny', HelpdeskTicket::class);

        $in_progress_tickets = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_IN_PROGRESS)->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk In Progress Tickets',
            'data' => new HelpdeskTicketCollection($in_progress_tickets),
        ]);
    }

    /**
     * Display a listing of the done helpdesk tickets.
     */
    public function doneTickets(Request $request): JsonResponse
    {
        $this->authorize('adminViewAny', HelpdeskTicket::class);

        $done_tickets = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_DONE)->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk Done Tickets',
            'data' => new HelpdeskTicketCollection($done_tickets),
        ]);
    }

    /**
     * Display a listing of the closed helpdesk tickets.
     */
    public function closedTickets(Request $request): JsonResponse
    {
        $this->authorize('adminViewAny', HelpdeskTicket::class);

        $closed_tickets = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_CLOSED)->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk Closed Tickets',
            'data' => new HelpdeskTicketCollection($closed_tickets),
        ]);
    }

    /**
     * Display a listing of the late helpdesk tickets.
     */
    public function lateTickets(Request $request): JsonResponse
    {
        $this->authorize('adminViewAny', HelpdeskTicket::class);

        $closed_tickets = $this->helpdeskQuery()
            ->where('status', HELPDESK_TICKET_CREATED)
            ->where('created_at', '<=', Carbon::now()->subHours(24))
            ->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk Late Tickets',
            'data' => new HelpdeskTicketCollection($closed_tickets),
        ]);
    }

    /**
     * Display a listing of the delayed helpdesk tickets.
     */
    public function delayedTickets(Request $request): JsonResponse
    {
        $this->authorize('adminViewAny', HelpdeskTicket::class);

        $closed_tickets = $this->helpdeskQuery()
            ->where('status', HELPDESK_TICKET_IN_PROGRESS)
            ->where('status_updated_at', '<=', Carbon::now()->subHours(48))
            ->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk Delayed Tickets',
            'data' => new HelpdeskTicketCollection($closed_tickets),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateHelpdeskTicketRequest $request, HelpdeskTicket $helpdesk_ticket): JsonResponse
    {
        $this->authorize('adminUpdate', $helpdesk_ticket);

        $helpdesk_ticket->update($request->safe()->only(['status', 'issue_type_id', 'issue_comment']));
        if ($helpdesk_ticket->wasChanged('status')) {
            $helpdesk_ticket->status_updated_at = Carbon::now()->toDateTimeString();
            $helpdesk_ticket->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk Ticket status updated Successfully!',
            'data' => [
                'helpdesk_ticket' => new HelpdeskTicketResource($helpdesk_ticket),
            ],
        ], 200);
    }

    /** @return Builder<HelpdeskTicket> */
    private function helpdeskQuery(): Builder
    {
        $query = HelpdeskTicket::query();

        if (user_is_staff()) {
            $userId = auth()->id();
            $query->where(function ($q) use ($userId): void {
                $q->where('assigned_to', $userId)
                    ->orWhere('manage_by', $userId);
            });
        }

        return $query;
    }
}
