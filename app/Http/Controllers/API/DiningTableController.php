<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiningTable\StoreDiningTableRequest;
use App\Http\Requests\DiningTable\UpdateDiningTableRequest;
use App\Http\Resources\DiningTableResource;
use App\Models\DiningTable;
use App\Models\User;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @group Customer
 *
 * @subgroup DiningTable
 *
 * @subgroupDescription APIs for managing DiningTable
 */
class DiningTableController extends Controller
{
    protected ?User $loggedInUser;

    public function __construct(protected QrCodeService $qrCodeService)
    {
        $this->loggedInUser = auth()->guard('api')->user();
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DiningTable::class);
        /**
         * - If the user is a company owner and branch_id is present in the request, it filters the dining tables by the provided branch_id.
         *- If the user is a company owner and branch_id is not present in the request, it returns all dining tables for all branches of the company.
         *- If the user is an employee, it returns the dining tables of the employee's branch only, ignoring any branch_id in the request.
         */
        $diningTablesQuery = DiningTable::query()->when($request->boolean('is_drive_thru'), function ($query): void {
            $query->where('is_drive_thru', request()->boolean('is_drive_thru'));
        });
        if (user_is_company_owner()) {
            $diningTablesQuery = $diningTablesQuery->when($request->integer('branch_id'), function ($query): void {
                $query->where('branch_id', request()->integer('branch_id'));
            })->when(! $request->has('branch_id'), function ($query): void {
                $query->whereHas('branch', function ($query): void {
                    $query->where('company_id', $this->loggedInUser->company_id);
                });
            });
        } else {
            $diningTablesQuery = $diningTablesQuery->where('branch_id', $this->loggedInUser->branch_id);
        }

        $diningTables = $diningTablesQuery->with('branch')
            ->orderBy('name')
            ->get()->keyBy->id;

        return response()->json([
            'success' => true,
            'message' => 'Dining Tables List Response',
            'data' => [
                'dining_tables' => DiningTableResource::collection($diningTables),
            ],
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     */
    public function store(StoreDiningTableRequest $request)
    {
        $this->authorize('create', DiningTable::class);

        $diningTable = DiningTable::create($request->validated());

        if (hasActiveQrOrderingAddon($this->loggedInUser)) {
            $diningTable->qr_code_path = $this->qrCodeService->generateQrCodeWithLabel(
                content: getFrontendQrOrderingUrl($request->branch_id, $diningTable->is_drive_thru, $diningTable->id),
                label: $diningTable->name,
            );
            $diningTable->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Dining Table Added Successfully!',
            'data' => [
                'dining_table' => new DiningTableResource($diningTable),
            ],
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return JsonResponse
     */
    public function update(UpdateDiningTableRequest $request, DiningTable $diningTable)
    {
        $this->authorize('update', $diningTable);
        $diningTable->update($request->validated());
        if ($diningTable->wasChanged('name') && hasActiveQrOrderingAddon($this->loggedInUser)) {
            if (! is_null($diningTable->qr_code_path)) {
                Storage::delete(str_replace('/storage', 'public', $diningTable->qr_code_path));
            }

            $diningTable->qr_code_path = $this->qrCodeService->generateQrCodeWithLabel(
                content: getFrontendQrOrderingUrl($request->branch_id, $diningTable->is_drive_thru, $diningTable->id),
                label: $diningTable->name,
            );
            $diningTable->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Dining Table Updated Successfully!',
            'data' => [
                'dining_table' => new DiningTableResource($diningTable),
            ],
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DiningTable $diningTable): JsonResponse
    {
        $this->authorize('delete', $diningTable);

        $diningTable->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dining table has been deleted.',
            'data' => [],
        ], 200);
    }
}
