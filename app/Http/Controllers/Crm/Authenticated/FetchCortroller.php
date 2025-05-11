<?php

namespace App\Http\Controllers\Crm\Authenticated;

use App\Http\Controllers\Controller;
use App\Models\AssignedJobs;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use App\Models\AuthorizedBrands;
use App\Models\Branches;
use App\Models\Brands;
use App\Models\Category;
use App\Models\Services;
use App\Models\Complaint;
use Illuminate\Http\Request;

class FetchCortroller extends Controller
{

    public function fetchBrands()
    {
        $brands = Brands::select('unique_id', 'name', 'logo')->get();
        return response()->json($brands);
    }
    public function fetchBranches()
    {
        $branches = Branches::select('id as value', 'name as label', 'branch_contact_no', 'branch_address', 'image')->get();
        return response()->json($branches);
    }
    public function fetchCategories()
    {
        $categories = Category::select('id as value', 'name as label', 'image', 'hero_image', 'description', 'keywords')->get();
        return response()->json($categories);
    }
    public function fetchServices(Request $request)
    {
        $query = Services::select('id as value', 'name as label', 'image');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $services = $query->get();
        return response()->json($services);
    }
    public function fetchAuthorizedBrands()
    {
        $authorizedBrands = AuthorizedBrands::select('id as value', 'name as label', 'image')->where('status', 'active')->get();
        return response()->json($authorizedBrands);
    }
    public function fetchCountries(Request $request)
    {
        $countries = DB::table('countries')->select('id as value', 'name as label', 'image')->get();
        return response()->json($countries);
    }
    public function fetchStates(Request $request)
    {
        $statesQuery = DB::table('states')->select('id as value', 'name as label');
        if ($request->has('country_id')) {
            $statesQuery->where('country_id', $request->country_id);
        }
        $states = $statesQuery->get();
        return response()->json($states);
    }
    public function fetchCities(Request $request)
    {
        $citiesQuery = DB::table('cities')->select('id as value', 'name as label');
        if ($request->has('state_id')) {
            $citiesQuery->where('state_id', $request->state_id);
        }
        $cities = $citiesQuery->get();
        return response()->json($cities);
    }
    public function fetchWorkers(Request $request)
    {
        $query = Staff::select('id as value', 'username as label', 'profile_image as image');
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }
        $workers = $query->get();
        return response()->json($workers);
    }

}
