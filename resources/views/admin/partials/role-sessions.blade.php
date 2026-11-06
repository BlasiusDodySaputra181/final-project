@php
    $roleSessions = role_sessions();
    $currentGuard = auth()->getDefaultDriver();
@endphp

@if (count($roleSessions) > 1 || collect($roleSessions)->contains(fn ($session) => $session['guard'] === 'student'))
    <div class="role-session-switch" aria-label="Sesi peran aktif">
        @foreach ($roleSessions as $session)
            <form action="{{ route('session.activate') }}" method="POST">
                @csrf
                <input type="hidden" name="guard" value="{{ $session['guard'] }}">
                <button
                    type="submit"
                    @class([
                        'role-session-chip',
                        'is-active' => $session['guard'] === $currentGuard,
                    ])
                    title="{{ $session['user']->name }} — {{ $session['role'] }}"
                >
                    <span class="role-session-chip-role">{{ $session['role'] }}</span>
                    <span class="role-session-chip-name">{{ $session['user']->name }}</span>
                </button>
            </form>
        @endforeach

        @if (count($roleSessions) < 3)
            <a href="{{ route('login') }}" class="role-session-chip role-session-chip--add" title="Masuk peran lain">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <span>Peran lain</span>
            </a>
        @endif
    </div>
@else
    <a href="{{ route('login') }}" class="role-session-chip role-session-chip--add" title="Masuk peran lain">
        <i class="fas fa-plus" aria-hidden="true"></i>
        <span>Peran lain</span>
    </a>
@endif
