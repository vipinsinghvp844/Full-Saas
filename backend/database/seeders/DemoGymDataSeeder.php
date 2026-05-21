<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GymClass;
use App\Models\GymSetting;
use App\Models\Member;
use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Trainer;
use App\Models\TrainerMember;
use App\Models\User;
use App\Services\Gym\BillingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoGymDataSeeder extends Seeder
{
    private const MEMBERS_PER_GYM = 10;

    private const TRAINERS_PER_GYM = 10;

    private const CLASSES_PER_GYM = 10;

    private const DEMO_PASSWORD = 'password';

    private const ROLE_NAMES = [
        'Gym Admin',
        'Manager',
        'Trainer',
        'Receptionist',
        'Accountant',
        'Member',
    ];

    private const CLASS_NAMES = [
        'Power Yoga',
        'HIIT Blast',
        'Strength Circuit',
        'Core & Stability',
        'Spin Ride',
        'Boxing Fundamentals',
        'Pilates Flow',
        'CrossFit WOD',
        'Zumba Energy',
        'Mobility Reset',
    ];

    private const CLASS_CATEGORIES = ['Mind & Body', 'Cardio', 'Strength', 'Wellness', 'Cycling', 'Combat', 'Dance'];

    private const INTENSITIES = ['Low', 'Medium', 'High'];

    private const SCHEDULE_DAYS = [
        ['Monday', '18:00', '19:00'],
        ['Wednesday', '07:00', '08:00'],
    ];

    public function run(): void
    {
        $tenants = Tenant::query()->orderBy('id')->get();

        foreach ($tenants as $index => $tenant) {
            $this->seedTenant($tenant, $index);
        }
    }

    protected function seedTenant(Tenant $tenant, int $gymIndex): void
    {
        $owner = $tenant->owner_user_id
            ? User::query()->find($tenant->owner_user_id)
            : null;

        $roles = $this->ensureRoles($tenant);
        $this->ensurePermissions($tenant, $roles['Gym Admin'] ?? null);

        if ($owner) {
            $owner->roles()->syncWithoutDetaching([
                $roles['Gym Admin']->id => ['tenant_id' => $tenant->id],
            ]);
        }

        $branch = Branch::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Main Branch'],
            [
                'address' => $tenant->address ?? 'Main Street',
                'phone' => $tenant->phone ?? '9000000000',
                'manager_id' => null,
                'created_by' => $owner?->id,
                'updated_by' => $owner?->id,
            ]
        );

        $this->seedGymSettings($tenant);
        $this->enrichTenantProfile($tenant, $gymIndex);

        $staffUserIds = $this->seedStaff($tenant, $branch, $roles, $owner?->id);
        $trainers = $this->seedTrainers($tenant, $branch, $roles, $owner?->id, $gymIndex);
        $plans = $this->seedMembershipPlans($tenant);
        $members = $this->seedMembers($tenant, $trainers, $plans, $roles, $owner?->id, $gymIndex);
        $this->seedMemberBilling($tenant, $members);
        $this->seedClasses($tenant, $trainers, $owner?->id, $gymIndex);
        $this->seedAttendance($tenant, $members, $trainers);
        $this->seedExpenses($tenant, $owner?->id);

        if ($staffUserIds['manager'] ?? null) {
            $managerEmployee = Employee::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $staffUserIds['manager'])
                ->first();

            if ($managerEmployee) {
                $branch->update(['manager_id' => $managerEmployee->id]);
            }
        }
    }

    protected function ensureRoles(Tenant $tenant): array
    {
        $roles = [];

        foreach (self::ROLE_NAMES as $roleName) {
            $roles[$roleName] = Role::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $roleName,
                    'guard_name' => 'web',
                ],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $roleName,
                    'guard_name' => 'web',
                ]
            );
        }

        return $roles;
    }

    protected function ensurePermissions(Tenant $tenant, ?Role $superAdminRole): void
    {
        $permissionNames = [
            'dashboard',
            'members',
            'trainers',
            'classes',
            'inventory',
            'expenses',
            'payments',
            'settings',
            'reports',
        ];

        $permissionIds = [];

        foreach ($permissionNames as $name) {
            $permission = Permission::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $name,
                    'guard_name' => 'web',
                ],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $name,
                    'guard_name' => 'web',
                ]
            );

            $permissionIds[] = $permission->id;
        }

        if ($superAdminRole) {
            $attach = [];

            foreach ($permissionIds as $id) {
                $attach[$id] = ['tenant_id' => $tenant->id];
            }

            $superAdminRole->permissions()->syncWithoutDetaching($attach);
        }
    }

    protected function seedGymSettings(Tenant $tenant): void
    {
        $settings = [
            'currency_symbol' => '₹',
            'currency_code' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'date_format' => 'd/m/Y',
            'invoice_prefix' => 'INV',
            'tax_percent' => '18',
            'auto_renew' => 'true',
            'enable_renewal_alerts' => 'true',
            'enable_payment_alerts' => 'true',
            'default_class_capacity' => '20',
        ];

        foreach ($settings as $key => $value) {
            GymSetting::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'key' => $key],
                ['value' => $value]
            );
        }
    }

    protected function enrichTenantProfile(Tenant $tenant, int $gymIndex): void
    {
        $cities = [
            ['Mumbai', 19.0760, 72.8777],
            ['Delhi', 28.6139, 77.2090],
            ['Bengaluru', 12.9716, 77.5946],
            ['Pune', 18.5204, 73.8567],
            ['Hyderabad', 17.3850, 78.4867],
            ['Chennai', 13.0827, 80.2707],
            ['Kolkata', 22.5726, 88.3639],
            ['Ahmedabad', 23.0225, 72.5714],
            ['Jaipur', 26.9124, 75.7873],
            ['Chandigarh', 30.7333, 76.7794],
        ];

        $city = $cities[$gymIndex % count($cities)];

        $tenant->update([
            'city' => $city[0],
            'state' => 'India',
            'country' => 'India',
            'latitude' => $city[1],
            'longitude' => $city[2],
            'website_enabled' => true,
            'website_template' => 'modern',
            'description' => $tenant->description ?: "Premium fitness at {$tenant->name}.",
        ]);
    }

    protected function seedStaff(Tenant $tenant, Branch $branch, array $roles, ?int $actorId): array
    {
        $slug = $tenant->slug;
        $staff = [
            ['key' => 'manager', 'name' => 'Manager', 'role' => 'Manager', 'position' => 'Gym Manager', 'salary' => 55000],
            ['key' => 'receptionist', 'name' => 'Reception', 'role' => 'Receptionist', 'position' => 'Front Desk', 'salary' => 28000],
            ['key' => 'accountant', 'name' => 'Accounts', 'role' => 'Accountant', 'position' => 'Finance Lead', 'salary' => 42000],
        ];

        $userIds = [];

        foreach ($staff as $index => $row) {
            $email = strtolower("{$row['key']}.{$slug}@demo.local");

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'tenant_id' => $tenant->id,
                    'name' => "{$tenant->name} {$row['name']}",
                    'password' => self::DEMO_PASSWORD,
                    'is_active' => true,
                ]
            );

            Employee::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                [
                    'role' => strtolower($row['role']),
                    'branch_id' => $branch->id,
                    'phone' => '98' . str_pad((string) ($tenant->id * 10 + $index), 8, '0', STR_PAD_LEFT),
                    'position' => $row['position'],
                    'hire_date' => now()->subMonths(6)->toDateString(),
                    'salary' => $row['salary'],
                    'shift' => '9 AM - 6 PM',
                    'status' => 'active',
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]
            );

            $user->roles()->syncWithoutDetaching([
                $roles[$row['role']]->id => ['tenant_id' => $tenant->id],
            ]);

            $userIds[$row['key']] = $user->id;
        }

        return $userIds;
    }

    protected function seedTrainers(Tenant $tenant, Branch $branch, array $roles, ?int $actorId, int $gymIndex): array
    {
        $slug = $tenant->slug;
        $specializations = [
            'Strength Training', 'Yoga', 'HIIT', 'CrossFit', 'Bodybuilding',
            'Pilates', 'Nutrition', 'Mobility', 'Boxing', 'Endurance',
        ];
        $trainers = [];

        for ($i = 1; $i <= self::TRAINERS_PER_GYM; $i++) {
            $email = "trainer{$i}.{$slug}@demo.local";

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'tenant_id' => $tenant->id,
                    'name' => "{$tenant->name} Trainer {$i}",
                    'password' => self::DEMO_PASSWORD,
                    'is_active' => true,
                ]
            );

            $avatar = "https://i.pravatar.cc/150?img=" . (($gymIndex * 10 + $i) % 70 + 1);

            $employee = Employee::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                [
                    'role' => 'trainer',
                    'branch_id' => $branch->id,
                    'phone' => '97' . str_pad((string) ($tenant->id * 100 + $i), 8, '0', STR_PAD_LEFT),
                    'position' => 'Personal Trainer',
                    'hire_date' => now()->subMonths(8)->toDateString(),
                    'avatar' => $avatar,
                    'salary' => 40000 + ($i * 500),
                    'shift' => '6 AM - 2 PM',
                    'status' => 'active',
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]
            );

            $user->roles()->syncWithoutDetaching([
                $roles['Trainer']->id => ['tenant_id' => $tenant->id],
            ]);

            $trainer = Trainer::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                [
                    'employee_id' => $employee->id,
                    'specialization' => $specializations[$i - 1],
                    'experience_years' => 2 + ($i % 8),
                    'certifications' => 'ACE / NASM Certified',
                    'bio' => "Expert coach specializing in {$specializations[$i - 1]}.",
                    'avatar' => $avatar,
                    'phone' => $employee->phone,
                    'salary' => $employee->salary,
                    'shift' => $employee->shift,
                    'status' => 'active',
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]
            );

            $trainers[] = $trainer;
        }

        return $trainers;
    }

    protected function seedMembershipPlans(Tenant $tenant): array
    {
        $definitions = [
            [
                'name' => 'Basic Monthly',
                'price' => 1500,
                'duration_days' => 30,
                'features' => ['Gym floor access', 'Locker'],
            ],
            [
                'name' => 'Premium Monthly',
                'price' => 2500,
                'duration_days' => 30,
                'features' => ['All equipment', 'Group classes', 'Trainer check-in'],
            ],
            [
                'name' => 'Annual Elite',
                'price' => 24000,
                'duration_days' => 365,
                'features' => ['Unlimited access', 'All classes', 'Quarterly assessment'],
            ],
        ];

        $plans = [];

        foreach ($definitions as $definition) {
            $plans[] = MembershipPlan::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $definition['name']],
                [
                    'price' => $definition['price'],
                    'duration_days' => $definition['duration_days'],
                    'features' => $definition['features'],
                ]
            );
        }

        return $plans;
    }

    protected function seedMembers(
        Tenant $tenant,
        array $trainers,
        array $plans,
        array $roles,
        ?int $actorId,
        int $gymIndex
    ): array {
        $slug = $tenant->slug;
        $firstNames = [
            'Aarav', 'Isha', 'Vihaan', 'Ananya', 'Arjun',
            'Diya', 'Kabir', 'Meera', 'Rohan', 'Sara',
        ];
        $members = [];

        for ($i = 1; $i <= self::MEMBERS_PER_GYM; $i++) {
            $email = "member{$i}.{$slug}@demo.local";
            $trainer = $trainers[($i - 1) % count($trainers)];
            $plan = $plans[($i - 1) % count($plans)];
            $firstName = $firstNames[$i - 1];

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'tenant_id' => $tenant->id,
                    'name' => "{$firstName} {$tenant->name}",
                    'password' => self::DEMO_PASSWORD,
                    'is_active' => true,
                ]
            );

            $user->roles()->syncWithoutDetaching([
                $roles['Member']->id => ['tenant_id' => $tenant->id],
            ]);

            $profilePicture = "https://i.pravatar.cc/150?img=" . (($gymIndex * 10 + $i + 20) % 70 + 1);

            $member = Member::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                [
                    'assigned_trainer_id' => $trainer->id,
                    'phone' => '96' . str_pad((string) ($tenant->id * 100 + $i), 8, '0', STR_PAD_LEFT),
                    'gender' => $i % 2 === 0 ? 'female' : 'male',
                    'date_of_birth' => now()->subYears(20 + ($i % 15))->toDateString(),
                    'address' => "Sector {$i}, {$tenant->city}",
                    'emergency_contact' => "Emergency {$i}",
                    'joining_date' => now()->subMonths(3)->addDays($i)->toDateString(),
                    'status' => $i === 10 ? 'inactive' : 'active',
                    'profile_picture' => $profilePicture,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]
            );

            $startDate = now()->subDays(15 + $i)->toDateString();
            $paymentStatus = match (true) {
                $i <= 7 => 'paid',
                $i <= 9 => 'pending',
                default => 'pending',
            };

            $membership = MemberMembership::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'member_id' => $member->id,
                    'status' => 'active',
                ],
                [
                    'plan_id' => $plan->id,
                    'start_date' => $startDate,
                    'end_date' => Carbon::parse($startDate)->addDays((int) $plan->duration_days)->toDateString(),
                    'payment_status' => $paymentStatus,
                    'final_amount' => $plan->price,
                ]
            );

            TrainerMember::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'trainer_id' => $trainer->id,
                    'member_id' => $member->id,
                ],
                ['assigned_date' => $startDate]
            );

            $members[] = ['member' => $member, 'membership' => $membership, 'payment_status' => $paymentStatus];
        }

        return $members;
    }

    protected function seedMemberBilling(Tenant $tenant, array $members): void
    {
        $billing = app(BillingService::class);

        foreach ($members as $row) {
            /** @var MemberMembership $membership */
            $membership = $row['membership'];
            $member = $row['member'];
            $billing->ensureMembershipInvoice($membership);

            if ($row['payment_status'] === 'paid') {
                $billing->recordPayment($tenant->id, $member, $membership, [
                    'amount' => (float) $membership->final_amount,
                    'discount' => 0,
                    'payment_method' => $member->id % 2 === 0 ? 'UPI' : 'cash',
                    'payment_status' => 'paid',
                    'paid_at' => now()->subDays(3)->toDateTimeString(),
                    'transaction_id' => 'TXN-' . $tenant->id . '-' . $member->id,
                    'notes' => 'Seeded membership payment',
                ]);
            }
        }
    }

    protected function seedClasses(Tenant $tenant, array $trainers, ?int $actorId, int $gymIndex): void
    {
        $images = [
            'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1534438327276-d14bfdafcff3?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1546484395-ad19f4c331f0?auto=format&fit=crop&w=800&q=80',
        ];

        for ($i = 0; $i < self::CLASSES_PER_GYM; $i++) {
            $trainer = $trainers[$i % count($trainers)];
            $name = self::CLASS_NAMES[$i];

            $gymClass = GymClass::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name],
                [
                    'description' => "Group session: {$name} at {$tenant->name}.",
                    'category' => self::CLASS_CATEGORIES[$i % count(self::CLASS_CATEGORIES)],
                    'capacity' => 15 + ($i % 6),
                    'duration' => 45 + ($i % 3) * 5,
                    'intensity' => self::INTENSITIES[$i % 3],
                    'image' => $images[$i % count($images)],
                    'trainer_id' => $trainer->id,
                    'status' => 'active',
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]
            );

            foreach (self::SCHEDULE_DAYS as [$day, $start, $end]) {
                ClassSchedule::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'class_id' => $gymClass->id,
                        'day_of_week' => $day,
                        'start_time' => $start,
                    ],
                    [
                        'end_time' => $end,
                        'room' => 'Studio ' . chr(65 + ($i % 4)),
                    ]
                );
            }
        }
    }

    protected function seedAttendance(Tenant $tenant, array $members, array $trainers): void
    {
        $activeMembers = collect($members)
            ->filter(fn ($row) => $row['member']->status === 'active')
            ->values();

        if ($activeMembers->isEmpty()) {
            return;
        }

        for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
            $date = now()->subDays($dayOffset)->toDateString();

            foreach ($activeMembers->take(6) as $index => $row) {
                if (($dayOffset + $index) % 2 !== 0) {
                    continue;
                }

                $member = $row['member'];
                $trainer = $trainers[$index % count($trainers)];
                $checkIn = Carbon::parse($date)->setTime(7 + $index, 30);

                Attendance::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'member_id' => $member->id,
                        'date' => $date,
                    ],
                    [
                        'trainer_id' => $trainer->id,
                        'check_in_time' => $checkIn,
                        'check_out_time' => $checkIn->copy()->addHours(2),
                        'status' => 'present',
                        'source' => 'manual',
                    ]
                );
            }
        }
    }

    protected function seedExpenses(Tenant $tenant, ?int $actorId): void
    {
        $categories = [
            ['name' => 'Rent', 'description' => 'Facility rent'],
            ['name' => 'Utilities', 'description' => 'Electricity and water'],
            ['name' => 'Maintenance', 'description' => 'Equipment upkeep'],
        ];

        $categoryIds = [];

        foreach ($categories as $cat) {
            $category = ExpenseCategory::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $cat['name']],
                ['description' => $cat['description']]
            );
            $categoryIds[$cat['name']] = $category->id;
        }

        $expenses = [
            ['category' => 'Rent', 'amount' => 45000, 'days_ago' => 12],
            ['category' => 'Utilities', 'amount' => 8500, 'days_ago' => 8],
            ['category' => 'Maintenance', 'amount' => 12000, 'days_ago' => 4],
        ];

        foreach ($expenses as $expense) {
            Expense::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'expense_category_id' => $categoryIds[$expense['category']],
                    'expense_date' => now()->subDays($expense['days_ago'])->toDateString(),
                    'amount' => $expense['amount'],
                ],
                [
                    'description' => "{$expense['category']} expense for {$tenant->name}",
                    'payment_method' => 'bank',
                    'recorded_by' => $actorId,
                ]
            );
        }
    }
}
