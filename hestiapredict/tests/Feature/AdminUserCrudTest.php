<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_user_crud_endpoints_work(): void
    {
        $create = $this->postJson('/api/users', [
            'name' => 'New Staff',
            'email' => 'new.staff@example.com',
            'password' => 'secret123',
            'role' => 'receptionist',
            'actor_role' => 'admin',
        ]);

        $create->assertOk();
        $userId = $create->json('id');
        $this->assertNotEmpty($userId);

        $created = User::query()->findOrFail($userId);
        $this->assertSame('New Staff', $created->name);
        $this->assertSame('new.staff@example.com', $created->email);
        $this->assertSame('receptionist', $created->role);
        $this->assertNotSame('secret123', $created->password);

        $update = $this->postJson('/api/users/update', [
            'id' => $userId,
            'name' => 'Updated Staff',
            'email' => 'updated.staff@example.com',
            'role' => 'admin',
            'password' => 'secret456',
            'actor_role' => 'admin',
        ]);

        $update->assertOk();
        $updated = User::query()->findOrFail($userId);
        $this->assertSame('Updated Staff', $updated->name);
        $this->assertSame('updated.staff@example.com', $updated->email);
        $this->assertSame('admin', $updated->role);
        $this->assertNotSame('secret456', $updated->password);

        $delete = $this->deleteJson("/api/users/{$userId}", [
            'actor_role' => 'admin',
        ]);
        $delete->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_admin_cannot_manage_superadmin_accounts(): void
    {
        $create = $this->postJson('/api/users', [
            'name' => 'Root Admin',
            'email' => 'root.admin@example.com',
            'password' => 'secret123',
            'role' => 'superadmin',
            'actor_role' => 'admin',
        ]);

        $create->assertForbidden();

        $superCreate = $this->postJson('/api/users', [
            'name' => 'Root Admin',
            'email' => 'root.admin@example.com',
            'password' => 'secret123',
            'role' => 'superadmin',
            'actor_role' => 'superadmin',
        ]);

        $superCreate->assertOk();
        $superadminId = $superCreate->json('id');

        $update = $this->postJson('/api/users/update', [
            'id' => $superadminId,
            'name' => 'Root Admin Updated',
            'email' => 'root.admin.updated@example.com',
            'role' => 'superadmin',
            'password' => 'secret456',
            'actor_role' => 'admin',
        ]);

        $update->assertForbidden();

        $delete = $this->deleteJson("/api/users/{$superadminId}", [
            'actor_role' => 'admin',
        ]);

        $delete->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $superadminId]);
    }
}
