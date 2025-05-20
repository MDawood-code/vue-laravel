<?php

namespace App\Http\Controllers\API;

use Maatwebsite\Excel\Validators\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Events\ProductCategoriesForOdoo;
use App\Events\ProductCategoryForOdooCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportRequest;
use App\Http\Requests\StoreProductCategoryRequest;
use App\Http\Requests\UpdateProductCategoryRequest;
use App\Http\Resources\ProductCategoryResource;
use App\Http\Resources\ProductCategoryWithProductsResource;
use App\Imports\ProductCategoriesImport;
use App\Models\CustomFeature;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group Customer
 *
 * @subgroup ProductCategory
 *
 * @subgroupDescription APIs for managing ProductCategory
 */
class ProductCategoriesController extends Controller
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
        $this->authorize('viewAny', ProductCategory::class);

        $product_categories = ProductCategory::where('company_id', $this->loggedInUser->company_id)
            ->orderBy('order')
            ->orderBy('name')
            ->get()->keyBy->id;

        return response()->json([
            'success' => true,
            'message' => 'Product Categories List Response',
            'data' => [
                'product_categories' => ProductCategoryResource::collection($product_categories),
            ],
        ], 200);
    }

    public function categoriesWithProducts(): JsonResponse
    {
        $this->authorize('viewAny', ProductCategory::class);

        $product_categories = ProductCategory::where('company_id', $this->loggedInUser->company_id)
            ->orderBy('order')
            ->orderBy('name')
            ->get()->keyBy->id;

        return response()->json([
            'success' => true,
            'message' => 'Categories with Products List Response',
            'data' => [
                'product_categories' => ProductCategoryWithProductsResource::collection($product_categories),
            ],
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     */
    public function store(StoreProductCategoryRequest $request)
    {
        $this->authorize('create', ProductCategory::class);

        $validated = $request->safe()->except(['odoo_reference_id', 'order']);

        $product_category = new ProductCategory;
        $product_category->fill($validated);
        $product_category->company_id = $this->loggedInUser->company_id;
        if ($this->loggedInUser->company->hasOdooIntegration()) {
            $product_category->odoo_reference_id = $validated['odoo_reference_id'];
        }
        $product_category->save();

        ProductCategoryForOdooCreated::dispatchIf(
            $this->loggedInUser->company->hasOdooIntegration() && CustomFeature::where('title', 'Odoo Integration')->where('status', true)->exists(),
            $product_category
        );

        return response()->json([
            'success' => true,
            'message' => 'Product Category Added Successfully!',
            'data' => ['product_category' => $product_category],
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return JsonResponse
     */
    public function update(UpdateProductCategoryRequest $request, ProductCategory $product_category)
    {
        $this->authorize('update', $product_category);

        $product_category->name = $request->name;
        $product_category->name_ar = $request->name_ar ?? $product_category->name_ar;
        $product_category->order = $request->order ?? 1;
        if ($this->loggedInUser->company->hasOdooIntegration()) {
            $product_category->odoo_reference_id = $request->odoo_reference_id;
        }
        $product_category->save();

        ProductCategoryForOdooCreated::dispatchIf(
            $this->loggedInUser->company->hasOdooIntegration() && CustomFeature::where('title', 'Odoo Integration')->where('status', true)->exists(),
            $product_category
        );

        return response()->json([
            'success' => true,
            'message' => 'Product Category Updated Successfully!',
            'data' => ['product_category' => $product_category],
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductCategory $product_category): JsonResponse
    {
        $this->authorize('delete', $product_category);

        if ($product_category->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Product Category cannot be deleted due to existence of related resources.',
                'data' => [],
            ], 500);
        }

        $product_category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product Category has been deleted.',
            'data' => [],
        ], 200);
    }

    /**
     * Export categories template.
     *
     * @return BinaryFileResponse
     */
    public function exportCategoriesTemplate()
    {
        $this->authorize('viewAny', ProductCategory::class);

        return response()->download(public_path('templates/Categories.xlsx'));
    }

    /**
     * Import categories from file.
     */
    public function import(ImportRequest $request): JsonResponse
    {
        $this->authorize('create', ProductCategory::class);

        try {
            $file = $request->file('attachment');

            if ($file instanceof UploadedFile) {
                Excel::import(new ProductCategoriesImport, $file);
            } else {
                return response()->json(['success' => false, 'message' => 'Invalid file upload.'], 400);
            }
            ProductCategoriesForOdoo::dispatchIf(CustomFeature::where('title', 'Odoo Integration')->where('status', true)->exists(), $this->loggedInUser->company);
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
            'message' => 'Product Categories have been successfully imported.',
            'data' => [],
        ], 200);
    }

    /**
     * Send to Odoo.
     */
    public function sendToOdoo(ProductCategory $category): JsonResponse
    {
        $this->authorize('view', $category);

        ProductCategoryForOdooCreated::dispatchIf(
            $this->loggedInUser->company->hasOdooIntegration() && CustomFeature::where('title', 'Odoo Integration')->where('status', true)->exists(),
            $category
        );

        return response()->json([
            'success' => true,
            'message' => 'Product Category is being sent to Odoo.',
            'data' => [],
        ], 200);
    }
}
