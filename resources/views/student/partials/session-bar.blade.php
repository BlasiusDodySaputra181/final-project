{{-- Navbar atas layanan mahasiswa (guard: mahasiswa) --}}
@auth('student')
    @php
        $studentUser = Auth::guard('student')->user();
        $studentName = $studentUser->name;
        $studentInitials = collect(preg_split('/\s+/', trim($studentName)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
        $studentHasProof = \App\Models\FinalProject::existsForNim($studentUser->identity_number);
        $studentHasMembership = \App\Models\Membership::existsForUser($studentUser->id);
        $onAnggotaPage = request()->is('anggota');
        $onSerahPage = request()->is('serah');

        $showAnggotaMenu = false;
        $showSerahMenu = false;
        $showBuktiMenu = false;

        if ($onAnggotaPage && ! $studentHasMembership) {
            $showAnggotaMenu = true;
            $showBuktiMenu = true;
        } elseif ($onSerahPage && ! $studentHasProof) {
            $showSerahMenu = true;
            $showBuktiMenu = true;
        } elseif (! $studentHasMembership && ! $studentHasProof) {
            $showAnggotaMenu = true;
        } else {
            $showBuktiMenu = true;
        }

        $studentNavExtended = ($showAnggotaMenu && $showBuktiMenu) || ($showSerahMenu && $showBuktiMenu);
    @endphp

    <header class="student-topnav" role="banner">
        <div class="student-topnav-inner">
            <a href="{{ route('page-public-visitor') }}" class="student-topnav-brand">
                <img
                    src="{{ asset('images/logo.png') }}"
                    alt="Logo UNJ"
                    width="36"
                    height="36"
                    decoding="async"
                >
                <div class="student-topnav-brand-text">
                    <strong>ARSIP TA</strong>
                    <span>Perpustakaan FMIPA UNJ</span>
                </div>
            </a>

            <nav
                @class(['student-topnav-nav', 'is-extended' => $studentNavExtended])
                aria-label="Layanan mahasiswa"
            >
                <a
                    href="{{ route('page-public-visitor') }}"
                    @class(['student-topnav-link', 'is-active' => request()->is('kunjungan')])
                >
                    <i class="fas fa-clipboard-user" aria-hidden="true"></i>
                    <span>Pengunjung</span>
                </a>
                <a
                    href="{{ route('page-public-search') }}"
                    @class(['student-topnav-link', 'is-active' => request()->is('cari')])
                >
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                    <span>Cari</span>
                </a>
                <a
                    href="{{ route('page-public-barcode') }}"
                    @class(['student-topnav-link', 'is-active' => request()->is('pindai', 'pindai/*')])
                >
                    <i class="fas fa-barcode" aria-hidden="true"></i>
                    <span>Pindai</span>
                </a>
                @if ($showAnggotaMenu)
                    <a
                        href="{{ route('page-public-membership') }}"
                        @class(['student-topnav-link', 'is-active' => request()->is('anggota')])
                    >
                        <i class="fas fa-id-card" aria-hidden="true"></i>
                        <span>Anggota</span>
                    </a>
                @endif
                @if ($showSerahMenu)
                    <a
                        href="{{ route('page-public-submission') }}"
                        @class(['student-topnav-link', 'is-active' => request()->is('serah')])
                    >
                        <i class="fas fa-file-arrow-up" aria-hidden="true"></i>
                        <span>Serah</span>
                    </a>
                @endif
                @if ($showBuktiMenu)
                    <a
                        href="{{ route('page-public-proof') }}"
                        @class(['student-topnav-link', 'is-active' => request()->is('bukti', 'bukti/*')])
                    >
                        <i class="fas fa-file-circle-check" aria-hidden="true"></i>
                        <span>Bukti</span>
                    </a>
                @endif
            </nav>

            <div class="student-topnav-aside">
                @php
                    $otherSessions = collect(role_sessions())
                        ->reject(fn ($session) => $session['guard'] === 'student');
                @endphp

                @if ($otherSessions->isNotEmpty())
                    <div class="student-topnav-sessions" aria-label="Peran lain">
                        @foreach ($otherSessions as $session)
                            <form action="{{ route('session.activate') }}" method="POST">
                                @csrf
                                <input type="hidden" name="guard" value="{{ $session['guard'] }}">
                                <button type="submit" class="student-topnav-session" title="{{ $session['user']->name }}">
                                    <i class="fas fa-user-shield" aria-hidden="true"></i>
                                    <span>{{ $session['role'] }}</span>
                                </button>
                            </form>
                        @endforeach
                    </div>
                @endif

                @if ($otherSessions->count() < 2)
                    <a href="{{ route('login') }}" class="student-topnav-session student-topnav-session--add" title="Masuk peran lain">
                        <i class="fas fa-user-plus" aria-hidden="true"></i>
                        <span>Peran lain</span>
                    </a>
                @endif

                <a
                    href="{{ route('page-public-profile') }}"
                    @class(['student-topnav-profile', 'is-active' => request()->is('akun')])
                    title="Kelola profil {{ $studentName }}"
                    aria-label="Kelola profil mahasiswa"
                >
                    <div class="student-topnav-avatar" aria-hidden="true">{{ $studentInitials ?: 'M' }}</div>
                    <div class="student-topnav-meta">
                        <span class="student-topnav-name">{{ $studentName }}</span>
                        <span class="student-topnav-role">
                            {{ $studentUser->identity_number ? 'NIM '.$studentUser->formattedIdentityNumber() : 'Mahasiswa' }}
                        </span>
                    </div>
                </a>

                <form action="{{ route('logout') }}" method="POST" class="student-topnav-logout">
                    @csrf
                    <input type="hidden" name="guard" value="student">
                    <button type="submit" class="student-topnav-logout-btn">
                        <i class="fas fa-right-from-bracket" aria-hidden="true"></i>
                        <span>Keluar</span>
                    </button>
                </form>
            </div>
        </div>
    </header>
@endauth
