@php
    $roleSessions = $roleSessions ?? role_sessions();
@endphp

@if ($roleSessions)
    <div class="login-sessions" aria-label="Sesi peran aktif">
        <p class="login-sessions-title">Sesi aktif di peramban ini</p>
        <p class="login-sessions-hint">Setiap peran punya aktivitas sendiri. Masuk peran lain tidak mengeluarkan sesi di bawah.</p>

        <ul class="login-sessions-list">
            @foreach ($roleSessions as $session)
                <li class="login-session-card">
                    <div class="login-session-meta">
                        <span class="login-session-role">{{ $session['role'] }}</span>
                        <span class="login-session-name">{{ $session['user']->name }}</span>
                    </div>
                    <div class="login-session-actions">
                        <form action="{{ route('session.activate') }}" method="POST">
                            @csrf
                            <input type="hidden" name="guard" value="{{ $session['guard'] }}">
                            <button type="submit" class="login-session-btn login-session-btn--go">
                                Lanjutkan
                            </button>
                        </form>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <input type="hidden" name="guard" value="{{ $session['guard'] }}">
                            <button type="submit" class="login-session-btn login-session-btn--out">
                                Keluar
                            </button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>

        @if (count($roleSessions) > 1)
            <form action="{{ route('logout') }}" method="POST" class="login-sessions-all">
                @csrf
                <input type="hidden" name="guard" value="all">
                <button type="submit" class="login-session-btn login-session-btn--all">
                    Keluar semua peran
                </button>
            </form>
        @endif
    </div>
@endif
