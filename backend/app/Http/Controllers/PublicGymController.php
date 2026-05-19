<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicGymController extends ApiController
{
    /**
     * Get a list of public gyms. Supports geolocation search.
     */
    public function index(Request $request)
    {
        $query = Tenant::where('status', 'active');

        // Optional Search by Name or City
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        // Optional Nearby Search using Haversine formula
        if ($request->filled('latitude') && $request->filled('longitude')) {
            $lat = (float) $request->latitude;
            $lng = (float) $request->longitude;

            $radius = $request->input('radius', 50); // Default 50 km radius

            $query->select('*')
                  ->selectRaw(
                      '( 6371 * acos( cos( radians(?) ) *
                        cos( radians( latitude ) )
                        * cos( radians( longitude ) - radians(?)
                        ) + sin( radians(?) ) *
                        sin( radians( latitude ) ) )
                      ) AS distance', [$lat, $lng, $lat]
                  )
                  ->having('distance', '<', $radius)
                  ->orderBy('distance');
        } else {
            $query->latest();
        }

        $gyms = $query->paginate(20);

        return $this->jsonResponse([
            'message' => 'Gyms retrieved successfully',
            'data' => $gyms
        ]);
    }

    /**
     * Get a specific gym by its slug for the public SEO page.
     */
    public function show($slug)
    {
        // Check if slug looks like a domain name (contains a dot)
        if (str_contains($slug, '.')) {
            $gym = Tenant::where('custom_domain', $slug)
                         ->where('status', 'active')
                         ->first();
        } else {
            $gym = Tenant::where('slug', $slug)
                         ->where('status', 'active')
                         ->first();
        }

        if (!$gym) {
            return $this->jsonResponse(['message' => 'Gym not found'], 404);
        }

        $dbPlans = \App\Models\MembershipPlan::where('tenant_id', $gym->id)->get()->map(function($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'price' => '₹' . number_format($p->price, 2),
                'features' => $p->features ?? []
            ];
        })->toArray();

        $pricingPlans = !empty($dbPlans) ? $dbPlans : ($gym->pricing_plans ?? []);

        return $this->jsonResponse([
            'message' => 'Gym retrieved successfully',
            'data' => [
                'name' => $gym->name,
                'slug' => $gym->slug,
                'description' => $gym->description,
                'logo_url' => $gym->logo_url,
                'address' => $gym->address,
                'city' => $gym->city,
                'state' => $gym->state,
                'zip' => $gym->zip,
                'country' => $gym->country,
                'phone' => $gym->phone,
                'email' => $gym->email,
                'website' => $gym->website,
                'latitude' => $gym->latitude,
                'longitude' => $gym->longitude,
                'gallery_images' => $gym->gallery_images ?? [],
                'services' => $gym->services ?? [],
                'website_enabled' => (bool) $gym->website_enabled,
                'website_template' => $gym->website_template,
                'seo_title' => $gym->seo_title,
                'seo_description' => $gym->seo_description,
                'seo_keywords' => $gym->seo_keywords,
                'custom_domain' => $gym->custom_domain,
                'custom_domain_verified' => (bool) $gym->custom_domain_verified,
                'opening_hours' => $gym->opening_hours ?? [],
                'social_links' => $gym->social_links ?? [],
                'banner_image' => $gym->banner_image,
                'trainers_data' => $gym->trainers_data ?? [],
                'pricing_plans' => $pricingPlans,
                'testimonials_data' => $gym->testimonials_data ?? [],
            ]
        ]);
    }

    /**
     * Submit client feedback / testimonials from the public landing page.
     */
    public function submitFeedback(Request $request, $slug)
    {
        if (str_contains($slug, '.')) {
            $gym = Tenant::where('custom_domain', $slug)->where('status', 'active')->first();
        } else {
            $gym = Tenant::where('slug', $slug)->where('status', 'active')->first();
        }

        if (!$gym) {
            return $this->jsonResponse(['message' => 'Gym not found'], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'text' => ['required', 'string', 'max:1000'],
        ]);

        $testimonials = $gym->testimonials_data ?? [];
        $testimonials[] = [
            'name' => $data['name'],
            'rating' => (int) $data['rating'],
            'text' => $data['text'],
            'created_at' => now()->toIso8601String(),
        ];

        $gym->testimonials_data = $testimonials;
        $gym->save();

        return $this->jsonResponse([
            'message' => 'Thank you for your feedback! It has been published successfully.',
            'data' => $testimonials
        ]);
    }

    /**
     * Join / subscribe a public user directly to a gym membership plan from the landing page.
     */
    public function subscribePlan(Request $request, $slug)
    {
        if (str_contains($slug, '.')) {
            $gym = Tenant::where('custom_domain', $slug)->where('status', 'active')->first();
        } else {
            $gym = Tenant::where('slug', $slug)->where('status', 'active')->first();
        }

        if (!$gym) {
            return $this->jsonResponse(['message' => 'Gym not found'], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'plan_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        // Dynamically register the Member
        $member = DB::transaction(function () use ($data, $gym) {
            // 1. Create User
            $user = \App\Models\User::create([
                'tenant_id' => $gym->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
            ]);

            // Assign standard Member role
            $memberRole = \App\Models\Role::where('name', 'Member')->first();
            if ($memberRole) {
                $user->roles()->attach($memberRole->id, ['tenant_id' => $gym->id]);
            }

            // 2. Create Member
            $member = \App\Models\Member::create([
                'tenant_id' => $gym->id,
                'user_id' => $user->id,
                'phone' => $data['phone'],
                'joining_date' => now()->toDateString(),
                'status' => 'active',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // 3. Create MemberMembership
            $dbPlan = \App\Models\MembershipPlan::where('tenant_id', $gym->id)
                ->where('name', 'like', "%{$data['plan_name']}%")
                ->first();

            if ($dbPlan) {
                \App\Models\MemberMembership::create([
                    'tenant_id' => $gym->id,
                    'member_id' => $member->id,
                    'plan_id' => $dbPlan->id,
                    'start_date' => now()->toDateString(),
                    'end_date' => now()->addDays($dbPlan->duration_days)->toDateString(),
                    'status' => 'active',
                    'payment_status' => 'paid',
                    'final_amount' => $dbPlan->price,
                ]);
            } else {
                $cleanPrice = (float) filter_var($data['plan_name'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?: 49.00;

                $mockPlan = \App\Models\MembershipPlan::firstOrCreate([
                    'tenant_id' => $gym->id,
                    'name' => $data['plan_name'],
                ], [
                    'price' => $cleanPrice,
                    'duration_days' => 30,
                    'status' => 'active',
                ]);

                \App\Models\MemberMembership::create([
                    'tenant_id' => $gym->id,
                    'member_id' => $member->id,
                    'plan_id' => $mockPlan->id,
                    'start_date' => now()->toDateString(),
                    'end_date' => now()->addDays(30)->toDateString(),
                    'status' => 'active',
                    'payment_status' => 'paid',
                    'final_amount' => $cleanPrice,
                ]);
            }

            return $member;
        });

        return $this->jsonResponse([
            'message' => 'Congratulations! You have successfully subscribed and joined the club.',
            'data' => [
                'name' => $data['name'],
                'email' => $data['email'],
                'plan_name' => $data['plan_name']
            ]
        ], 201);
    }
}
