@extends('layouts.writer')
@section('title', __('Admin').' · '.config('app.name'))
@section('content')
<main class="library admin-users">
 <div class="library-heading"><div><h1>{{ __('Users') }}</h1><p class="muted">{{ __(':count registered users', ['count' => $users->total()]) }}</p></div><a href="{{ route('dashboard') }}">{{ __('My library') }}</a></div>
 <form method="get" class="row"><label for="admin-search">{{ __('Search users') }}</label><input id="admin-search" name="search" value="{{ $search }}" maxlength="200" placeholder="{{ __('Name or email') }}"><button>{{ __('Search') }}</button><a href="{{ route('admin.users') }}">{{ __('Reset') }}</a></form>
 <p class="muted">{{ __('Book totals exclude deleted books. AI cost includes settled calls only.') }}</p>
 <div class="admin-table-scroll"><table class="admin-table"><thead><tr>@foreach(['User','Joined','Books','Archived books','AI calls','AI cost','Demo used','Demo reserved','Actions'] as $label)<th>{{ __($label) }}</th>@endforeach</tr></thead><tbody>
 @forelse($users as $user)<tr><td><strong>{{ $user->name }}</strong><br>{{ $user->email }}<br><small>#{{ $user->id }} @if($user->is_admin) · {{ __('Admin') }}@endif</small></td><td>{{ $user->created_at->format('Y-m-d') }}</td><td>{{ number_format($user->books_count) }}</td><td>{{ number_format($user->archived_count) }}</td><td>{{ number_format($user->calls_count) }}</td><td>${{ \App\Support\Money::display($user->ai_cost) }}</td><td>${{ \App\Support\Money::display($user->demo_spent) }}</td><td>${{ \App\Support\Money::display($user->demo_reserved) }}</td><td>@unless($user->is_admin)<form method="post" action="{{ route('admin.impersonate', $user) }}">@csrf<button>{{ __('Log in as user') }}</button></form>@endunless</td></tr>
 @empty<tr><td colspan="9">{{ __('No users found.') }}</td></tr>@endforelse
 </tbody></table></div>{{ $users->links() }}
</main>
@endsection
