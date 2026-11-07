<nav class="row-actions" aria-label="Shop settings sections">
    @foreach ([['admin.shop-settings', 'Shop Details'], ['admin.staff-roles', 'Staff Roles'], ['admin.users', 'Manage Users']] as [$routeName, $label])
        <a href="{{ route($routeName) }}" wire:navigate class="row-action {{ request()->routeIs($routeName) ? 'row-action-primary' : 'row-action-neutral' }}" @if(request()->routeIs($routeName)) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
