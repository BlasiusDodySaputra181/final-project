/**
 * Guided tour for the officer and coordinator roles.
 * Started from the Help button in the sidebar. Steps follow the active page.
 */
(function () {
    const createDriver = window.driver?.js?.driver;
    if (typeof createDriver !== 'function') {
        console.warn('Driver.js is unavailable — help tour disabled.');
        return;
    }

    const role = document.body?.dataset?.userRole
        || document.querySelector('meta[name="user-role"]')?.content
        || '';
    const isOfficer = role === 'Petugas';
    const isCoordinator = role === 'Koordinator';

    const path = (location.pathname || '').replace(/\/+$/, '') || '/';
    const isPage = page => path === page || path.endsWith(page);

    const MOBILE_BREAKPOINT = 992;

    const step = (element, title, description, side = 'bottom', options = {}) => ({
        element,
        keepSidebar: Boolean(options.keepSidebar),
        popover: {
            title,
            description,
            side,
            align: 'start'
        }
    });

    const centerStep = (title, description) => ({
        keepSidebar: false,
        popover: {
            title,
            description,
            side: 'over',
            align: 'center'
        }
    });

    /** Step that points at an element in the side navigation. */
    const sidebarStep = (element, title, description, side = 'right') =>
        step(element, title, description, side, { keepSidebar: true });

    const roleNote = isOfficer
        ? emphasizedSentence('Sebagai ', 'Petugas', ', Anda dapat menambah, mengubah, dan menghapus data.')
        : emphasizedSentence('Sebagai ', 'Koordinator', ', Anda dapat melihat dan mengunduh data (tanpa mengubah atau menghapus).');

    const mutateHint = (officerText, coordinatorText) =>
        isOfficer ? officerText : (coordinatorText || 'Tombol ini tidak tersedia untuk Koordinator.');

    const resolveElement = selector => {
        if (!selector) return null;
        if (typeof selector === 'string') return document.querySelector(selector);
        if (typeof selector === 'function') return selector();
        return selector;
    };

    /**
     * A tour target is available when it is in the DOM and not permanently hidden (display/visibility).
     * Opacity 0 or a size of 0 is allowed — a collapsed sidebar or an empty table
     * still counts, so every relevant hint can run.
     */
    const isTourTargetAvailable = el => {
        if (!el || !el.isConnected) return false;

        let node = el;
        while (node && node.nodeType === 1 && node !== document.documentElement) {
            const style = window.getComputedStyle(node);
            if (style.display === 'none' || style.visibility === 'hidden') {
                return false;
            }
            node = node.parentElement;
        }

        return true;
    };

    const filterSteps = steps => steps.filter(item => {
        if (!item.element) return true;
        return isTourTargetAvailable(resolveElement(item.element));
    });

    const isMobileView = () => window.innerWidth <= MOBILE_BREAKPOINT;

    const setTourMobileSidebar = open => {
        const sidebarEl = document.getElementById('sidebar');
        const toggle = document.getElementById('toggleSidebar');
        const backdrop = document.getElementById('sidebarbackdrop');
        if (!sidebarEl) return;

        const changed = sidebarEl.classList.contains('show') !== open;
        if (changed) {
            sidebarEl.classList.add('tour-sidebar-instant');
            sidebarEl.classList.toggle('show', open);
            toggle?.classList.toggle('active', open);
            document.body.classList.toggle('sidebar-open', open);
            backdrop?.setAttribute('aria-hidden', open ? 'false' : 'true');
            // Force a reflow so the Driver.js highlight uses the final position (skip the animation).
            void sidebarEl.offsetWidth;
            requestAnimationFrame(() => {
                sidebarEl.classList.remove('tour-sidebar-instant');
            });
        } else {
            sidebarEl.classList.toggle('show', open);
            toggle?.classList.toggle('active', open);
            document.body.classList.toggle('sidebar-open', open);
            backdrop?.setAttribute('aria-hidden', open ? 'false' : 'true');
        }
    };

    const ensureDesktopSidebarExpanded = () => {
        const sidebarEl = document.getElementById('sidebar');
        if (sidebarEl?.classList.contains('collapsed')) {
            document.getElementById('toggleSidebar')?.click();
        }
    };

    const isSidebarTourStep = tourStep => {
        if (tourStep?.keepSidebar) return true;
        const el = resolveElement(tourStep?.element);
        return Boolean(el?.closest?.('#sidebar'));
    };

    const syncSidebarForTourStep = tourStep => {
        const focusSidebar = isSidebarTourStep(tourStep);
        document.body.classList.toggle('tour-sidebar-focus', focusSidebar);

        if (!isMobileView()) {
            if (focusSidebar) ensureDesktopSidebarExpanded();
            return;
        }

        // Phone: open the side navigation while its menu is explained; close it for panels and pages.
        setTourMobileSidebar(focusSidebar);
    };

    const resetTourSidebarState = () => {
        document.body.classList.remove('tour-sidebar-focus');
        if (isMobileView()) {
            setTourMobileSidebar(false);
        }
    };

    const ensureFinalProjectMenuOpen = () => {
        const menu = document.getElementById('menufinalproject');
        const submenu = document.getElementById('submenufinalproject');
        if (menu && !menu.classList.contains('open')) {
            menu.classList.add('open', 'active');
            submenu?.classList.add('show');
        }
    };

    /** Side navigation and top panel — included on every page so every menu is explained. */
    const chromeSteps = () => {
        const side = isMobileView() ? 'bottom' : 'right';
        return [
            sidebarStep('#sidebar .brand', 'Identitas aplikasi', 'Logo dan nama sistem arsip tugas akhir FMIPA UNJ.', side),
            sidebarStep('#sidebar .menu', 'Menu utama', 'Akses Dasbor, Tugas Akhir, Kode Batang, Arsip, dan Laporan dari sini.', side),
            sidebarStep('#menufinalproject', 'Menu Tugas Akhir', 'Klik untuk membuka daftar jenis tugas akhir (skripsi, tesis, dll.).', side),
            sidebarStep('#menusetting', 'Pengaturan', 'Atur kunci dan model kecerdasan buatan untuk impor tugas akhir, folder penyimpanan (ARSIP TUGAS AKHIR menurut jenis, tahun, rumpun ilmu, lalu program studi, dan hanya berisi folder yang ada berkasnya), serta buka atau tutup akses pengunjung, penyerahan, dan kode batang untuk seluruh mahasiswa.', side),
            step('.module-nav', 'Navigasi modul', 'Pintasan modul layanan: Penyerahan, Pengunjung, Pemusnahan, Keanggotaan, Bebas Pustaka, Hibah Buku, Program Studi, Jenis Tugas Akhir, dan Pengguna.', 'bottom'),
            step('#profilebtn', 'Profil & keluar', 'Lihat profil akun atau keluar dari sistem.', 'bottom'),
            sidebarStep('#helptourbtn', 'Bantuan', 'Klik kapan saja untuk mengulang petunjuk halaman yang sedang dibuka.', side)
        ];
    };

    /** Page intro, navigation chrome, and page-feature steps. */
    const withChrome = (intro, pageSteps = []) => [intro, ...chromeSteps(), ...pageSteps];

    const tableCommon = (addId, deleteId, tableId, entityLabel) => [
        step('.hero', 'Judul halaman', `Ringkasan fitur ${entityLabel} yang sedang Anda kelola.`, 'bottom'),
        step(
            addId,
            'Tambah data',
            mutateHint(
                `Buka formulir untuk menambah ${entityLabel} baru.`,
                `Koordinator tidak menambah data — gunakan tombol unduh (jika ada) untuk melihat laporan.`
            ),
            'bottom'
        ),
        step(
            deleteId,
            'Hapus dipilih',
            mutateHint(
                `Centang baris di tabel lalu hapus ${entityLabel} yang dipilih.`,
                'Penghapusan hanya tersedia untuk Petugas.'
            ),
            'bottom'
        ),
        step(
            tableId,
            'Tabel data',
            'Cari, urutkan, dan buka detail baris. Ikon aksi di setiap baris untuk melihat atau mengelola data.',
            'top'
        )
    ];

    const pageTours = {
        dasbor: () => withChrome(
            centerStep(
                'Petunjuk Arsip Tugas Akhir',
                `Tour singkat fitur di halaman ini. ${roleNote}`
            ),
            [
                step('#buttongenerateaccessqr', 'Kode QR akses', 'Pratinjau kode QR tautan Cloudflare statis yang tersimpan. Lihat dan cetak untuk petugas atau koordinator. Tautan tidak berubah selamanya.', 'bottom'),
                step('#dashboardstats', 'Kartu ringkasan', 'Angka cepat: total pengunjung, total tugas akhir, serta status masa simpan (belum atau sudah 10 tahun).', 'bottom'),
                step('#statvisitors', 'Total pengunjung', 'Jumlah kunjungan yang tercatat di modul Pengunjung.', 'bottom'),
                step('#statfinalprojects', 'Total tugas akhir', 'Jumlah seluruh dokumen tugas akhir yang tersimpan.', 'bottom'),
                step('#statundertenyears', 'Belum 10 tahun', 'Tugas akhir yang masih dalam masa simpan aktif (kurang dari 10 tahun).', 'bottom'),
                step('#statovertenyears', 'Sudah 10 tahun', 'Tugas akhir yang sudah melewati batas 10 tahun — biasanya terkait arsip atau pemusnahan.', 'bottom'),
                step('#chartbarcard', 'Grafik batang', 'Perbandingan jumlah tugas akhir yang belum dan yang sudah berumur 10 tahun.', 'top'),
                step('#chartpiecard', 'Grafik lingkaran', 'Persentase komposisi tugas akhir berdasarkan status masa simpan.', 'top')
            ]
        ),

        finalproject: () => {
            ensureFinalProjectMenuOpen();
            return withChrome(
                centerStep(
                    'Modul Tugas Akhir',
                    `Kelola dokumen ${document.getElementById('finalprojectcontext')?.dataset?.type || 'tugas akhir'}. ${roleNote}`
                ),
                [
                    step('.hero', 'Judul halaman', 'Jenis tugas akhir aktif dan ringkasan fitur halaman ini.', 'bottom'),
                    step(
                        '#buttonaddfinalproject',
                        'Tambah data',
                        mutateHint(
                            'Buka formulir untuk menambah dokumen tugas akhir baru.',
                            'Koordinator tidak menambah data — unduh berkas dari detail baris.'
                        ),
                        'bottom'
                    ),
                    step(
                        '#buttondeleteallfinalproject',
                        'Hapus dipilih',
                        mutateHint(
                            'Centang baris di tabel lalu hapus data yang dipilih.',
                            'Penghapusan hanya tersedia untuk Petugas.'
                        ),
                        'bottom'
                    ),
                    step(
                        '#finalprojecttable',
                        'Tabel & unduh berkas',
                        'Cari dan buka detail baris. Dari detail Anda dapat mengunduh berkas tugas akhir dan lembar persetujuan.',
                        'top'
                    )
                ]
            );
        },

        penyerahan: () => withChrome(
            centerStep('Modul Penyerahan', `Pencatatan penyerahan tugas akhir digital dari mahasiswa. ${roleNote}`),
            tableCommon('#buttonaddsubmission', '#buttondeleteallsubmission', '#submissiontable', 'penyerahan')
        ),

        pengunjung: () => withChrome(
            centerStep('Modul Pengunjung', `Pencatatan kunjungan perpustakaan. ${roleNote}`),
            [
                ...tableCommon('#buttonaddvisitor', '#buttondeleteallvisitor', '#visitortable', 'pengunjung'),
                step(
                    '#buttondownloadvisitor',
                    'Unduh pengunjung',
                    'Cetak daftar pengunjung dalam tabel rapi kertas A4 mendatar.',
                    'bottom'
                )
            ]
        ),

        pemusnahan: () => withChrome(
            centerStep(
                'Modul Pemusnahan',
                isOfficer
                    ? 'Ajukan dan kelola pemusnahan tugas akhir yang tidak berlaku. Koordinator dapat menyetujui dari tabel.'
                    : 'Tinjau dan setujui usulan pemusnahan tugas akhir. Anda tidak menambah atau menghapus data.'
            ),
            [
                ...tableCommon('#buttonadddestruction', '#buttondeletealldestruction', '#destructiontable', 'pemusnahan'),
                step(
                    '#destructiontable',
                    'Persetujuan',
                    isCoordinator
                        ? 'Gunakan kontrol persetujuan di tabel untuk menyetujui usulan pemusnahan.'
                        : 'Setelah diajukan, Koordinator dapat menyetujui usulan dari kolom tabel.',
                    'top'
                )
            ]
        ),

        bebaspustaka: () => withChrome(
            centerStep('Modul Bebas Pustaka', `Pencatatan bebas pustaka mahasiswa. ${roleNote}`),
            [
                ...tableCommon('#buttonaddlibraryclearance', '#buttondeletealllibraryclearance', '#libraryclearancetable', 'bebas pustaka'),
                step('#libraryclearancetable .icon-primary', 'Unduh surat', 'Unduh surat keterangan bebas pinjaman buku untuk baris tersebut.', 'left')
            ]
        ),

        hibahbuku: () => withChrome(
            centerStep('Modul Hibah Buku', `Pencatatan hibah buku terkait tugas akhir. ${roleNote}`),
            [
                ...tableCommon('#buttonaddbookgrant', '#buttondeleteallbookgrant', '#bookgranttable', 'hibah buku'),
                step('#bookgranttable .icon-primary', 'Unduh surat', 'Unduh tanda terima penyerahan hibah buku untuk baris tersebut.', 'left')
            ]
        ),

        kodebatang: () => withChrome(
            centerStep(
                'Modul Kode Batang',
                isOfficer
                    ? `Buat, unduh, dan pindai kode batang tugas akhir. ${roleNote}`
                    : 'Lihat dan unduh kode batang tugas akhir, serta cari data dengan memindai kamera. Anda tidak menambah atau menghapus data.'
            ),
            [
                ...tableCommon('#buttonaddbarcode', '#buttondeleteallbarcode', '#barcodetable', 'kode batang'),
                step(
                    '#buttondownloadbarcode',
                    'Unduh kode batang',
                    'Buka pratinjau cetak stiker kode batang berukuran A4 mendatar (15 stiker per lembar, 3 kolom × 5 baris), lalu tekan Cetak. Potong tiap kotak dan tempel pada tugas akhir.',
                    'bottom'
                ),
                step(
                    '#buttonscandevicebarcode',
                    'Pindai alat',
                    mutateHint(
                        'Gunakan pemindai kode batang fisik yang terhubung ke perangkat.',
                        'Pemindaian alat hanya untuk Petugas.'
                    ),
                    'bottom'
                ),
                step(
                    '#buttonscancamerabarcode',
                    'Pindai kamera',
                    'Buka pemindai kamera (langsung aktif). Pemindai membaca kode batang di kertas, layar monitor, atau layar ponsel.',
                    'bottom'
                ),
                step(
                    '#buttonscanimagebarcode',
                    'Pindai gambar',
                    'Pilih foto atau berkas PNG kode batang dari galeri jika ingin memindai tanpa kamera langsung.',
                    'bottom'
                )
            ]
        ),

        arsip: () => withChrome(
            centerStep('Modul Arsip', `Kelola arsip tugas akhir dan unduh daftar arsip. ${roleNote}`),
            [
                ...tableCommon('#buttonaddarchive', '#buttondeleteallarchive', '#archivetable', 'arsip'),
                step('#buttondownloadarchive', 'Unduh arsip', 'Cetak daftar arsip dalam tabel rapi kertas A4 mendatar.', 'bottom'),
                step('#buttondownloadarchivestudyprogram', 'Unduh per program studi', 'Cetak arsip per program studi dalam tabel A4 mendatar.', 'bottom')
            ]
        ),

        laporan: () => withChrome(
            centerStep('Modul Laporan', `Rekap laporan tugas akhir (kode batang, hibah, bebas pustaka, arsip). ${roleNote}`),
            [
                ...tableCommon('#buttonaddreport', '#buttondeleteallreport', '#reporttable', 'laporan'),
                step('#buttondownloadreport', 'Unduh laporan', 'Cetak daftar laporan dalam tabel rapi kertas A4 mendatar.', 'bottom'),
                step('#buttondownloadreportfinalprojectyear', 'Unduh per tahun tugas akhir', 'Cetak laporan per tahun tugas akhir dalam tabel A4 mendatar.', 'bottom')
            ]
        ),

        impor: () => withChrome(
            centerStep(
                'Modul Impor Tugas Akhir',
                isOfficer
                    ? 'Impor massal berkas tugas akhir digital. Fitur ini khusus Petugas.'
                    : 'Halaman impor hanya untuk Petugas. Koordinator tidak dapat mengimpor data.'
            ),
            [
                step('#buttonaddimport', 'Mulai impor', 'Pilih folder/berkas untuk diimpor ke sistem. Jika kunci dan model AI belum ada di basis data, impor ditahan dan muncul peringatan untuk mengisinya di Pengaturan atau memilih basis data proyek ini.', 'bottom'),
                step('#buttonsaveimport', 'Simpan impor', 'Simpan hasil impor yang sudah diverifikasi di tabel.', 'bottom'),
                step('#buttondeleteallimport', 'Hapus dipilih', 'Hapus baris impor sementara yang dipilih sebelum disimpan.', 'bottom'),
                step('#importtable', 'Tabel pratinjau', 'Periksa data hasil baca impor sebelum disimpan permanen.', 'top')
            ]
        ),

        prodi: () => withChrome(
            centerStep('Modul Program Studi', `Master data program studi FMIPA. ${roleNote}`),
            tableCommon('#buttonaddstudyprogram', '#buttondeleteallstudyprogram', '#studyprogramtable', 'program studi')
        ),

        keanggotaan: () => withChrome(
            centerStep('Modul Keanggotaan', `Data keanggotaan perpustakaan mahasiswa. ${roleNote}`),
            [
                ...tableCommon('#buttonaddmembership', '#buttondeleteallmembership', '#membershiptable', 'keanggotaan'),
                step('#membershiptable .icon-primary', 'Unduh surat', 'Unduh formulir keanggotaan perpustakaan untuk mahasiswa pada baris tersebut.', 'left')
            ]
        ),

        jenista: () => withChrome(
            centerStep('Modul Jenis tugas akhir', `Master jenis tugas akhir (skripsi, tesis, dll.). ${roleNote}`),
            tableCommon('#buttonaddfinalprojecttype', '#buttondeleteallfinalprojecttype', '#finalprojecttypetable', 'jenis tugas akhir')
        ),

        pengguna: () => withChrome(
            centerStep(
                'Modul Pengguna',
                isOfficer
                    ? 'Kelola akun Petugas, Koordinator, dan Mahasiswa. Akun Mahasiswa hanya untuk layanan kunjungan, cari tugas akhir, pindai, penyerahan tugas akhir, keanggotaan, dan bukti penyerahan.'
                    : 'Lihat daftar pengguna sistem. Penambahan/penghapusan hanya oleh Petugas.'
            ),
            [
                ...tableCommon('#buttonadduser', '#buttondeletealluser', '#usertable', 'pengguna'),
                step(
                    '#usertable',
                    'Akses penyerahan dan anggota per akun',
                    mutateHint(
                        'Untuk peran Mahasiswa, sakelar penyerahan dan keanggotaan bawaan tertutup saat akun ditambah atau diubah. Hanya petugas yang dapat membukanya dari kolom Pengaturan. Nyala berarti terbuka, mati berarti tertutup. Sakelar penyerahan, pengunjung, dan kode batang untuk seluruh mahasiswa ada di menu Pengaturan.',
                        'Status akses penyerahan dan keanggotaan per akun Mahasiswa. Bawaan tertutup; hanya petugas yang dapat membuka.'
                    ),
                    'top'
                )
            ]
        ),

        profil: () => withChrome(
            centerStep(
                (isOfficer || isCoordinator) ? 'Profil Pengguna' : 'Pengaturan Profil',
                'Perbarui data akun Anda di sistem Arsip Tugas Akhir.'
            ),
            [
                step('#profileidentitynumber', 'NIP', 'Nomor Induk Pegawai Anda. Opsional untuk diperbarui.', 'bottom'),
                step('#profilename', 'Nama', 'Nama lengkap yang tampil di profil dan catatan aktivitas.', 'bottom'),
                step('#profileemail', 'Surel', 'Surel akun untuk komunikasi dan identifikasi.', 'bottom'),
                step('#profilerole', 'Peran', 'Peran akun (Petugas/Koordinator) tidak dapat diubah dari halaman ini.', 'bottom'),
                step('#profilepassword', 'Kata sandi baru', 'Isi hanya jika ingin mengganti kata sandi. Kosongkan jika tidak diganti.', 'bottom'),
                step('#buttonprofile', 'Simpan profil', 'Simpan perubahan profil ke peladen.', 'top')
            ]
        ),

        pengaturan: () => withChrome(
            centerStep(
                'Pengaturan',
                'Atur kunci dan model kecerdasan buatan untuk impor tugas akhir, lalu tentukan folder penyimpanan berkas tugas akhir dan lembar persetujuan. Setelah lokasi dipilih, berkas tersusun di ARSIP TUGAS AKHIR menurut jenis, tahun, rumpun ilmu, dan program studi. Hanya folder yang berisi berkas yang disimpan. Atur juga akses pengunjung, penyerahan, dan kode batang untuk seluruh mahasiswa.'
            ),
            [
                step('#aisettings', 'Kecerdasan buatan impor', 'Isi kunci API dan nama model yang dipakai saat impor tugas akhir membaca sampul. Keduanya disimpan di basis data. Kunci yang sudah tersimpan tidak perlu diisi ulang.', 'bottom'),
                step('#settingstoragepath', 'Folder penyimpanan', 'Lokasi folder yang sedang dipakai. Setelah dipilih, folder ARSIP TUGAS AKHIR disusun menurut jenis, tahun, rumpun ilmu, lalu program studi, dan hanya dibuat untuk berkas tugas akhir atau lembar persetujuan yang ada. Folder kosong dan folder rumpun atau program studi yang namanya sama tidak digandakan. Kosong berarti folder bawaan aplikasi.', 'bottom'),
                step('#buttonpickstorage', 'Pilih folder', 'Buka daftar penyimpanan yang terhubung, lalu pilih folder pada penyimpanan internal atau eksternal.', 'bottom'),
                step('#buttonsetting', 'Simpan pengaturan', 'Simpan lokasi folder. Saat berkas dipindah, progres tampil secara langsung. Perubahan akses mahasiswa tidak memakai tombol ini — sakelar di bawah tersimpan langsung.', 'top'),
                step(
                    '#visitoraccesstoggle',
                    'Akses pengunjung',
                    'Nyala = buka formulir kunjungan publik (/kunjungan) untuk semua mahasiswa. Mati = tutup; halaman kunjungan menampilkan bahwa akses sedang ditutup oleh petugas.',
                    'left'
                ),
                step(
                    '#submissionaccesstoggle',
                    'Akses penyerahan',
                    'Nyala = buka formulir penyerahan publik (/serah) secara global. Mati = tutup untuk semua mahasiswa. Akses per akun tetap diatur di modul Pengguna.',
                    'left'
                ),
                step(
                    '#barcodeaccesstoggle',
                    'Akses kode batang',
                    'Nyala = buka halaman pindai publik (/pindai) tanpa masuk sebagai petugas. Mati = tutup; mahasiswa melihat bahwa akses pindai sedang ditutup oleh petugas.',
                    'left'
                )
            ]
        ),

        serverlokal: () => withChrome(
            centerStep(
                'Peladen di komputer ini',
                'Komputer ini menjadi peladen. Luring hanya petugas di komputer ini yang menjalankan aplikasi. Daring, klien yang menerima tautan dapat masuk selama komputer menyala. Tidak perlu sewa peladen.'
            ),
            [
                step('#cloudflaresettings', 'Tautan kode QR', 'Tempel satu tautan Worker Cloudflare, sekali. Setelah disimpan, tautan itu terkunci dan kode QR tidak berubah. Ponsel memakai tautan itu.', 'bottom'),
                step('#devicecapacity', 'Kapasitas memori', 'Bandingkan sisa ruang perangkat dengan ukuran basis data dan berkas arsip tugas akhir. Kedua persentase ditampilkan terpisah.', 'bottom'),
                step('#installplacement', 'Basis data dan berkas', 'Pakai berkas basis data lama atau buat baru, dan pilih folder ARSIP TUGAS AKHIR yang sudah ada atau buat baru.', 'bottom'),
                step('#localdatabaseexport', 'Ekspor dan impor', 'Unduh basis data untuk komputer lain, atau impor berkas dari pemasangan lain agar data yang sama dipakai di setiap aplikasi.', 'top'),
                step('#localserverinstall', 'Pasang semua', 'Unduh PHP, basis data, dan tautan internet, lalu buat pintasan. Berlaku di Windows, Linux, macOS, dan Unix lain.', 'bottom'),
                step('#localserveruninstall', 'Copot pemasangan', 'Timpa isi berkas, lalu hapus folder aplikasi, registri, catatan, pintasan, dan sisa berkas secara permanen. Berlaku di Windows, Linux, macOS, dan Unix lain. Bisa juga lewat klik kanan pintasan ARSIP-TA, lalu Copot.', 'bottom'),
                step('#localservershare', 'Bagikan', 'Pakai lagi tautan Worker yang sudah disimpan. Tautan tidak berganti saat jaringan berubah. Hentikan tautan jika hanya ingin dipakai di komputer ini.', 'bottom'),
                step('#localserverdatabase', 'Panel basis data', 'Lihat tabel di komputer ini. Pengganti phpMyAdmin, dan tidak terbuka dari tautan internet.', 'top')
            ]
        ),

        basisdata: () => withChrome(
            centerStep(
                'Panel basis data',
                'Tampilan baca tabel pada komputer tempat aplikasi dipasang. Kata sandi disembunyikan. Ubah data lewat modul aplikasi.'
            ),
            [
                step('#localdatabaseexport', 'Ekspor dan impor', 'Unduh berkas basis data, atau impor berkas dari komputer lain. Folder ARSIP TUGAS AKHIR disalin terpisah.', 'bottom'),
                step('#localdatabasetables', 'Tabel', 'Pilih tabel yang ingin dilihat.', 'bottom')
            ]
        )
    };

    const resolveTourKey = () => {
        if (isPage('/dasbor')) return 'dasbor';
        if (path.includes('/tugas-akhir/') || isPage('/tesis')) return 'finalproject';
        if (isPage('/penyerahan')) return 'penyerahan';
        if (isPage('/pengunjung')) return 'pengunjung';
        if (isPage('/pemusnahan')) return 'pemusnahan';
        if (isPage('/bebaspustaka')) return 'bebaspustaka';
        if (isPage('/hibahbuku')) return 'hibahbuku';
        if (isPage('/kodebatang')) return 'kodebatang';
        if (isPage('/arsip')) return 'arsip';
        if (isPage('/laporan')) return 'laporan';
        if (isPage('/impor')) return 'impor';
        if (isPage('/prodi')) return 'prodi';
        if (isPage('/keanggotaan')) return 'keanggotaan';
        if (isPage('/jenista')) return 'jenista';
        if (isPage('/pengguna')) return 'pengguna';
        if (isPage('/profil')) return 'profil';
        if (isPage('/pengaturan')) return 'pengaturan';
        if (path === '/peladen-lokal/basisdata' || path.endsWith('/peladen-lokal/basisdata')) return 'basisdata';
        if (isPage('/peladen-lokal')) return 'serverlokal';
        return 'dasbor';
    };

    let activeDriver = null;

    const startTour = () => {
        ensureDesktopSidebarExpanded();

        const key = resolveTourKey();
        const build = pageTours[key] || pageTours.dasbor;
        const steps = filterSteps(build());

        if (!steps.length) {
            if (typeof showAlert === 'function') {
                showAlert('Petunjuk tidak tersedia.', 'Tidak ada elemen yang dapat ditampilkan di halaman ini.');
            }
            return;
        }

        if (activeDriver) {
            activeDriver.destroy();
            activeDriver = null;
        }

        const helpBtn = document.getElementById('helptourbtn');
        helpBtn?.classList.add('is-tour-running');

        const mobile = isMobileView();

        // This Driver.js build does not register default nextClick/prevClick/closeClick handlers.
        // Without an explicit handler, Next does not advance the step (the tour looks like it "vanished").
        activeDriver = createDriver({
            showProgress: true,
            animate: true,
            allowClose: true,
            smoothScroll: true,
            overlayOpacity: mobile ? 0.72 : 0.55,
            stagePadding: mobile ? 14 : 8,
            stageRadius: mobile ? 14 : 12,
            popoverOffset: mobile ? 16 : 12,
            // Do not close the tour when the overlay is clicked — avoid an accidental dismiss on a phone.
            overlayClickBehavior: () => {},
            popoverClass: 'help-tour-popover',
            nextBtnText: 'Lanjut',
            prevBtnText: 'Kembali',
            doneBtnText: 'Selesai',
            progressText: '{{current}} / {{total}}',
            disableActiveInteraction: true,
            steps,
            onPopoverRender: (popover) => {
                if (!isMobileView() || !popover?.wrapper) return;
                const el = popover.wrapper;
                const width = Math.min(window.innerWidth - 16, window.innerWidth);
                el.style.width = `${width}px`;
                el.style.maxWidth = `${width}px`;
                el.style.left = '8px';
                el.style.right = 'auto';
                el.style.transform = 'none';

                const rect = el.getBoundingClientRect();
                const maxTop = Math.max(8, window.innerHeight - rect.height - 8);
                if (rect.top < 8) el.style.top = '8px';
                else if (rect.bottom > window.innerHeight - 8) el.style.top = `${maxTop}px`;
            },
            onNextClick: (_element, _step, { driver }) => {
                if (driver.isLastStep()) {
                    driver.destroy();
                    return;
                }
                driver.moveNext();
            },
            onPrevClick: (_element, _step, { driver }) => {
                driver.movePrevious();
            },
            onCloseClick: (_element, _step, { driver }) => {
                driver.destroy();
            },
            onHighlightStarted: (element, tourStep) => {
                syncSidebarForTourStep(tourStep);
                if (element && element.id !== 'driver-dummy-element') {
                    try {
                        element.scrollIntoView({ behavior: 'auto', block: 'center', inline: 'nearest' });
                    } catch (_) {
                        element.scrollIntoView(true);
                    }
                }
            },
            onDestroyed: () => {
                helpBtn?.classList.remove('is-tour-running');
                resetTourSidebarState();
                activeDriver = null;
            }
        });

        syncSidebarForTourStep(steps[0]);
        activeDriver.drive();
    };

    document.addEventListener('DOMContentLoaded', () => {
        const helpBtn = document.getElementById('helptourbtn');
        if (!helpBtn) return;

        helpBtn.addEventListener('click', event => {
            event.preventDefault();
            startTour();
        });
    });
})();
