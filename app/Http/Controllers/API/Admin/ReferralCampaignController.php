<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReferralCampaign\StoreReferralCampaignRequest;
use App\Http\Resources\ReferralCampaignResource;
use App\Models\ReferralCampaign;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralCampaignController extends Controller
{
    /**
     * store newly referral campaign.
     */
    public function store(StoreReferralCampaignRequest $request, int|string $referral): JsonResponse
    {
        $this->authorize('adminDashboard', User::class);

        // Set status of existing campaigns to BOOLEAN_FALSE
        ReferralCampaign::where('referral_id', $referral)
            ->update(['status' => BOOLEAN_FALSE]);
        // Create the new referral campaign
        $data = $request->all();
        $data['referral_id'] = $referral;
        $data['status'] = BOOLEAN_TRUE;
        $referral_campaign = ReferralCampaign::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Referral Campaign Added Successfully!',
            'data' => [
                'referral_campaign' => new ReferralCampaignResource($referral_campaign),
            ],
        ], 201);
    }

    /**
     * Activate Referral Campaign.
     */
    public function activate(Request $request, int|string $referral, ReferralCampaign $referralCampaign): JsonResponse
    {
        $this->authorize('adminDashboard', User::class);
        // Set status of existing campaigns to BOOLEAN_FALSE
        ReferralCampaign::where('referral_id', $referral)
            ->update(['status' => BOOLEAN_FALSE]);

        $referralCampaign->status = BOOLEAN_TRUE;
        $referralCampaign->save();

        return response()->json([
            'success' => true,
            'message' => 'Referral Campaign Activated Successfully!',
            'data' => [
                'referral_campaign' => new ReferralCampaignResource($referralCampaign),
            ],
        ], 201);
    }

    /**
     * Deactivate Referral Campaign.
     */
    public function deactivate(Request $request, ReferralCampaign $referralCampaign): JsonResponse
    {
        $this->authorize('adminDashboard', User::class);
        $referralCampaign->status = BOOLEAN_FALSE;
        $referralCampaign->save();

        return response()->json([
            'success' => true,
            'message' => 'Referral Campaign Deactivated Successfully!',
            'data' => [
                'referral_campaign' => new ReferralCampaignResource($referralCampaign),
            ],
        ], 201);
    }
}
