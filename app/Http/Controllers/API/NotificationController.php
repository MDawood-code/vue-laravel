<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @group Customer
 *
 * @subgroup Notification
 *
 * @subgroupDescription APIs for managing Notifications
 */
class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Notifications',
            'data' => new NotificationCollection(auth()->user()->notifications()->paginate(perPage: PER_PAGE_RECORDS_SHORT)),
        ], 200);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(Request $request, DatabaseNotification $notification): JsonResponse
    {
        if ($notification->notifiable()->is(auth()->user())) {
            $notification->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Read successfully',
                'data' => [
                    'notification' => $notification,
                ],
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid notification',
            'data' => [],
        ], 403);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Read successfully',
            'data' => [],
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DatabaseNotification $notification): JsonResponse
    {
        if ($notification->notifiable()->is(auth()->user())) {
            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Delete successfully',
                'data' => [],
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid notification',
            'data' => [],
        ], 403);
    }
}
