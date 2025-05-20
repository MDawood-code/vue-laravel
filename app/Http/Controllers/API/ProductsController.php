<?php

namespace App\Http\Controllers\API;

use Maatwebsite\Excel\Validators\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Events\ProductForOdooCreated;
use App\Events\ProductsForOdoo;
use App\Exports\ProductsTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Imports\ProductsImport;
use App\Models\CustomFeature;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Image;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group Customer
 *
 * @subgroup Product
 *
 * @subgroupDescription APIs for managing Product
 */
class ProductsController extends Controller
{
    protected ?User $loggedInUser;

    public function __construct()
    {
        $this->loggedInUser = auth()->guard('api')->user();
    }

    /**
     * Display a listing of the resource.
     */
    // public function index(): JsonResponse
    // {
    //     $this->authorize('viewAny', Product::class);

    //     $product = Product::where('company_id', $this->loggedInUser->company_id)
    //         ->with('stocks')
    //         ->orderBy('name')
    //         ->get()->keyBy->id;

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Products List Response',
    //         'data' => [
    //             'products' => ProductResource::collection($product),
    //         ],
    //     ], 200);
    // }
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);
    
        $unitId = $request->unit_id;
        $categoryId = $request->category_id;
    
        // Adjust the query to use relationships for filtering
        $query = Product::where('company_id', $this->loggedInUser->company_id)
            ->when($unitId, function ($q) use ($unitId) {
                $q->whereHas('unit', function ($query) use ($unitId) {
                    $query->where('id', $unitId);
                });
            })
            ->when($categoryId, function ($q) use ($categoryId) {
                $q->whereHas('category', function ($query) use ($categoryId) {
                    $query->where('id', $categoryId);
                });
            })
            ->with(['stocks', 'unit', 'category']) // Include related models for efficient loading
            ->orderBy('name');
    
        // Execute the query and get the results
        $products = $query->get()->keyBy->id;
    
        return response()->json([
            'success' => true,
            'message' => 'Products List Response',
            'data' => [
                'products' => ProductResource::collection($products),
            ],
        ], 200);
    }
    
    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     */
    public function store(StoreProductRequest $request)
    {
        $this->authorize('create', Product::class);

        $image_can_be_uploaded = false;
        $image_errors = ['Product Image is missing.'];

        // Validate and update Logo
        if ($request->file('image')) {
            $validator = Validator::make($request->all(), [
                'image' => 'required|image',
            ]);

            $image_can_be_uploaded = true;

            if ($validator->fails()) {
                $image_can_be_uploaded = false;
                $image_errors = $validator->messages()->toArray();
            }
        }

        $product = new Product;
        $product->name = $request->name;
        $product->name_en = $request->name_en;
        $product->price = $request->price;
        $product->barcode = $request->barcode ?? null;
        // if the company is tax exempt, then the product will also be exempted from tax
        // otherwise, depends on request data
        $product->is_taxable = $this->loggedInUser->company->is_vat_exempt ? BOOLEAN_FALSE : ($request->is_taxable ?? BOOLEAN_FALSE);
        $product->product_category_id = $request->category_id;
        $product->product_unit_id = $request->unit_id;
        if ($image_can_be_uploaded) {
            // Get File
            /** @var UploadedFile $image */
            $image = $request->file('image');
            // Generate Random Name
            $file_name = Str::random(14).'_'.time().'.'.$image->extension();
            // Set File Path
            $access_path = 'public/product_images/'.$this->loggedInUser->id;
            $file_path = storage_path('app/'.$access_path);
            // Create directory if not exists
            if (! is_dir($file_path)) {
                mkdir($file_path, 0775, true);
            }
            // Create Image Object
            $img = Image::make($image->path());
            // Resize, Crop and Save
            $img->fit(500)->save($file_path.'/'.$file_name);
            // Save Path to Product
            $product->image = $access_path.'/'.$file_name;
        }
        $product->company_id = $this->loggedInUser->company_id;
        if ($this->loggedInUser->company->hasOdooIntegration()) {
            $product->odoo_reference_id = $request->odoo_reference_id;
        }

        if (hasActiveQrOrderingAddon($this->loggedInUser) && $request->has('is_qr_product')) {
            $product->is_qr_product = $request->boolean('is_qr_product');
        }

        $product->is_stockable = (bool) $request->is_stockable;
        $product->save();
        if (hasActiveStockAddon($this->loggedInUser->company->owner)) {
            $product->createStockForProduct();
        }

        ProductForOdooCreated::dispatchIf(
            $this->loggedInUser->company->hasOdooIntegration() && CustomFeature::where('title', 'Odoo Integration')->where('status', true)->exists(),
            $product
        );

        $response_array = [
            'success' => true,
            'message' => 'Product has been Added Successfully!',
            'data' => ['product' => new ProductResource($product)],
        ];

        // If Image has some issue, will add product but warn user
        if (! $image_can_be_uploaded && $request->file('image')) {
            $response_array['message'] = 'Product has been Added with some errors!';
            $response_array['data']['errors'] = $image_errors;
        }

        return response()->json($response_array, 201);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return JsonResponse
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $this->authorize('update', $product);

        $image_can_be_uploaded = false;
        $image_errors = ['Product Image is missing.'];

        // Validate and update Logo
        if ($request->file('image')) {
            $validator = Validator::make($request->all(), [
                'image' => 'required|image',
            ]);

            $image_can_be_uploaded = true;

            if ($validator->fails()) {
                $image_can_be_uploaded = false;
                $image_errors = $validator->messages()->toArray();
            }
        }

        $product->name = $request->name;
        $product->name_en = $request->name_en;
        $product->price = $request->price;
        $product->barcode = $request->barcode ?? null;
        $product->is_taxable = $request->is_taxable ?? BOOLEAN_FALSE;
        $product->product_category_id = $request->category_id;
        $product->product_unit_id = $request->unit_id;
        if ($image_can_be_uploaded) {
            // Get File
            /** @var UploadedFile $image */
            $image = $request->file('image');
            // Generate Random Name
            $file_name = Str::random(14).'_'.time().'.'.$image->extension();
            // Set File Path
            $access_path = 'public/product_images/'.$this->loggedInUser->id;
            $file_path = storage_path('app/'.$access_path);
            // Create directory if not exists
            if (! is_dir($file_path)) {
                mkdir($file_path, 0775, true);
            }
            // Create Image Object
            $img = Image::make($image->path());
            // Resize, Crop and Save
            $img->fit(500)->save($file_path.'/'.$file_name);
            // Save Path to Product
            $product->image = $access_path.'/'.$file_name;
        }
        $product->company_id = $this->loggedInUser->company_id;
        if ($this->loggedInUser->company->hasOdooIntegration()) {
            $product->odoo_reference_id = $request->odoo_reference_id;
        }

        if (hasActiveQrOrderingAddon($this->loggedInUser) && $request->has('is_qr_product')) {
            $product->is_qr_product = $request->boolean('is_qr_product');
        }

        $product->is_stockable = (bool) $request->is_stockable;
        $product->save();

        if (hasActiveStockAddon($this->loggedInUser->company->owner)) {
            if ($product->wasChanged('is_stockable') && ! $request->is_stockable) {
                $product->stocks()->delete();
            } elseif ($product->wasChanged('is_stockable') && $request->is_stockable) {
                $product->createStockForProduct();
            }
        }

        ProductForOdooCreated::dispatchIf(
            $this->loggedInUser->company->hasOdooIntegration() && CustomFeature::where('title', 'Odoo Integration')->where('status', true)->exists(),
            $product
        );

        $response_array = [
            'success' => true,
            'message' => 'Product has been Updated Successfully!',
            'data' => ['product' => new ProductResource($product)],
        ];

        // If Image has some issue, will add product but warn user
        if (! $image_can_be_uploaded && $request->file('image')) {
            $response_array['message'] = 'Product has been Updated with some errors!';
            $response_array['data']['errors'] = $image_errors;
        }

        return response()->json($response_array, 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);
        $productStocks = $product->stocks();
        $productStocks->delete();
        $product->delete();


        return response()->json([
            'success' => true,
            'message' => 'Product has been deleted.',
            'data' => [],
        ], 200);
    }

    /**
     * Export products template.
     *
     * @return BinaryFileResponse|JsonResponse
     */
    public function exportProductsTemplate()
    {
        $this->authorize('create', Product::class);

        if ($this->loggedInUser->company->productUnits()->count() === 0 || $this->loggedInUser->company->productCategories()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'First upload units and categories please.',
                'data' => [],
            ], 400);
        }

        return Excel::download(new ProductsTemplateExport, 'products.xlsx');
    }

    /**
     * Import products from file.
     */
    public function import(ImportRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        try {
            $file = $request->file('attachment');
            if ($file instanceof UploadedFile) {
                Excel::import(new ProductsImport, $file);
            } else {
                return response()->json(['success' => false, 'message' => 'Invalid file upload.'], 400);
            }
            ProductsForOdoo::dispatchIf(CustomFeature::where('title', 'Odoo Integration')->where('status', true)->exists(),
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
            'message' => 'Products have been successfully imported.',
            'data' => [],
        ], 200);
    }

    /**
     * Send to Odoo.
     */
    public function sendToOdoo(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        ProductForOdooCreated::dispatchIf(
            $this->loggedInUser->company->hasOdooIntegration() && CustomFeature::where('title', 'Odoo Integration')->where('status', true)->exists(),
            $product
        );

        return response()->json([
            'success' => true,
            'message' => 'Product is being sent to Odoo.',
            'data' => [],
        ], 200);
    }
}
