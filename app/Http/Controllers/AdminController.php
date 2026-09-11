<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->is_admin && ! $request->session()->has('impersonator_id'), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $search = $request->validate(['search' => 'nullable|string|max:200'])['search'] ?? '';
        $users = User::query()->select(['id', 'name', 'email', 'is_admin', 'created_at', 'demo_spent', 'demo_reserved'])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->selectSub(DB::table('books')->selectRaw('COUNT(*)')->whereColumn('books.user_id', 'users.id')->whereNull('deleted_at'), 'books_count')
            ->selectSub(DB::table('books')->selectRaw('COUNT(*)')->whereColumn('books.user_id', 'users.id')->whereNull('deleted_at')->where('archived', true), 'archived_count')
            ->selectSub(DB::table('ai_calls')->selectRaw('COUNT(*)')->whereColumn('ai_calls.user_id', 'users.id'), 'calls_count')
            ->selectSub(DB::table('ai_calls')->selectRaw('COALESCE(SUM(cost),0)')->whereColumn('ai_calls.user_id', 'users.id')->where('status', 'settled'), 'ai_cost')
            ->orderByDesc('id')->paginate(25)->withQueryString();

        return response()->view('admin.users', compact('users', 'search'))->header('Cache-Control', 'private, no-store');
    }

    public function impersonate(Request $request, User $user)
    {
        $this->authorizeAdmin($request);
        abort_if($user->is_admin || $user->id === $request->user()->id, 403);
        $adminId = $request->user()->id;
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('impersonator_id', $adminId);
        Auth::guard('web')->login($user, false);
        Log::notice('Admin impersonation started', ['admin_id' => $adminId, 'user_id' => $user->id]);

        return redirect()->route('dashboard');
    }

    public function stop(Request $request)
    {
        $admin = User::find($request->session()->get('impersonator_id'));
        abort_unless($admin && $admin->is_admin, 403);
        $targetId = $request->user()->id;
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::guard('web')->login($admin, false);
        Log::notice('Admin impersonation ended', ['admin_id' => $admin->id, 'user_id' => $targetId]);

        return redirect()->route('admin.users');
    }
}
