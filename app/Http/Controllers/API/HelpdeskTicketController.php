<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\UploadedFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\HelpdeskTicket\StoreHelpdeskTicketRequest;
use App\Http\Resources\HelpdeskTicketCollection;
use App\Http\Resources\HelpdeskTicketResource;
use App\Http\Traits\FileUploadTrait;
use App\Models\HelpdeskTicket;
use App\Models\User;
use App\Notifications\HelpdeskTicketCreated;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * @group Customer
 *
 * @subgroup HeldeskTicket
 *
 * @subgroupDescription APIs for managing HeldeskTicket
 */
class HelpdeskTicketController extends Controller
{
    use FileUploadTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HelpdeskTicket::class);

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
     * Display a listing of the new tickets.
     */
    public function newTickets(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HelpdeskTicket::class);

        $new_tickets = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_CREATED)->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk New Tickets',
            'data' => new HelpdeskTicketCollection($new_tickets),
        ]);
    }

    /**
     * Display a listing of the inprogress tickets.
     */
    public function inProgressTickets(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HelpdeskTicket::class);

        $in_progress_tickets = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_IN_PROGRESS)->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk In Progress Tickets',
            'data' => new HelpdeskTicketCollection($in_progress_tickets),
        ]);
    }

    /**
     * Display a listing of the done tickets.
     */
    public function doneTickets(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HelpdeskTicket::class);

        $done_tickets = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_DONE)->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk Done Tickets',
            'data' => new HelpdeskTicketCollection($done_tickets),
        ]);
    }

    /**
     * Display a listing of the closed tickets.
     */
    public function closedTickets(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HelpdeskTicket::class);

        $closed_tickets = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_CLOSED)->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk Closed Tickets',
            'data' => new HelpdeskTicketCollection($closed_tickets),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreHelpdeskTicketRequest $request): JsonResponse
    {
        $this->authorize('create', HelpdeskTicket::class);

        // Get the authenticated user
        $user = Auth::user();
        $company = $user->company;

        $helpdesk_ticket = new HelpdeskTicket;
        $helpdesk_ticket->description = $request->description;

        if ($request->has('attachment')) {
            $file = $request->file('attachment');
            if ($file instanceof UploadedFile) {
                $helpdesk_ticket->attachment = $this->uploadFile($file, 'tickets');
            } else {
                return response()->json(['error' => 'Invalid file upload.'], 400);
            }
        }

        $reseller_number = $company->reseller_number;
        $helpdesk_ticket->created_by = (int) auth()->id();

        if ($reseller_number != '' && $reseller_number != null) {
            $reseller = User::where('reseller_number', $reseller_number)->first();
            $helpdesk_ticket->reseller_agent = $reseller->id;
            $helpdesk_ticket->manage_by = $reseller->id;
        }
        // Check if the company has an admin staff
        $adminStaff = $company->adminStaff;
        if ($adminStaff) {
            $helpdesk_ticket->assigned_to = $adminStaff->id;
        } elseif ($last_ticket = HelpdeskTicket::latest()->first()) {
            // Find next support agent whom this ticket is to be assigned
            // Find next support agent
            $next_agent = User::where('type', USER_TYPE_ADMIN_STAFF)
                ->where('is_support_agent', BOOLEAN_TRUE)
                ->where('id', '>', $last_ticket->assigned_to ?? 0)
                ->orderBy('id', 'asc')
                ->first();
            if ($next_agent) {
                $helpdesk_ticket->assigned_to = $next_agent->id;
            } else {
                $support_agent = User::where('type', USER_TYPE_ADMIN_STAFF)
                    ->where('is_support_agent', BOOLEAN_TRUE)
                    ->first();
                $helpdesk_ticket->assigned_to = $support_agent ? $support_agent->id : null;
            }
        } else {

            $support_agent = User::where('type', USER_TYPE_ADMIN_STAFF)
                ->where('is_support_agent', BOOLEAN_TRUE)
                ->first();
            $helpdesk_ticket->assigned_to = $support_agent ? $support_agent->id : null;
        }

        $helpdesk_ticket->status = HELPDESK_TICKET_CREATED;
        $helpdesk_ticket->status_updated_at = Carbon::now()->toDateTimeString();
        $helpdesk_ticket->save();

        Notification::send(
            User::where('type', USER_TYPE_ADMIN)
                ->orWhere('type', USER_TYPE_SUPER_ADMIN)
                ->orWhere('id', $helpdesk_ticket->supportAgent?->id)
                ->get(),
            new HelpdeskTicketCreated($helpdesk_ticket)
        );

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk Ticket created Successfully!',
            'data' => [
                'helpdesk_ticket' => new HelpdeskTicketResource($helpdesk_ticket),
            ],
        ], 201);
    }

    /** @return Builder<HelpdeskTicket> */
    private function helpdeskQuery(): Builder
    {
        return HelpdeskTicket::where('created_by', auth()->id());
    }
}
