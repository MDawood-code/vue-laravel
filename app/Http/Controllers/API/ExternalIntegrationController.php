<?php

namespace App\Http\Controllers\API;

use App\Events\CompanyBranchesForOdoo;
use App\Events\CompanyUsersForOdoo;
use App\Events\ProductCategoriesForOdoo;
use App\Events\ProductsForOdoo;
use App\Events\ProductTaxesForOdoo;
use App\Events\ProductUnitsForOdoo;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExternalIntegration\StoreExternalIntegrationRequest;
use App\Http\Resources\ExternalIntegrationResource;
use App\Http\Resources\ExternalIntegrationTypeResource;
use App\Models\Branch;
use App\Models\CustomFeature;
use App\Models\ExternalIntegration;
use App\Models\ExternalIntegrationType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @group Customer
 *
 * @subgroup ExternalIntegration
 *
 * @subgroupDescription APIs for managing ExternalIntegration
 */
class ExternalIntegrationController extends Controller
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
        $this->authorize('viewAny', ExternalIntegration::class);

        $externalIntegrations = $this->loggedInUser->company->externalIntegrations;

        return response()->json([
            'success' => true,
            'message' => 'External Integrations List Response',
            'data' => [
                'externalIntegrations' => ExternalIntegrationResource::collection($externalIntegrations),
            ],
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExternalIntegrationRequest $request): JsonResponse
    {
        $this->authorize('create', ExternalIntegration::class);

        $response = $this->test($request);
        if (! $response['success']) {
            return response()->json($response, 400);
        }

        $externalIntegration = $this->loggedInUser->company->externalIntegrations()->updateOrCreate(
            ['external_integration_type_id' => $request->external_integration_type_id],
            $request->safe()->only(['url', 'secret_key']),
        );

        if ($this->loggedInUser->company->hasOdooIntegration()) {
            $company = $this->loggedInUser->company;
            ProductTaxesForOdoo::dispatch($company);
            CompanyUsersForOdoo::dispatch($company);
            CompanyBranchesForOdoo::dispatch($company);
            ProductCategoriesForOdoo::dispatch($company);
            ProductUnitsForOdoo::dispatch($company);
            ProductsForOdoo::dispatch($company);
        }

        return response()->json([
            'success' => true,
            'message' => 'External Integration action success.',
            'data' => [
                'externalIntegration' => new ExternalIntegrationResource($externalIntegration),
            ],
        ], 200);
    }

    /**
     * Display a listing of the external integrations types.
     */
    public function types(): JsonResponse
    {
        $this->authorize('viewAny', ExternalIntegrationType::class);

        $externalIntegrationsTypes = ExternalIntegrationType::with('authUserExternalIntegrations')
            ->when(CustomFeature::where('title', 'Odoo Integration')->where('status', false)->exists(), function (Builder $query): void {
                $query->where('name', '!=', 'Odoo');
            })
            ->when(CustomFeature::where('title', 'Xero Integration')->where('status', false)->exists(), function (Builder $query): void {
                $query->where('name', '!=', 'Xero');
            })
            ->when(CustomFeature::where('title', 'Zoho Integration')->where('status', false)->exists(), function (Builder $query): void {
                $query->where('name', '!=', 'Zoho');
            })
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'message' => 'External Integrations Types List Response',
            'data' => [
                // 'externalIntegrationsTypes' => $externalIntegrationsTypes,
                'externalIntegrationsTypes' => ExternalIntegrationTypeResource::collection($externalIntegrationsTypes),
            ],
        ], 200);
    }

    /**
     * Test a specific connection
     */
    public function testConnection(StoreExternalIntegrationRequest $request): JsonResponse
    {
        $this->authorize('test', ExternalIntegration::class);

        $response = $this->test($request);
        if ($response['success']) {
            return response()->json($response, 200);
        }

        return response()->json($response, 400);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ExternalIntegration $externalIntegration): JsonResponse
    {
        $this->authorize('delete', $externalIntegration);

        // Set null for odoo_reference_id of products, categories and units
        ProductUnit::where('company_id', $this->loggedInUser->company_id)->update(['odoo_reference_id' => null]);
        ProductCategory::where('company_id', $this->loggedInUser->company_id)->update(['odoo_reference_id' => null]);
        Product::where('company_id', $this->loggedInUser->company_id)->update(['odoo_reference_id' => null]);
        User::where('company_id', $this->loggedInUser->company_id)->update(['odoo_reference_id' => null]);
        Branch::where('company_id', $this->loggedInUser->company_id)->update(['odoo_reference_id' => null]);

        // Delete external integration
        $externalIntegration->delete();

        return response()->json([
            'success' => true,
            'message' => 'External Integration connection deleted successful',
            'data' => [],
        ], 200);
    }

    /**
     * @return array<mixed>
     */
    private function test(StoreExternalIntegrationRequest $request): array
    {
        try {
            $externalResponse = Http::post($request->validated('url').'/connection', [
                'token' => $request->validated('secret_key'),
            ]);
            Log::debug('Test External Integration Connection');
            Log::debug($externalResponse->body());
            $externalResponse = json_decode($externalResponse->body());

            if ($externalResponse->result && $externalResponse->result->status == true) {
                return [
                    'success' => true,
                    'message' => 'External Integration connection test successful',
                    'data' => [],
                ];
            }

            return [
                'success' => false,
                'message' => 'External Integration connection test failed due to invalid credentials.',
                'data' => [],
            ];
        } catch (Throwable) {
            return [
                'success' => false,
                'message' => 'External Integration connection test failed',
                'data' => [],
            ];
        }
    }
}
