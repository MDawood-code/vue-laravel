<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddonRequest;
use App\Http\Requests\UpdateAddonRequest;
use App\Http\Resources\AddonResource;
use App\Http\Traits\ApiResponseHelpers;
use App\Http\Traits\FileUploadTrait;
use App\Models\Addon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * @group Customer
 *
 * @subgroup Addon
 *
 * @subgroupDescription APIs for managing Addon
 */
class AddonController extends Controller
{
    use ApiResponseHelpers;
    use FileUploadTrait;
   /**
     * Display the list of addons without auth.
     */

    public function getAddons(): JsonResponse
    {
        // Base query to fetch addons with their relationships
        $addonsQuery = Addon::query()->with([
            'dependentAddons',
            'dependentAddons.activeCompanyAddons',
            'requiredByAddons',
            'requiredByAddons.activeCompanyAddons',
        ]);

        // Fetch the addons
        $addons = $addonsQuery->get();

        // Return response
        return $this->respondWithSuccess([
            'success' => true,
            'message' => 'Addons List Response',
            'data' => [
                'addons' => AddonResource::collection($addons),
            ],
        ]);
    }

    /**
     * Display the list of addons.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Addon::class);
    
        $authUser = auth()->guard('api')->user();
        $status = request()->input('status', 'all'); // Default to "all" if not provided.
    
        // Base query
        $addonsQuery = Addon::query()->with([
            'activeCompanyAddons',
            'dependentAddons' => function ($query) use ($authUser): void {
                $query->withCount(['activeCompanyAddons' => function ($query) use ($authUser): void {
                    $query->where('company_id', $authUser->company_id);
                }]);
            },
            'dependentAddons.activeCompanyAddons',
            'requiredByAddons' => function ($query) use ($authUser): void {
                $query->withCount(['activeCompanyAddons' => function ($query) use ($authUser): void {
                    $query->where('company_id', $authUser->company_id);
                }]);
            },
            'requiredByAddons.activeCompanyAddons',
        ]);
    
        // Apply additional filtering for company owners or employees
        if (user_is_company_owner() || user_is_employee()) {
            $addonsQuery = $addonsQuery->withCount(['activeCompanyAddons' => function ($query) use ($authUser): void {
                $query->where('company_id', $authUser->company_id);
            }]);
        }
    
        // Filter based on status
        if ($status === 'subscribe') {
            $addonsQuery->whereHas('activeCompanyAddons', function ($query) use ($authUser): void {
                $query->where('company_id', $authUser->company_id);
            });
        }elseif ($status === 'unsubscribe') {
            // Get addons where is_subscribed is false
            $addonsQuery->whereDoesntHave('activeCompanyAddons', function ($query) use ($authUser): void {
                $query->where('company_id', $authUser->company_id);
            });
        }
    
        // Fetch the addons
        $addons = $addonsQuery->get();
    
        // Return response
        return $this->respondWithSuccess([
            'success' => true,
            'message' => 'Addons List Response',
            'data' => [
                'addons' => AddonResource::collection($addons),
            ],
        ]);
    }
 
        /**
         * Create addon.
         */
        public function store(StoreAddonRequest $request): JsonResponse
        {
            $this->authorize('create', Addon::class);

            // Create the addon with the request data, excluding 'image' and 'icon'
            $addon = Addon::create($request->safe()->except(['image', 'icon']));

            // Handle the image file upload (if present)
            if ($request->hasFile('image')) {
                $file = $request->file('image');

                if ($file instanceof UploadedFile) {
                    $addon->image = $this->uploadFile($file, 'addons');
                } else {
                    // Handle the case where the file is not a valid UploadedFile
                    return response()->json(['error' => 'Invalid file upload.'], 400);
                }
            }
            // Handle the icon file upload (if present)
            if ($request->hasFile('icon')) {
                $file = $request->file('icon');

                if ($file instanceof UploadedFile) {
                    $addon->icon = $this->uploadFile($file, 'addons_icons');
                } else {
                    // Handle the case where the file is not a valid UploadedFile
                    return response()->json(['error' => 'Invalid file upload.'], 400);
                }
            }
            $addon->save();
            $addon->refresh();

            return $this->respondCreated([
                'success' => true,
                'message' => 'Addon added successfully',
                'data' => [
                    'addon' => new AddonResource($addon),
                ],
            ]);
        }
    // /**
    //  * Update the specified addon.
    //  */
    // public function update(UpdateAddonRequest $request, Addon $addon): JsonResponse
    // {
    //     $this->authorize('update', $addon);

    //     $addon->update($request->safe()->except('image'));

    //     // Check if price or discount has been updated
    //     if ($addon->wasChanged(['price', 'discount'])) {
    //         // Update price and addon for each active company addon
    //         $addon->activeCompanyAddons()->update([
    //             'price' => $addon->price,
    //             'discount' => $addon->discount,
    //         ]);
    //     }

    //     if ($request->hasFile('image')) { // Check if the file is present
    //         $file = $request->file('image');

    //         if ($file instanceof UploadedFile) {
    //             $addon->image = $this->uploadFile($file, 'addons');
    //             $addon->save();
    //         } else {
    //             // Handle the case where the file is not a valid UploadedFile
    //             return response()->json(['error' => 'Invalid file upload.'], 400);
    //         }
    //     }
    //     $addon->refresh();

    //     return $this->respondWithSuccess([
    //         'success' => true,
    //         'message' => 'Addons updated successfully',
    //         'data' => [
    //             'addon' => new AddonResource($addon),
    //         ],
    //     ]);
    // }
    /**
     * Update the specified addon.
     */
    public function update(UpdateAddonRequest $request, Addon $addon): JsonResponse
    {
        $this->authorize('update', $addon);

        // Update the addon with the request data, excluding 'image' and 'icon'
        $addon->update($request->safe()->except(['image', 'icon']));

        // Check if price or discount has been updated
        if ($addon->wasChanged(['price', 'discount'])) {
            // Update price and addon for each active company addon
            $addon->activeCompanyAddons()->update([
                'price' => $addon->price,
                'discount' => $addon->discount,
            ]);
        }

        // Handle the image file upload (if present)
        if ($request->hasFile('image')) {
            $file = $request->file('image');

            if ($file instanceof UploadedFile) {
                $addon->image = $this->uploadFile($file, 'addons');
            } else {
                // Handle the case where the file is not a valid UploadedFile
                return response()->json(['error' => 'Invalid file upload.'], 400);
            }
        }

       // Handle the icon file upload (if present)
       if ($request->hasFile('icon')) {
            $file = $request->file('icon');

            if ($file instanceof UploadedFile) {
                $addon->icon = $this->uploadFile($file, 'addons_icons');
            } else {
                // Handle the case where the file is not a valid UploadedFile
                return response()->json(['error' => 'Invalid file upload.'], 400);
            }
        }

        // Save the addon with the updated fields
        $addon->save();
        $addon->refresh();

        return $this->respondWithSuccess([
            'success' => true,
            'message' => 'Addon updated successfully',
            'data' => [
                'addon' => new AddonResource($addon),
            ],
        ]);
    }
}
