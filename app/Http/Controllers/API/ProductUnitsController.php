<?php

namespace App\Http\Controllers\API;

use Maatwebsite\Excel\Validators\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Events\ProductUnitForOdooCreated;
use App\Events\ProductUnitsForOdoo;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportRequest;
use App\Http\Requests\StoreProductUnitRequest;
use App\Http\Requests\UpdateProductUnitRequest;
use App\Http\Resources\ProductUnitResource;
use App\Imports\ProductUnitsImport;
use App\Models\CustomFeature;
use App\Models\ProductUnit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group Customer
 *
 * @subgroup ProductUnit
 *
 * @subgroupDescription APIs for managing ProductUnit
 */
class ProductUnitsController extends Controller
{
    protected ?User $loggedInUser;

    public function __construct()
    {
        $this->loggedInUser = auth()->guard('api')->user();
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', ProductUnit::class);

        $product_units = ProductUnit::where('company_id', $this->loggedInUser->company_id)
            ->orderBy('name')
            ->get()->keyBy->id;

        return response()->json([
            'success' => true,
            'message' => 'Product Units List Response',
            'data' => [
                'product_units' => ProductUnitResource::collection($product_units),
            ],
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     */
    public function store(StoreProductUnitRequest $request)
    {
        $this->authorize('create', ProductUnit::class);

        $product_unit = new ProductUnit;
        $product_unit->name = $request->name;
        $product_unit->name_ar = $request->name_ar;
        $product_unit->company_id = $this->loggedInUser->company_id;
        if ($this->loggedInUser->company->hasOdooIntegration()) {
            $product_unit->odoo_reference_id = $request->odoo_reference_id;
        }
        $product_unit->save();

        ProductUnitForOdooCreated::dispatchIf(
            $this->loggedInUser->company->hasOdooIntegration() && CustomFeature::where('title', 'Odoo Integration')->where('status', true)->exists(),
            $product_unit
        );

        return response()->json([
            'success' => true,
            'message' => 'Product Unit Added Successfully!',
            'data' => ['product_unit' => $product_unit],
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return JsonResponse
     */
    public function update(UpdateProductUnitRequest $request, ProductUnit $product_unit)
    {
        $this->authorize('update', $product_unit);

        $product_unit->name = $request->name;
        $product_unit->name_ar = $request->name_ar ?? $product_unit->name_ar;
        if ($this->loggedInUser->company->hasOdooIntegration()) {
            $product_unit->odoo_reference_id = $request->odoo_reference_id;
        }
        $product_unit->save();

        ProductUnitForOdooCreated::dispatchIf(
            $this->loggedInUser->company->hasOdooIntegration() && CustomFeature::where('title', 'Odoo Integration')->where('status', true)->exists(),
            $product_unit
        );

        return response()->json([
            'success' => true,
            'message' => 'Product Unit Updated Successfully!',
            'data' => ['product_unit' => $product_unit],
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductUnit $product_unit): JsonResponse
    {
        $this->authorize('delete', $product_unit);

        if ($product_unit->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Product Unit cannot be deleted due to existence of related resources.',
                'data' => [],
            ], 500);
        }

        $product_unit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product Unit has been deleted.',
            'data' => [],
        ], 200);
    }

    /**
     * Export units template.
     *
     * @return BinaryFileResponse
     */
    public function exportUnitsTemplate()
    {
        $this->authorize('viewAny', ProductUnit::class);

        return response()->download(public_path('templates/Units.xlsx'));
    }

    public function import(ImportRequest $request): JsonResponse
    {
        $this->authorize('create', ProductUnit::class);

        try {
            $file = $request->file('attachment');

            if ($file instanceof UploadedFile) {
                Excel::import(new ProductUnitsImport, $file);
            } else {
                return response()->json(['success' => false, 'message' => 'Invalid file upload.'], 400);
            }
            ProductUnitsForOdoo::dispatchIf(CustomFeature::where('title', 'Odoo Integration')->where('status', true)->exists(),
                $this->loggedInUser->company
            );
        } catch (ValidationException $e) {
            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $key => $failure) {
                $errors[$key] = [
                    'row' => $failure->row(),
                    'message' => $failure->errors(),
                ];
            }

            return response()->json([
                'success' => false,
                'message' => 'Errors in your file.',
                'data' => [
                    'errors' => $errors,
                ],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product Units have been successfully imported.',
            'data' => [],
        ], 200);
    }

    /**
     * Send to Odoo.
     */
    public function sendToOdoo(ProductUnit $productUnit): JsonResponse
    {
        $this->authorize('view', $productUnit);

        ProductUnitForOdooCreated::dispatchIf(
            $this->loggedInUser->company->hasOdooIntegration() && CustomFeature::where('title', 'Odoo Integration')->where('status', true)->exists(),
            $productUnit
        );

        return response()->json([
            'success' => true,
            'message' => 'Product unit is being sent to Odoo.',
            'data' => [],
        ], 200);
    }
}
