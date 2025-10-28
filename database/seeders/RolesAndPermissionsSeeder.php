<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles with levels (Guest=0, Client=100, Staff=500, Admin=750, Superuser=999)
        $guest = Role::firstOrCreate(['name' => 'guest']);
        $client = Role::firstOrCreate(['name' => 'client']);
        $staff = Role::firstOrCreate(['name' => 'staff']);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $superuser = Role::firstOrCreate(['name' => 'superuser']);

        $permissions = [
            // User Admin permissions
            'user.browse',
            'user.show.any',
            'user.show.own',
            'user.edit.any',
            'user.edit.staff',
            'user.edit.client',
            'user.edit.own',
            'user.add.client',
            'user.add.staff',
            'user.add.admin',
            'user.delete.any',
            'user.delete.staff',
            'user.delete.client',
            'user.delete.own',
            'user.search',
            'user.assign.role',
            'user.ban',
            'user.suspend',
            'user.revert.status',

            // Joke permissions
            'joke.browse',
            'joke.show.any',
            'joke.show.own',
            'joke.edit.any',
            'joke.edit.own',
            'joke.add',
            'joke.delete.any',
            'joke.delete.own',
            'joke.search',
            'joke.random.one',
            'joke.trash.view',
            'joke.trash.recover.one',
            'joke.trash.remove.one',
            'joke.trash.recover.all',
            'joke.trash.empty.all',

            // Category permissions
            'category.browse',
            'category.show.any',
            'category.edit.any',
            'category.add',
            'category.delete.any',
            'category.search',
            'category.trash.view',
            'category.trash.recover.one',
            'category.trash.remove.one',
            'category.trash.recover.all',
            'category.trash.empty.all',

            // Vote permissions
            'vote.add',
            'vote.edit.own',
            'vote.delete.own',
            'vote.clear.user',
            'vote.clear.all',
            'vote.reset.all',

            // Authentication permissions
            'auth.register',
            'auth.login',
            'auth.logout',
            'auth.reset.password.own',
            'auth.reset.password.others',
            'auth.force.logout.others',

            // Profile permissions
            'profile.view.own',
            'profile.edit.own',
            'profile.delete.own',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Guest permissions (level 0)
        $guest->givePermissionTo([
            'auth.register',
            'joke.random.one', 
        ]);

        // Client permissions (level 100)
        $client->givePermissionTo([
            'auth.login',
            'auth.logout',
            'auth.reset.password.own',
            'profile.view.own',
            'profile.edit.own',
            'profile.delete.own',
            'joke.browse',
            'joke.show.any',
            'joke.show.own',
            'joke.edit.own',
            'joke.add',
            'joke.delete.own',
            'joke.search',
            'category.browse',
            'category.show.any',
            'category.search',
            'vote.add',
            'vote.edit.own',
            'vote.delete.own',
            'user.show.own',
            'user.edit.own',
            'user.delete.own',
        ]);

        // Staff permissions (level 500)
        $staff->givePermissionTo([
            'auth.login',
            'auth.logout',
            'auth.reset.password.own',
            'auth.reset.password.others',
            'auth.force.logout.others',
            'profile.view.own',
            'profile.edit.own',
            'user.browse',
            'user.show.any',
            'user.show.own',
            'user.edit.client',
            'user.edit.own',
            'user.add.client',
            'user.delete.client',
            'user.search',
            'user.ban',
            'user.suspend',
            'user.revert.status',
            'joke.browse',
            'joke.show.any',
            'joke.edit.any',
            'joke.add',
            'joke.delete.any',
            'joke.search',
            'category.browse',
            'category.show.any',
            'category.edit.any',
            'category.add',
            'category.delete.any',
            'category.search',
            'category.trash.view',
            'category.trash.recover.one',
            'vote.add',
            'vote.edit.own',
            'vote.delete.own',
        ]);

        // Admin permissions (level 750)
        $admin->givePermissionTo([
            'auth.login',
            'auth.logout',
            'auth.reset.password.own',
            'auth.reset.password.others',
            'auth.force.logout.others',
            'profile.view.own',
            'profile.edit.own',
            'user.browse',
            'user.show.any',
            'user.edit.any',
            'user.add.client',
            'user.add.staff',
            'user.delete.staff',
            'user.delete.client',
            'user.search',
            'user.assign.role',
            'user.ban',
            'user.suspend',
            'user.revert.status',
            'joke.browse',
            'joke.show.any',
            'joke.edit.any',
            'joke.add',
            'joke.delete.any',
            'joke.search',
            'category.browse',
            'category.show.any',
            'category.edit.any',
            'category.add',
            'category.delete.any',
            'category.search',
            'category.trash.view',
            'category.trash.recover.one',
            'category.trash.remove.one',
            'category.trash.recover.all',
            'vote.add',
            'vote.edit.own',
            'vote.delete.own',
            'vote.clear.user',
        ]);

        // Superuser permissions (level 999) - Everything + backup capabilities
        $superuser->givePermissionTo(Permission::all());
    }
}
