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
        ]);

        $update->assertOk();
        $updated = User::query()->findOrFail($userId);
        $this->assertSame('Updated Staff', $updated->name);
        $this->assertSame('updated.staff@example.com', $updated->email);
        $this->assertSame('admin', $updated->role);
        $this->assertNotSame('secret456', $updated->password);

        $delete = $this->deleteJson("/api/users/{$userId}");
        $delete->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }
}
