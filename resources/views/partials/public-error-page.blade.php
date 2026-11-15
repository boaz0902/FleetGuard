@if (!request()->is('admin', 'admin/*'))
    @include('partials.public-navigation')
    <main class="public-error-page" id="main-content">
        <div class="public-error-card">
            <p class="public-error-code">{{ $code }}</p>
            <span class="public-icon-tile"><i class="bi bi-shield-exclamation" aria-hidden="true"></i></span>
            <h1>{{ $title }}</h1>
            <p>{{ $message }}</p>
            <a class="button button-primary" href="{{ route('home') }}">Back to FleetGuard <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
        </div>
    </main>
@else
    <main class="admin-error-page" id="main-content">
        <div>
            <p>{{ $code }}</p>
            <h1>{{ $title }}</h1>
            <p>{{ $message }}</p>
            <a href="{{ route('admin.login') }}">Return to admin sign in</a>
        </div>
    </main>
@endif
