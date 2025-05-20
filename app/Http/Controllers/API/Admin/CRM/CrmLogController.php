<?php

namespace App\Http\Controllers\API\Admin\CRM;

use App\Http\Controllers\Controller;
use App\Http\Resources\CrmLogCollection;
use App\Models\Company;
use App\Models\CrmLog;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin CRM
 *
 * @subgroup CRM Logs
 *
 * @subgroupDescription APIs for managing CRM Logs
 */
class CrmLogController extends Controller
{
    /**
     * Display a listing of the CrmLog resource.
     *
     * @queryParam page int Page number to show. Defaults to 1.
     */
    public function index(Company $company): JsonResponse
    {
        $this->authorize('viewAny', [CrmLog::class, $company]);
        $crmLogs = $company->crmLogs()->with('createdByUser')->latest()->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Logs',
            'data' => new CrmLogCollection($crmLogs),
        ]);
    }
}
