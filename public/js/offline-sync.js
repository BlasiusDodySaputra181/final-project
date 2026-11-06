/**
 * Orkestrasi mode offline (petugas) untuk modul CRUD:
 * online  -> server -> salinan IndexedDB
 * offline -> IndexedDB + outbox
 * online kembali -> flush via PwaRegister
 *
 * Kecuali: /reader/import (Groq) tetap online-only.
 * SW / banner / queueAction inti: public/js/pwa-register.js
 */
const OfflineSync = (() => {

    const isOffline = () =>
        (typeof PwaRegister !== 'undefined' && PwaRegister.isOffline)
            ? PwaRegister.isOffline()
            : !navigator.onLine;

    /**
     * Blokir aksi yang wajib online (unduh PDF/surat, dll).
     * Reader AI Groq punya helper sendiri di component-imports.js.
     */
    const requireOnline = (header, paragraph) => {
        if (!isOffline()) return true;

        if (typeof showError === 'function') {
            showError(header, paragraph);
        } else if (typeof showAlert === 'function') {
            showAlert(header, paragraph);
        }

        return false;
    };

    const requestBackgroundSync = () =>
        (typeof PwaRegister !== 'undefined' && PwaRegister.requestBackgroundSync)
            ? PwaRegister.requestBackgroundSync()
            : Promise.resolve();

    /**
     * Registri modul CRUD offline. import/reader (Groq) tidak didaftarkan.
     */
    // --- Peta modul yang disalin offline ---

    const MODULES = {
        finalproject: {
            indexKey: 'finalprojects',
            idField: 'finalprojectid',
            saveUrl: '/storefinalproject',
            deleteUrl: id => `/finalproject/destroy/${id}`,
            fileFields: ['final_project_file', 'approval_sheet'],
            tableId: 'finalprojecttable',
            getTable: () => typeof finalProjectTable !== 'undefined' ? finalProjectTable : null,
            label: 'Tugas Akhir'
        },
        submission: {
            indexKey: 'submissions',
            idField: 'submissionid',
            saveUrl: '/storesubmission',
            deleteUrl: id => `/submission/destroy/${id}`,
            fileFields: ['final_project_file', 'approval_sheet'],
            tableId: 'submissiontable',
            getTable: () => typeof submissionTable !== 'undefined' ? submissionTable : null,
            label: 'Penyerahan'
        },
        studyprogram: {
            indexKey: 'studyprograms',
            idField: 'studyprogramid',
            saveUrl: '/storestudyprogram',
            deleteUrl: id => `/studyprogram/destroy/${id}`,
            fileFields: [],
            tableId: 'studyprogramtable',
            getTable: () => typeof studyProgramTable !== 'undefined' ? studyProgramTable : null,
            label: 'Program Studi'
        },
        membership: {
            indexKey: 'memberships',
            idField: 'membershipid',
            saveUrl: '/storemembership',
            deleteUrl: id => `/membership/destroy/${id}`,
            fileFields: [],
            tableId: 'membershiptable',
            getTable: () => typeof membershipTable !== 'undefined' ? membershipTable : null,
            label: 'Keanggotaan'
        },
        finalprojecttype: {
            indexKey: 'finalprojecttypes',
            idField: 'finalprojecttypeid',
            saveUrl: '/storefinalprojecttype',
            deleteUrl: id => `/finalprojecttype/destroy/${id}`,
            fileFields: [],
            tableId: 'finalprojecttypetable',
            getTable: () => typeof finalProjectTypeTable !== 'undefined' ? finalProjectTypeTable : null,
            label: 'Jenis Tugas Akhir'
        },
        visitor: {
            indexKey: 'visitors',
            idField: 'visitorid',
            saveUrl: '/storevisitor',
            deleteUrl: id => `/visitor/destroy/${id}`,
            fileFields: [],
            tableId: 'visitortable',
            getTable: () => typeof visitorTable !== 'undefined' ? visitorTable : null,
            label: 'Pengunjung'
        },
        bookgrant: {
            indexKey: 'bookgrants',
            idField: 'bookgrantid',
            saveUrl: '/storebookgrant',
            deleteUrl: id => `/bookgrant/destroy/${id}`,
            fileFields: [],
            tableId: 'bookgranttable',
            getTable: () => typeof bookGrantTable !== 'undefined' ? bookGrantTable : null,
            label: 'Hibah Buku'
        },
        libraryclearance: {
            indexKey: 'libraryclearances',
            idField: 'libraryclearanceid',
            saveUrl: '/storelibraryclearance',
            deleteUrl: id => `/libraryclearance/destroy/${id}`,
            fileFields: [],
            tableId: 'libraryclearancetable',
            getTable: () => typeof libraryClearanceTable !== 'undefined' ? libraryClearanceTable : null,
            label: 'Bebas Pustaka'
        },
        barcode: {
            indexKey: 'barcodes',
            idField: 'barcodeid',
            saveUrl: '/storebarcode',
            deleteUrl: id => `/barcode/destroy/${id}`,
            fileFields: [],
            tableId: 'barcodetable',
            getTable: () => typeof barcodeTable !== 'undefined' ? barcodeTable : null,
            label: 'Kode Batang'
        },
        destruction: {
            indexKey: 'destructions',
            idField: 'destructionid',
            saveUrl: '/storedestruction',
            deleteUrl: id => `/destruction/destroy/${id}`,
            fileFields: [],
            tableId: 'destructiontable',
            getTable: () => typeof destructionTable !== 'undefined' ? destructionTable : null,
            label: 'Pemusnahan'
        },
        archive: {
            indexKey: 'archives',
            idField: 'archiveid',
            saveUrl: '/storearchive',
            deleteUrl: id => `/archive/destroy/${id}`,
            fileFields: [],
            tableId: 'archivetable',
            getTable: () => typeof archiveTable !== 'undefined' ? archiveTable : null,
            label: 'Arsip'
        },
        report: {
            indexKey: 'reports',
            idField: 'reportid',
            saveUrl: '/storereport',
            deleteUrl: id => `/report/destroy/${id}`,
            fileFields: [],
            tableId: 'reporttable',
            getTable: () => typeof reportTable !== 'undefined' ? reportTable : null,
            label: 'Laporan'
        },
        user: {
            indexKey: 'users',
            idField: 'userid',
            saveUrl: '/storeuser',
            deleteUrl: id => `/user/destroy/${id}`,
            fileFields: [],
            tableId: 'usertable',
            getTable: () => typeof userTable !== 'undefined' ? userTable : null,
            label: 'Pengguna'
        }
    };

    // --- Tanggal dan status masa berlaku ---

    const formatIndonesianDate = date => {
        const parsed = (typeof parseDateValue === 'function')
            ? parseDateValue(date instanceof Date ? date.toLocaleDateString('sv-SE') : date)
            : (date instanceof Date ? date : new Date(date));

        if (!parsed || Number.isNaN(parsed.getTime())) {
            const fallback = date instanceof Date ? date : new Date(date);
            if (Number.isNaN(fallback.getTime())) return '';
            return fallback.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
        }

        return parsed.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    };

    function buildStatus(year) {
        const currentYear = new Date().getFullYear();
        const numericYear = Number(year);
        if (!numericYear) return 'Tidak Berlaku';
        return (numericYear <= currentYear && currentYear - numericYear <= 10) ? 'Berlaku' : 'Tidak Berlaku';
    }

    function formatValidityDeadline(year) {
        if (year === null || year === undefined || year === '' || year === '-') return '-';
        const numericYear = Number(year);
        const remainingYears = 10 - (new Date().getFullYear() - numericYear);
        if (remainingYears <= 0) return 'Sudah berakhir';
        if (remainingYears === 1) return '1 tahun tersisa';
        return `${remainingYears} tahun tersisa`;
    }

    async function findFinalProjectRow(finalProjectId) {
        const keys = await listFinalProjectIndexKeys();
        for (const key of keys) {
            const row = (await OfflineDatabase.getIndexRows(key))
                .find(item => String(item[1]) === String(finalProjectId));
            if (row) return row;
        }

        return (await OfflineDatabase.getIndexRows('submissions'))
            .find(row => String(row[1]) === String(finalProjectId)) ?? null;
    }

    async function listFinalProjectIndexKeys() {
        const base = MODULES.finalproject.indexKey;
        const keys = [base];
        const typeId = typeof getFinalProjectTypeContext === 'function'
            ? getFinalProjectTypeContext()?.id
            : null;
        if (typeId) keys.unshift(`${base}:type:${typeId}`);
        return keys;
    }

    function resolveIndexKey(moduleName, params = {}, fields = {}) {
        const module = getModule(moduleName);
        if (moduleName === 'finalproject') {
            const typeId = params.type_id || fields.final_project_type_id;
            if (typeId) return `${module.indexKey}:type:${typeId}`;
        }
        return module.indexKey;
    }

    async function findUserName(userId) {
        const user = (await OfflineDatabase.getIndexRows('users'))
            .find(row => String(row[1]) === String(userId));
        return user?.[3] ?? '-';
    }

    function getModule(moduleName) {
        const module = MODULES[moduleName];
        if (!module) throw new Error(`Modul luring tidak dikenal: ${moduleName}`);
        return module;
    }

    async function getOfflineIndexRows(moduleName, params = {}) {
        const indexKey = resolveIndexKey(moduleName, params);
        const rows = await OfflineDatabase.getIndexRows(indexKey);
        rows.forEach((row, i) => { row[0] = i + 1; });
        return rows;
    }

    function reloadModuleTable(moduleName) {
        const module = getModule(moduleName);
        if (!module.tableId) return;
        const table = module.getTable();
        if (table && document.getElementById(module.tableId)) {
            table.ajax.reload(null, false);
        }
    }

    function reloadAllModuleTables() {
        Object.keys(MODULES).forEach(reloadModuleTable);
    }

    // ---------- Builder baris index lokal (optimistic UI) ----------

    async function buildLocalRow(moduleName, fields, existing = null) {
        const now = formatIndonesianDate(new Date());
        const idField = MODULES[moduleName].idField;
        const id = fields[idField];

        switch (moduleName) {
            case 'finalproject':
            case 'submission': {
                const year = Number(fields.final_project_year);
                const submissionDate = fields.submission_date
                    ? formatIndonesianDate(fields.submission_date)
                    : (existing?.[8] ?? '');

                return [
                    0,
                    id,
                    fields.nim,
                    fields.student_name,
                    fields.final_project_title,
                    fields.program_study,
                    fields.degree_level,
                    year,
                    submissionDate,
                    buildStatus(year),
                    existing?.[10] ?? null,
                    existing?.[11] ?? null,
                    existing?.[12] ?? now,
                    now,
                    fields.final_project_type_id || existing?.[14] || null
                ];
            }

            case 'studyprogram':
                return [
                    0,
                    id,
                    fields.study_program,
                    fields.cluster,
                    existing?.[4] ?? now,
                    now
                ];

            case 'membership':
                return [
                    0,
                    id,
                    existing?.[2] ?? '-',
                    existing?.[3] ?? '-',
                    existing?.[4] ?? '-',
                    fields.gender,
                    formatIndonesianDate(fields.date_of_birth) || fields.date_of_birth || '-',
                    fields.origin_address,
                    fields.current_address,
                    existing?.[9] ?? now,
                    now,
                    fields.user_id,
                    fields.study_program_id,
                    existing?.[13] ?? '-',
                    existing?.[14] ?? '-',
                    existing?.[15] ?? '-',
                    fields.place_of_birth ?? '',
                    fields.date_of_birth ?? ''
                ];

            case 'finalprojecttype':
                return [
                    0,
                    id,
                    fields.level,
                    fields.type,
                    existing?.[4] ?? now,
                    now
                ];

            case 'visitor':
                return [
                    0,
                    id,
                    fields.nim,
                    fields.student_name,
                    fields.program_study,
                    fields.needs,
                    fields.supervision,
                    existing?.[7] ?? now,
                    now,
                    fields.user_id ?? existing?.[9] ?? null
                ];

            case 'user':
                return [
                    0,
                    id,
                    fields.identity_number || '-',
                    fields.name,
                    fields.email,
                    fields.role || '-',
                    existing?.[6] ?? now,
                    now,
                    fields.role === 'Mahasiswa' ? 0 : (existing?.[8] ?? 1),
                    fields.role === 'Mahasiswa' ? 0 : (existing?.[9] ?? 1)
                ];

            case 'bookgrant': {
                const fp = await findFinalProjectRow(fields.final_project_id);
                return [
                    0,
                    id,
                    fp?.[2] ?? existing?.[2] ?? '-',
                    fp?.[3] ?? existing?.[3] ?? '-',
                    existing?.[4] ?? '-',
                    fp?.[5] ?? existing?.[5] ?? '-',
                    fp?.[6] ?? existing?.[6] ?? '-',
                    fields.membership_registered || '-',
                    existing?.[8] ?? '-',
                    fields.book_title,
                    fields.book_year,
                    fields.author,
                    existing?.[12] ?? now,
                    now,
                    fields.final_project_id
                ];
            }

            case 'libraryclearance': {
                const fp = await findFinalProjectRow(fields.final_project_id);
                return [
                    0,
                    id,
                    fp?.[2] ?? existing?.[2] ?? '-',
                    fp?.[3] ?? existing?.[3] ?? '-',
                    existing?.[4] ?? '-',
                    fp?.[5] ?? existing?.[5] ?? '-',
                    fp?.[6] ?? existing?.[6] ?? '-',
                    fields.membership_registered || '-',
                    fields.email,
                    fields.phone_number,
                    fields.graduation_year,
                    fields.semester_graduated,
                    existing?.[12] ?? now,
                    now,
                    fields.final_project_id
                ];
            }

            case 'barcode': {
                const fp = await findFinalProjectRow(fields.final_project_id);
                const year = fp?.[7] ?? existing?.[7] ?? '-';
                return [
                    0,
                    id,
                    fp?.[2] ?? existing?.[2] ?? '-',
                    fp?.[3] ?? existing?.[3] ?? '-',
                    fp?.[4] ?? existing?.[4] ?? '-',
                    fp?.[5] ?? existing?.[5] ?? '-',
                    fp?.[6] ?? existing?.[6] ?? '-',
                    year,
                    existing?.[8] ?? '-',
                    existing?.[9] ?? '-',
                    fp?.[8] ?? existing?.[10] ?? '-',
                    buildStatus(year),
                    fields.label,
                    fields.barcode,
                    existing?.[14] ?? now,
                    now,
                    fields.final_project_id
                ];
            }

            case 'destruction': {
                const fp = await findFinalProjectRow(fields.final_project_id);
                return [
                    0,
                    id,
                    fp?.[2] ?? existing?.[2] ?? '-',
                    fp?.[3] ?? existing?.[3] ?? '-',
                    fp?.[4] ?? existing?.[4] ?? '-',
                    fp?.[5] ?? existing?.[5] ?? '-',
                    fp?.[6] ?? existing?.[6] ?? '-',
                    fp?.[7] ?? existing?.[7] ?? '-',
                    fp?.[8] ?? existing?.[8] ?? '-',
                    fp?.[9] ?? existing?.[9] ?? 'Tidak Berlaku',
                    fields.description || '-',
                    fields.agreement || 'Belum Disetujui',
                    existing?.[12] ?? '-',
                    existing?.[13] ?? now,
                    now,
                    fields.final_project_id,
                    fields.user_id ?? null
                ];
            }

            case 'archive': {
                const fp = await findFinalProjectRow(fields.final_project_id);
                const year = fp?.[7] ?? existing?.[7] ?? '-';
                return [
                    0,
                    id,
                    fp?.[2] ?? existing?.[2] ?? '-',
                    fp?.[3] ?? existing?.[3] ?? '-',
                    fp?.[4] ?? existing?.[4] ?? '-',
                    fp?.[5] ?? existing?.[5] ?? '-',
                    fp?.[6] ?? existing?.[6] ?? '-',
                    year,
                    fp?.[8] ?? existing?.[8] ?? '-',
                    buildStatus(year),
                    formatValidityDeadline(year),
                    fields.description || '-',
                    existing?.[12] ?? now,
                    now,
                    fields.final_project_id
                ];
            }

            case 'report': {
                const fp = await findFinalProjectRow(fields.final_project_id);
                const editor = await findUserName(fields.user_id);
                const year = fp?.[7] ?? existing?.[7] ?? '-';
                return [
                    0,
                    id,
                    fp?.[2] ?? existing?.[2] ?? '-',
                    fp?.[3] ?? existing?.[3] ?? '-',
                    fp?.[4] ?? existing?.[4] ?? '-',
                    fp?.[5] ?? existing?.[5] ?? '-',
                    fp?.[6] ?? existing?.[6] ?? '-',
                    year,
                    existing?.[8] ?? '-',
                    existing?.[9] ?? '-',
                    fp?.[8] ?? existing?.[10] ?? '-',
                    buildStatus(year),
                    existing?.[12] ?? '-',
                    existing?.[13] ?? 'Tidak',
                    existing?.[14] ?? 'Tidak',
                    formatValidityDeadline(year),
                    existing?.[16] ?? '-',
                    fields.description || '-',
                    editor !== '-' ? editor : (existing?.[18] ?? '-'),
                    existing?.[19] ?? now,
                    now,
                    fields.final_project_id,
                    fields.user_id
                ];
            }

            default:
                return existing ?? [0, id];
        }
    }

    // ---------- Perubahan lokal (optimistic) pada salinan index ----------

    async function applySaveLocally(moduleName, fields) {
        const module = getModule(moduleName);
        const indexKey = resolveIndexKey(moduleName, {}, fields);
        const rows = await OfflineDatabase.getIndexRows(indexKey);
        const id = fields[module.idField];
        const existingIndex = rows.findIndex(row => String(row[1]) === String(id));
        const existing = existingIndex >= 0 ? rows[existingIndex] : null;
        const nextRow = await buildLocalRow(moduleName, fields, existing);

        if (existingIndex >= 0) {
            rows[existingIndex] = nextRow;
        } else {
            rows.push(nextRow);
        }

        rows.forEach((row, i) => { row[0] = i + 1; });
        await OfflineDatabase.saveIndexRows(indexKey, rows);
        reloadModuleTable(moduleName);
    }

    async function applyDeleteLocally(moduleName, id) {
        const module = getModule(moduleName);
        const typeId = typeof getFinalProjectTypeContext === 'function'
            ? getFinalProjectTypeContext()?.id
            : null;
        const indexKey = resolveIndexKey(moduleName, typeId ? { type_id: typeId } : {});
        const rows = (await OfflineDatabase.getIndexRows(indexKey))
            .filter(row => String(row[1]) !== String(id));

        rows.forEach((row, i) => { row[0] = i + 1; });
        await OfflineDatabase.saveIndexRows(indexKey, rows);
        reloadModuleTable(moduleName);
    }

    async function applyDestructionApprovalLocally(ids, approved) {
        const serverIds = ids.filter(id => !OfflineDatabase.isLocalId(id) && Number.isFinite(Number(id)));
        if (!serverIds.length) return;

        const idSet = new Set(serverIds.map(String));
        const rows = await OfflineDatabase.getIndexRows('destructions');
        let changed = false;

        rows.forEach(row => {
            if (!idSet.has(String(row[1]))) return;
            row[11] = approved ? 'Disetujui' : 'Belum Disetujui';
            changed = true;
        });

        if (changed) {
            await OfflineDatabase.saveIndexRows('destructions', rows);
            reloadModuleTable('destruction');
        }
    }

    async function applyVisitorSupervisionLocally(ids, supervised) {
        const serverIds = ids.filter(id => !OfflineDatabase.isLocalId(id) && Number.isFinite(Number(id)));
        if (!serverIds.length) return;

        const idSet = new Set(serverIds.map(String));
        const rows = await OfflineDatabase.getIndexRows('visitors');
        let changed = false;
        const supervisorName = (typeof authUserName !== 'undefined' && authUserName)
            ? authUserName
            : (document.body?.dataset?.userName || '');
        const supervisorId = (typeof authUserId !== 'undefined' && authUserId)
            ? Number(authUserId)
            : Number(document.body?.dataset?.userId || 0);

        rows.forEach(row => {
            if (!idSet.has(String(row[1]))) return;
            row[6] = supervised ? (supervisorName || row[6]) : 'Mandiri';
            row[9] = supervised ? (supervisorId || null) : null;
            changed = true;
        });

        if (changed) {
            await OfflineDatabase.saveIndexRows('visitors', rows);
            reloadModuleTable('visitor');
        }
    }

    // ---------- Antrean operasi (outbox) ----------

    const BARCODE_UNIQUE_MESSAGE =
        'Kode batang sudah digunakan. Tidak dapat menyimpan data dengan kode yang sama.';

    /**
     * Validasi unik barcode offline (index IndexedDB + outbox pending).
     * Kolom kode di baris index: index 13 (selaras buildLocalRow).
     */
    async function assertBarcodeUnique(fields) {
        const code = String(fields.barcode || '').trim();
        if (!code) return;

        const currentId = String(fields.barcodeid ?? '');
        const rows = await OfflineDatabase.getIndexRows('barcodes');
        const duplicateRow = rows.find(row =>
            String(row[13] || '').trim() === code && String(row[1]) !== currentId
        );

        if (duplicateRow) {
            throw new Error(BARCODE_UNIQUE_MESSAGE);
        }

        const outbox = await OfflineDatabase.getOutboxItems();
        const duplicatePending = outbox.find(item =>
            item.module === 'barcode' &&
            item.type === 'save' &&
            String(item.fields?.barcode || '').trim() === code &&
            String(item.fields?.barcodeid ?? '') !== currentId
        );

        if (duplicatePending) {
            throw new Error(BARCODE_UNIQUE_MESSAGE);
        }
    }

    async function queueSave(moduleName, fields, files = {}) {
        const module = getModule(moduleName);

        if (moduleName === 'barcode') {
            await assertBarcodeUnique(fields);
        }

        const localId = fields[module.idField] ?? `local-${Date.now()}`;
        fields[module.idField] = localId;

        const key = `save:${moduleName}:${localId}`;
        const existing = await OfflineDatabase.getOutboxItem(key);

        const mergedFiles = {};
        module.fileFields.forEach(field => {
            mergedFiles[field] = files[field] ?? existing?.files?.[field] ?? null;
        });

        await OfflineDatabase.putOutboxItem({
            key,
            module: moduleName,
            type: 'save',
            queuedAt: existing?.queuedAt ?? Date.now(),
            idField: module.idField,
            saveUrl: module.saveUrl,
            fields,
            files: mergedFiles
        });

        await applySaveLocally(moduleName, fields);
        await requestBackgroundSync();
    }

    async function queueDelete(moduleName, id, token) {
        getModule(moduleName);

        if (OfflineDatabase.isLocalId(id)) {
            await OfflineDatabase.deleteOutboxItem(`save:${moduleName}:${id}`);
        } else {
            await OfflineDatabase.deleteOutboxItem(`save:${moduleName}:${id}`);
            await OfflineDatabase.putOutboxItem({
                key: `delete:${moduleName}:${id}`,
                module: moduleName,
                type: 'delete',
                queuedAt: Date.now(),
                id,
                token,
                deleteUrl: MODULES[moduleName].deleteUrl(id)
            });
        }

        await applyDeleteLocally(moduleName, id);
        await requestBackgroundSync();
    }

    async function queueAction(options) {
        if (typeof PwaRegister !== 'undefined' && PwaRegister.queueAction) {
            return PwaRegister.queueAction(options);
        }

        await OfflineDatabase.putOutboxItem({
            key: options.key || `action:${options.url}:${Date.now()}`,
            module: options.module || 'action',
            type: 'action',
            queuedAt: Date.now(),
            url: options.url,
            fields: options.fields || {},
            files: options.files || {}
        });
        await requestBackgroundSync();
    }

    /**
     * Batch impor -> POST /storeimport (bukan /reader/import).
     * Indeks berkas mengikuti indeks baris agar selaras dengan Processor.
     */
    async function queueImportBatch(token, rows, resolveFiles) {
        const fields = {
            _token: token,
            'nim[]': [],
            'student_name[]': [],
            'final_project_title[]': [],
            'program_study[]': [],
            'degree_level[]': [],
            'final_project_type_id[]': [],
            'final_project_year[]': [],
            'submission_date[]': []
        };

        const files = {};

        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            fields['nim[]'].push(row[1]);
            fields['student_name[]'].push(row[2]);
            fields['final_project_title[]'].push(row[3]);
            fields['program_study[]'].push(row[4]);
            fields['degree_level[]'].push(row[5]);
            fields['final_project_type_id[]'].push(row[20] || '');
            fields['final_project_year[]'].push(row[6]);
            fields['submission_date[]'].push(row[7]);

            const pair = typeof resolveFiles === 'function' ? resolveFiles(row[0]) : {};
            if (pair?.final_project_file instanceof File) {
                files[`final_project_file[${i}]`] = pair.final_project_file;
            }
            if (pair?.approval_sheet instanceof File) {
                files[`approval_sheet[${i}]`] = pair.approval_sheet;
            }
        }

        await queueAction({
            key: `action:storeimport:${Date.now()}`,
            url: '/storeimport',
            module: 'import',
            fields,
            files
        });
    }

    async function queueDestructionApproval(token, ids, approved) {
        const serverIds = ids
            .map(Number)
            .filter(id => Number.isFinite(id) && id > 0);

        if (!serverIds.length) return;

        await queueAction({
            key: `action:destruction-approve:${serverIds.join(',')}:${approved ? 1 : 0}`,
            url: '/destruction/approve',
            module: 'destruction',
            fields: {
                _token: token,
                ids: serverIds,
                approved: approved ? 1 : 0
            }
        });

        await applyDestructionApprovalLocally(serverIds, approved);
    }

    async function queueVisitorSupervision(token, ids, supervised) {
        const serverIds = ids
            .map(Number)
            .filter(id => Number.isFinite(id) && id > 0);

        if (!serverIds.length) return;

        await queueAction({
            key: `action:visitor-supervise:${serverIds.join(',')}:${supervised ? 1 : 0}`,
            url: '/visitor/supervise',
            module: 'visitor',
            fields: {
                _token: token,
                ids: serverIds,
                supervised: supervised ? 1 : 0
            }
        });

        await applyVisitorSupervisionLocally(serverIds, supervised);
    }

    async function applyUserSubmissionAccessLocally(userId, open) {
        const id = String(userId);
        if (!id) return;

        const rows = await OfflineDatabase.getIndexRows('users');
        let changed = false;

        rows.forEach(row => {
            if (String(row[1]) !== id) return;
            row[8] = open ? 1 : 0;
            changed = true;
        });

        if (changed) {
            await OfflineDatabase.saveIndexRows('users', rows);
            reloadModuleTable('user');
        }
    }

    async function queueUserSubmissionAccess(token, userId, open) {
        const id = Number(userId);
        if (!Number.isFinite(id) || id <= 0) return;

        await queueAction({
            key: `action:user-submission-access:${id}:${open ? 1 : 0}`,
            url: '/user/submission-access',
            module: 'user',
            fields: {
                _token: token,
                id,
                open: open ? 1 : 0
            }
        });

        await applyUserSubmissionAccessLocally(id, open);
    }

    async function applyUserMembershipAccessLocally(userId, open) {
        const id = String(userId);
        if (!id) return;

        const rows = await OfflineDatabase.getIndexRows('users');
        let changed = false;

        rows.forEach(row => {
            if (String(row[1]) !== id) return;
            row[9] = open ? 1 : 0;
            changed = true;
        });

        if (changed) {
            await OfflineDatabase.saveIndexRows('users', rows);
            reloadModuleTable('user');
        }
    }

    async function queueUserMembershipAccess(token, userId, open) {
        const id = Number(userId);
        if (!Number.isFinite(id) || id <= 0) return;

        await queueAction({
            key: `action:user-membership-access:${id}:${open ? 1 : 0}`,
            url: '/user/membership-access',
            module: 'user',
            fields: {
                _token: token,
                id,
                open: open ? 1 : 0
            }
        });

        await applyUserMembershipAccessLocally(id, open);
    }

    async function flushAndReload() {
        if (isOffline()) return 0;

        if (typeof PwaRegister !== 'undefined' && PwaRegister.flushOutboxIfPossible) {
            return PwaRegister.flushOutboxIfPossible();
        }

        const sent = await OfflineDatabase.flushOutbox();
        if (sent > 0) reloadAllModuleTables();
        return sent;
    }

    // SW/banner/flush inti di pwa-register.js — reload tabel setelah flush sukses
    window.addEventListener('pwa:flushed', event => {
        if ((event.detail?.sent || 0) > 0) reloadAllModuleTables();
    });

    window.addEventListener('online', () => {
        // PwaRegister sudah flush; pastikan tabel ikut diperbarui
        if (!isOffline()) {
            setTimeout(() => reloadAllModuleTables(), 300);
        }
    });

    return {
        MODULES,
        isOffline,
        requireOnline,
        resolveIndexKey,
        getOfflineIndexRows,
        queueSave,
        queueDelete,
        queueAction,
        queueImportBatch,
        queueDestructionApproval,
        queueVisitorSupervision,
        queueUserSubmissionAccess,
        queueUserMembershipAccess,
        flushAndReload,
        reloadModuleTable
    };

})();
