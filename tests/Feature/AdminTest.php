<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->is_admin = true;
        $u->save();

        return $u;
    }

    public function test_default_and_mass_assignment_cannot_grant_admin(): void
    {
        $u = User::factory()->create();
        $this->assertFalse($u->fresh()->is_admin);
        $this->assertFalse($u->isFillable('is_admin'));
        $this->actingAs($u)->patch('/profile', ['name' => $u->name, 'email' => $u->email, 'is_admin' => 1]);
        $this->assertFalse($u->fresh()->is_admin);
    }

    public function test_admin_routes_are_protected(): void
    {
        $target = User::factory()->create();
        $this->get('/admin/users')->assertRedirect('/login');
        $this->actingAs($target)->get('/admin/users')->assertForbidden();
        $this->post('/admin/users/'.$target->id.'/impersonate')->assertForbidden();
        $this->post('/admin/impersonation/stop')->assertForbidden();
        $this->get('/dashboard')->assertDontSee('href="'.route('admin.users').'"', false);
    }

    public function test_admin_listing_and_impersonation_round_trip(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $this->actingAs($admin)->get('/dashboard')->assertSee(route('admin.users'));
        $this->get('/admin/users')->assertOk()->assertSee($target->email)->assertSee($admin->email);
        $this->get('/admin/users?search='.urlencode($target->email))->assertOk()->assertSee($target->email)->assertDontSee($admin->email);
        $this->post('/admin/users/'.$admin->id.'/impersonate')->assertForbidden();
        $this->post('/admin/users/'.$target->id.'/impersonate')->assertRedirect('/dashboard')->assertSessionHas('impersonator_id', $admin->id);
        $this->assertAuthenticatedAs($target);
        $this->get('/dashboard')->assertSee(route('admin.impersonation.stop'));
        $this->get('/admin/users')->assertForbidden();
        $this->post('/admin/users/'.$admin->id.'/impersonate')->assertForbidden();
        $this->post('/admin/impersonation/stop')->assertRedirect('/admin/users')->assertSessionMissing('impersonator_id');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_revoking_original_admin_ends_impersonation(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $this->actingAs($admin)->post('/admin/users/'.$target->id.'/impersonate');
        DB::table('users')->where('id', $admin->id)->update(['is_admin' => false]);
        $this->get('/dashboard')->assertRedirect('/login')->assertSessionMissing('impersonator_id');
        $this->assertGuest();
    }

    public function test_stats_separate_deleted_books_and_unsettled_costs(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $book = \App\Models\Book::create(['user_id' => $target->id, 'title' => 'Test', 'document' => [], 'codex_types' => []]);
        $archived = $book->replicate();
        $archived->archived = true;
        $archived->save();
        $deleted = $book->replicate();
        $deleted->save();
        $deleted->delete();
        foreach (['settled' => 0.0123, 'reserved' => 9] as $status => $cost) {
            DB::table('ai_calls')->insert(['user_id' => $target->id, 'book_id' => $book->id, 'model' => 'test', 'stage' => 'execute', 'funding' => 'demo', 'status' => $status, 'cost' => $cost]);
        }
        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertViewHas('users', function ($users) use ($target) {
            $row = $users->firstWhere('id', $target->id);

            return $row->books_count == 2 && $row->archived_count == 1 && $row->calls_count == 2 && abs($row->ai_cost - 0.0123) < 0.00001;
        });
        $otherAdmin = $this->admin();
        $this->post('/admin/users/'.$otherAdmin->id.'/impersonate')->assertForbidden();
    }

    public function test_logout_during_impersonation_clears_return_access(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $this->actingAs($admin)->post('/admin/users/'.$target->id.'/impersonate');
        $this->post('/logout')->assertSessionMissing('impersonator_id');
        $this->assertGuest();
    }
}
