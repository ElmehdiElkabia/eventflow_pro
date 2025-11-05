<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1️⃣ Clear cache for roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Define Global Permissions
        |--------------------------------------------------------------------------
        |
        | We define permissions based on your main modules:
        | - User Management
        | - Event Management
        | - Ticket Management
        | - Payment Management
        | - Notification System
        |
        */
        $permissions = [
            // User Management
            'view users',
            'create users',
            'view_users',
            'delete_users',

            // Event Management
            'view_events',
            'create_event',
            'edit_event',
            'delete_event',
            'publish_event',

            // Ticket Management
            'view_tickets',
            'create_ticket',
			'edit_ticket',
			'delete_ticket',
            'validate_ticket',
            'refund_ticket',

		    // Review permissions
            'view reviews',
            'create reviews',
            'edit reviews',
            'delete reviews',

            // Payment Management
            'process_payment',
            'view_transactions',
            'request_refund',

            // Notification Management
            'send_notifications',
            'view_notifications',
            'delete_notifications',
			
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Define Roles
        |--------------------------------------------------------------------------
        */
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $organizer  = Role::firstOrCreate(['name' => 'organizer']);
        $user       = Role::firstOrCreate(['name' => 'user']);

        /*
        |--------------------------------------------------------------------------
        | 4️⃣ Assign Permissions to Roles
        |--------------------------------------------------------------------------
        |
        | Super Admin → all permissions
        | Organizer → manage events, tickets, and notifications
        | User → discover events, buy tickets, request refunds
        |
        */
        $superAdmin->syncPermissions(Permission::all());

        $organizer->syncPermissions([
            'create_event',
            'edit_event',
            'delete_event',
            'view_events',
            'publish_event',
            'create_ticket',
            'view_tickets',
            'validate_ticket',
            'send_notifications',
            'view_notifications',
        ]);

        $user->syncPermissions([
            'view_events',
            'view_tickets',
            'request_refund',
            'view_notifications',
        ]);

        /*
        |--------------------------------------------------------------------------
        | 5️⃣ Create Example Users
        |--------------------------------------------------------------------------
        */
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@eventflowpro.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
            ]
        );
        $adminUser->assignRole($superAdmin);

        $organizerUser = User::firstOrCreate(
            ['email' => 'organizer@eventflowpro.com'],
            [
                'name' => 'Event Organizer',
                'password' => Hash::make('password'),
            ]
        );
        $organizerUser->assignRole($organizer);

        $regularUser = User::firstOrCreate(
            ['email' => 'user@eventflowpro.com'],
            [
                'name' => 'Regular User',
                'password' => Hash::make('password'),
            ]
        );
        $regularUser->assignRole($user);

        /*
        |--------------------------------------------------------------------------
        | 6️⃣ Confirm Seeder Completion
        |--------------------------------------------------------------------------
        */
        $this->command->info('✅ Roles, permissions, and test users have been seeded successfully.');
    }
}
