<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\Participants\ParticipantResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Item;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaffUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_sees_the_user_manager(): void
    {
        $admin = User::factory()->create();
        $pengawas = User::factory()->pengawas()->create();

        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->actingAs($pengawas)->get('/admin/users')->assertForbidden();
        $this->actingAs($pengawas)->get('/admin/participants')->assertForbidden();
        $this->actingAs($pengawas)->get('/admin/items')->assertForbidden();
        $this->actingAs($pengawas)->get('/admin/exam-groups')->assertOk();
        $this->actingAs($pengawas)->get('/admin/exam-groups/create')->assertForbidden();
        $this->actingAs($pengawas)->get('/admin/test-configs')->assertForbidden();
        $this->actingAs($admin)->get('/admin/exam-groups/create')->assertForbidden();
        $this->actingAs($admin)->get('/admin/test-configs')->assertForbidden();
    }

    public function test_an_operator_manages_the_roster_but_not_staff_accounts(): void
    {
        $operator = User::factory()->operator()->create();

        $this->actingAs($operator)->get('/admin/participants')->assertOk();
        $this->actingAs($operator)->get('/admin/exam-groups')->assertOk();
        $this->actingAs($operator)->get('/admin/exam-groups/create')->assertOk();
        $this->actingAs($operator)->get('/admin/test-configs')->assertOk();
        $this->actingAs($operator)->get('/admin/users')->assertForbidden();
        $this->actingAs($operator)->get('/admin/items')->assertForbidden();
    }

    public function test_a_peneliti_can_read_items_but_not_edit_them(): void
    {
        $peneliti = User::factory()->peneliti()->create();

        $this->actingAs($peneliti);
        $this->get('/admin/items')->assertOk();
        $this->get('/admin/item-banks')->assertOk();
        $this->assertFalse(ItemResource::canEdit(new Item));
        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(ParticipantResource::canViewAny());
    }

    public function test_admin_can_create_a_pengawas_from_the_panel(): void
    {
        $admin = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Pengawas Baru',
                'email' => 'pengawas-baru@c-eco.test',
                'password' => 'ekonomi1234',
                'role' => UserRole::Pengawas->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::query()->where('email', 'pengawas-baru@c-eco.test')->firstOrFail();
        $this->assertTrue($created->role === UserRole::Pengawas);
        $this->assertTrue($created->is_active);
        $this->assertTrue($created->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_admin_can_deactivate_a_pengawas(): void
    {
        $admin = User::factory()->create();
        $pengawas = User::factory()->pengawas()->create([
            'email' => 'ruangan@c-eco.test',
        ]);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $pengawas->getKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($pengawas->fresh()->is_active);
        $this->actingAs($pengawas->fresh())->get('/admin/monitor')->assertForbidden();
    }

    public function test_users_cannot_be_deleted_from_the_panel(): void
    {
        $this->actingAs(User::factory()->create());
        $this->assertFalse(UserResource::canDeleteAny());
        $this->assertFalse(UserResource::canDelete(User::factory()->pengawas()->create()));
    }
}
