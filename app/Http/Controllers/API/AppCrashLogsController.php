<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppCrashLogRequest;
use App\Models\AppCrashLog;
use Illuminate\Http\JsonResponse;

/**
 * @group Customer
 *
 * @subgroup AppCrashLog
 *
 * @subgroupDescription APIs for managing AppCrashLog
 */
class AppCrashLogsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', AppCrashLog::class);

        $appCrashLogs = AppCrashLog::latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'AppCrashLogs List response.',
            'data' => [
                'AppCrashLogs' => $appCrashLogs,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAppCrashLogRequest $request): JsonResponse
    {
        $this->authorize('create', AppCrashLog::class);

        $appCrashLog = AppCrashLog::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'AppCrashLog has been saved successfully.',
            'data' => [
                'AppCrashLog' => $appCrashLog,
            ],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(AppCrashLog $appCrashLog): JsonResponse
    {
        $this->authorize('view', $appCrashLog);

        return response()->json([
            'success' => true,
            'message' => 'AppCrashLog response.',
            'data' => [
                'AppCrashLog' => $appCrashLog,
            ],
        ]);
    }
}
