<?php

namespace App\Http\Controllers\API\Admin\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\StoreActivityRequest;
use App\Http\Requests\Activity\UpdateActivityRequest;
use App\Http\Requests\Activity\UpdateActivityStatusRequest;
use App\Http\Resources\ActivityCollection;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\CommentCollection;
use App\Models\Activity;
use App\Models\Comment;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin CRM
 *
 * @subgroup Activities
 *
 * @subgroupDescription APIs for managing Activities
 */
class ActivityController extends Controller
{
    /**
     * List of Activities
     *
     * @queryParam page int Page number to show. Defaults to 1.
     * @queryParam status integer Filter activities by a given status by passing status. Optional.
     */
    public function index(Request $request, Company $company): JsonResponse
    {
        $this->authorize('viewAny', [Activity::class, $company]);

        $status = is_null($request->status) ? null : $request->integer('status');

        $activities = $company->activities()
            ->when($status, function (HasMany $query, ?int $status): void {
                $query->where('status', $status);
            })
            ->with('company', 'createdByUser', 'assignedToUser', 'comments.createdByUser')
            ->withCount('comments')
            ->latest()
            ->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Activities Collection',
            'data' => new ActivityCollection($activities),
        ]);
    }

    /**
     * List of All Activities
     *
     * Display all activities assigned to admin staff if admin staff
     *  or all activities if admin/super admin
     *
     * @queryParam page int Page number to show. Defaults to 1.
     * @queryParam status integer Filter activities by a given status by passing status. Optional.
     */
    public function all(Request $request): JsonResponse
    {
        $status = is_null($request->status) ? null : $request->integer('status');

        $activities = Activity::when(auth()->user()->isAdminStaff(), function (Builder $query): void {
            $query->where('assigned_to', auth()->id());
        })
            ->when($status, function (Builder $query, ?int $status): void {
                $query->where('status', $status);
            })
            ->with('company', 'createdByUser', 'assignedToUser', 'comments.createdByUser')
            ->withCount('comments')
            ->latest()
            ->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Activities Collection',
            'data' => new ActivityCollection($activities),
        ]);
    }

    /**
     * Store a crm Activity
     */
    public function store(StoreActivityRequest $request, Company $company): JsonResponse
    {
        $data = $request->validated() + ['created_by' => auth()->id()];
        $activity = $company->activities()->create($data);
        $activity->load('company', 'createdByUser', 'assignedToUser');

        return response()->json([
            'success' => true,
            'message' => 'Activity created successfully.',
            'data' => [
                'activity' => new ActivityResource($activity),
            ],
        ]);
    }

    /**
     * Display the specified Activity resource.
     */
    public function show(Company $company, Activity $activity): JsonResponse
    {
        $this->authorize('view', [$activity, $company->id]);
        $activity->load('company', 'createdByUser', 'assignedToUser', 'comments.createdByUser');

        return response()->json([
            'success' => true,
            'message' => 'Activity',
            'data' => [
                'activity' => new ActivityResource($activity),
            ],
        ]);
    }

    /**
     * Update the specified Activity resource in storage.
     */
    public function update(UpdateActivityRequest $request, Company $company, Activity $activity): JsonResponse
    {
        $this->authorize('update', [$activity, $company->id]);
        $activity->update($request->validated());
        $activity->load('company', 'createdByUser', 'assignedToUser', 'comments.createdByUser');

        return response()->json([
            'success' => true,
            'message' => 'Activity updated successfully.',
            'data' => [
                'activity' => new ActivityResource($activity),
            ],
        ]);
    }

    /**
     * Update status of the specified Activity resource in storage.
     */
    public function updateStatus(UpdateActivityStatusRequest $request, Company $company, Activity $activity): JsonResponse
    {
        $this->authorize('update', [$activity, $company->id]);
        $activity->status = $request->status;
        $activity->save();

        $activity->load('company', 'createdByUser', 'assignedToUser', 'comments.createdByUser');

        return response()->json([
            'success' => true,
            'message' => 'Activity',
            'data' => [
                'activity' => new ActivityResource($activity),
            ],
        ]);
    }

    /**
     * Remove the specified Activity resource from storage.
     */
    public function destroy(Company $company, Activity $activity): JsonResponse
    {
        $this->authorize('delete', [$activity, $company->id]);
        $activity->delete();

        return response()->json([
            'success' => true,
            'message' => 'Activity deleted successfully.',
            'data' => [],
        ]);
    }

    /**
     * Display comments of the given Activity.
     *
     * @queryParam page int Page number to show. Defaults to 1.
     */
    public function comments(Activity $activity): JsonResponse
    {
        $this->authorize('viewAny', [Comment::class, $activity->company]);
        $comments = $activity->comments()
            ->with('createdByUser')
            ->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Activity comments',
            'data' => new CommentCollection($comments),
        ]);
    }
}
