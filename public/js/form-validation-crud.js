/* Peta perawatan — form-validation-crud.js
 * Anak dari setup klien lewat crudClient (token, peran, kesalahan AJAX).
 * 1. Validasi tiap formulir
 * 2. Tambah  3. Impor  4. Unduh berkas
 * 5. Ubah  6. Hapus satu  7. Hapus massal  8. Konfirmasi AJAX
 */
$(document).ready(function() {
    /* ----------------------------------------------------------------
     * BAGIAN: Validasi formulir
     * Aturan dan pesan tiap #...form. Perbaikan dimulai di blok form yang bersangkutan.
     * ---------------------------------------------------------------- */
    $('#finalprojectform').validate({
        errorElement: 'div',
        errorClass: 'validation-tooltip',
        ignore: ':hidden:not(#studyprogramme)',
    
        rules: {
            nim: 'required',
            studentsname: 'required',
            studyprogramme: 'required',
            finalyearproject: 'required',
            submissiondate: 'required',
            finalprojecttitle: 'required'
        },
    
        messages: {
            nim: 'NIM harus diisi!',
            studentsname: 'Nama mahasiswa harus diisi!',
            studyprogramme: 'Program studi harus dipilih!',
            finalyearproject: 'Tahun tugas akhir harus diisi!',
            submissiondate: 'Tanggal penyerahan harus diisi!',
            finalprojecttitle: 'Judul tugas akhir harus diisi!'
        },
    
        highlight(element) {
            $(element).addClass('input-error');
    
            if (element.name === 'studyprogramme') {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .addClass('input-error');
            }
        },
    
        unhighlight(element) {
            $(element).removeClass('input-error');
    
            if (element.name === 'studyprogramme') {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .removeClass('input-error');
            }
    
            const fieldKey = element.name;
    
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
    
            $('#' + fieldKey + '-error').remove();
        },
    
        errorPlacement(errorElement, inputElement) {
            const fieldKey = inputElement.attr('name');
            const tooltipId = fieldKey + '-error';
    
            $('#' + tooltipId).remove();
    
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
    
            errorElement.attr('id', tooltipId);
    
            $('body').append(errorElement);
    
            const referenceElement =
                fieldKey === 'studyprogramme'
                    ? inputElement
                        .closest('.custom-select')
                        .find('.custom-select-trigger')[0]
                    : inputElement[0];
    
            poppers[fieldKey] = Popper.createPopper(
                referenceElement,
                errorElement[0],
                {
                    placement:
                        window.innerWidth <= 768
                            ? 'bottom'
                            : 'top-end',
    
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 8]
                            }
                        },
                        {
                            name: 'flip',
                            options: {
                                fallbackPlacements: [
                                    'bottom',
                                    'bottom-start',
                                    'bottom-end',
                                    'top',
                                    'left',
                                    'right'
                                ]
                            }
                        },
                        {
                            name: 'preventOverflow',
                            options: {
                                boundary: document.body,
                                padding: 8
                            }
                        }
                    ]
                }
            );
        },
    
        success(labelElement) {
            const fieldKey = labelElement
                .attr('id')
                ?.replace('-error', '');
    
            if (!fieldKey) {
                labelElement.remove();
                return;
            }
    
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
    
            labelElement.remove();
        },
    
        submitHandler(form) {
            const selected = getSelectedFinalProject();
            const typeContext = getFinalProjectTypeContext();
    
            const fields = {
                _token: csrfToken,
                degree_level: typeContext.level,
                final_project_type_id: typeContext.id,
                finalprojectid: selected.id,
                nim: form.nim.value,
                student_name: form.studentsname.value,
                program_study: form.studyprogramme.value,
                final_project_year: form.finalyearproject.value,
                final_project_title: form.finalprojecttitle.value,
                submission_date:
                    submissionDatepicker.selectedDates[0]
                        ?.toLocaleDateString('sv-SE')
            };
    
            const files = {
                final_project_file:
                    $('[name=finalprojectfile]')[0].files[0] ?? null,
                approval_sheet:
                    $('[name=approvalsheet]')[0].files[0] ?? null
            };
    
            const queueOffline = async () => {
                await OfflineSync.queueSave(
                    'finalproject',
                    fields,
                    files
                );
    
                closeModal();
    
                showSuccess(
                    'Tersimpan secara luring.',
                    `Data ${typeContext.type} disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.`
                );
            };
    
            if (OfflineSync.isOffline()) {
                return void queueOffline();
            }
    
            const formData = new FormData();
    
            Object.entries({
                ...fields,
                ...files
            }).forEach(([key, value]) => {
                if (value != null && value !== '') {
                    formData.append(key, value);
                }
            });
    
            $.ajax({
                url: '/storefinalproject',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
    
                success: () => {
                    closeModal();
    
                    finalProjectTable.ajax.reload(
                        null,
                        false
                    );
    
                    showSuccess(
                        `Data ${typeContext.type} berhasil disimpan.`,
                        `Data ${typeContext.type} telah berhasil disimpan ke basis data.`
                    );
                },
    
                error: xhr =>
                    xhr.status === 0
                        ? queueOffline()
                        : showError(
                            `Data ${typeContext.type} gagal disimpan.`,
                            (typeof firstValidationMessage === 'function' ? firstValidationMessage(xhr.responseJSON) : null)
                                || 'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                        )
            });
        }
    });

    /* ----------------------------------------------------------------
     * BAGIAN: Validasi — Penyerahan
     * Cari: #submissionform
     * ---------------------------------------------------------------- */
    $('#submissionform').validate({
        errorElement: 'div',
        errorClass: 'validation-tooltip',
        ignore: ':hidden:not(#submissionstudyprogramme):not(#submissiondegreelevel)',
        rules: {
            submissionnim: 'required',
            submissionstudentsname: 'required',
            submissionstudyprogramme: 'required',
            submissiondegreelevel: 'required',
            submissionfinalyearproject: 'required',
            submissionsubmissiondate: 'required',
            submissionfinalprojecttitle: 'required'
        },
        messages: {
            submissionnim: 'NIM harus diisi!',
            submissionstudentsname: 'Nama mahasiswa harus diisi!',
            submissionstudyprogramme: 'Program studi harus dipilih!',
            submissiondegreelevel: 'Jenjang harus dipilih!',
            submissionfinalyearproject: 'Tahun tugas akhir harus diisi!',
            submissionsubmissiondate: 'Tanggal penyerahan harus diisi!',
            submissionfinalprojecttitle: 'Judul tugas akhir harus diisi!'
        },
        highlight(element) {
            $(element).addClass('input-error');
        
            if (
                ['submissionstudyprogramme', 'submissiondegreelevel']
                    .includes(element.name)
            ) {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .addClass('input-error');
            }
        },
        
        unhighlight(element) {
            $(element).removeClass('input-error');
        
            if (
                ['submissionstudyprogramme', 'submissiondegreelevel']
                    .includes(element.name)
            ) {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .removeClass('input-error');
            }
        
            const fieldKey = element.name;
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            $('#' + fieldKey + '-error').remove();
        },
        
        errorPlacement(errorElement, inputElement) {
            const fieldKey = inputElement.attr('name');
            const tooltipId = fieldKey + '-error';
        
            $('#' + tooltipId).remove();
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            errorElement.attr('id', tooltipId);
        
            $('body').append(errorElement);
        
            const referenceElement =
                ['submissionstudyprogramme', 'submissiondegreelevel']
                    .includes(fieldKey)
                    ? inputElement
                        .closest('.custom-select')
                        .find('.custom-select-trigger')[0]
                    : inputElement[0];
        
            poppers[fieldKey] = Popper.createPopper(
                referenceElement,
                errorElement[0],
                {
                    placement:
                        window.innerWidth <= 768
                            ? 'bottom'
                            : 'top-end',
        
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 8]
                            }
                        },
                        {
                            name: 'flip',
                            options: {
                                fallbackPlacements: [
                                    'bottom',
                                    'bottom-start',
                                    'bottom-end',
                                    'top',
                                    'left',
                                    'right'
                                ]
                            }
                        },
                        {
                            name: 'preventOverflow',
                            options: {
                                boundary: document.body,
                                padding: 8
                            }
                        }
                    ]
                }
            );
        },
        
        success(labelElement) {
            const fieldKey = labelElement
                .attr('id')
                ?.replace('-error', '');
        
            if (!fieldKey) {
                labelElement.remove();
                return;
            }
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            labelElement.remove();
        },
        submitHandler(form) {
            const selected = getSelectedSubmission();

            const fields = {
                _token: csrfToken,
                submissionid: selected.id,
                nim: form.submissionnim.value,
                student_name: form.submissionstudentsname.value,
                program_study: form.submissionstudyprogramme.value,
                degree_level: form.submissiondegreelevel.value,
                final_project_type_id: form.submissionfinalprojecttypeid?.value || '',
                final_project_year: form.submissionfinalyearproject.value,
                final_project_title: form.submissionfinalprojecttitle.value,
                submission_date: submissionSubmissionDatepicker.selectedDates[0]?.toLocaleDateString('sv-SE')
            };

            const files = {
                final_project_file: $('[name=submissionfinalprojectfile]')[0].files[0] ?? null,
                approval_sheet: $('[name=submissionapprovalsheet]')[0].files[0] ?? null
            };

            const queueOffline = async () => {
                await OfflineSync.queueSave('submission', fields, files);
                closeModal();
                showSuccess(
                    'Tersimpan secara luring.',
                    'Data Penyerahan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            const formData = new FormData();
            Object.entries({ ...fields, ...files }).forEach(([key, value]) => {
                if (value != null && value !== '') formData.append(key, value);
            });

            $.ajax({
                url: '/storesubmission',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: () => (
                    closeModal(),
                    submissionTable.ajax.reload(null, false),
                    showSuccess(
                        'Data Penyerahan berhasil disimpan.',
                        'Data Penyerahan telah berhasil disimpan ke basis data.'
                    )
                ),
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Data Penyerahan gagal disimpan.',
                        (typeof firstValidationMessage === 'function' ? firstValidationMessage(xhr.responseJSON) : null)
                            || 'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        }
    });

    /* ----------------------------------------------------------------
     * BAGIAN: Validasi — Impor
     * Cari: #importform
     * ---------------------------------------------------------------- */
    $('#importform').validate({
        errorElement: 'div',
        errorClass: 'validation-tooltip',
        ignore: ':hidden:not(#importstudyprogramme):not(#importdegreelevel)',
        rules: {
            importnim: 'required',
            importstudentsname: 'required',
            importstudyprogramme: 'required',
            importdegreelevel: 'required',
            importfinalyearproject: 'required',
            importsubmissiondate: 'required',
            importfinalprojecttitle: 'required'
        },
        messages: {
            importnim: 'NIM harus diisi!',
            importstudentsname: 'Nama mahasiswa harus diisi!',
            importstudyprogramme: 'Program studi harus dipilih!',
            importdegreelevel: 'Jenjang harus dipilih!',
            importfinalyearproject: 'Tahun tugas akhir harus diisi!',
            importsubmissiondate: 'Tanggal penyerahan harus diisi!',
            importfinalprojecttitle: 'Judul tugas akhir harus diisi!'
        },
        highlight(element) {
            $(element).addClass('input-error');
        
            if (['importstudyprogramme', 'importdegreelevel'].includes(element.name)) {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .addClass('input-error');
            }
        },
        
        unhighlight(element) {
            $(element).removeClass('input-error');
        
            if (['importstudyprogramme', 'importdegreelevel'].includes(element.name)) {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .removeClass('input-error');
            }
        
            const fieldKey = element.name;
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            $('#' + fieldKey + '-error').remove();
        },
        
        errorPlacement(errorElement, inputElement) {
            const fieldKey = inputElement.attr('name');
            const tooltipId = fieldKey + '-error';
        
            $('#' + tooltipId).remove();
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            errorElement.attr('id', tooltipId);
        
            $('body').append(errorElement);
        
            const referenceElement =
                ['importstudyprogramme', 'importdegreelevel'].includes(fieldKey)
                    ? inputElement
                          .closest('.custom-select')
                          .find('.custom-select-trigger')[0]
                    : inputElement[0];
        
            poppers[fieldKey] = Popper.createPopper(
                referenceElement,
                errorElement[0],
                {
                    placement:
                        window.innerWidth <= 768
                            ? 'bottom'
                            : 'top-end',
        
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 8]
                            }
                        },
                        {
                            name: 'flip',
                            options: {
                                fallbackPlacements: [
                                    'bottom',
                                    'bottom-start',
                                    'bottom-end',
                                    'top',
                                    'left',
                                    'right'
                                ]
                            }
                        },
                        {
                            name: 'preventOverflow',
                            options: {
                                boundary: document.body,
                                padding: 8
                            }
                        }
                    ]
                }
            );
        },
        
        success(labelElement) {
            const fieldKey = labelElement
                .attr('id')
                ?.replace('-error', '');
        
            if (!fieldKey) {
                labelElement.remove();
                return;
            }
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            labelElement.remove();
        },
        submitHandler: function(form, event) {

            event.preventDefault();

            const selected = JSON.parse($('#buttonimport').attr('data-id'));

            const row = editedImportRow.data();
            row[1] = form.importnim.value;
            row[2] = form.importstudentsname.value;
            row[3] = form.importfinalprojecttitle.value;
            row[4] = form.importstudyprogramme.value;
            row[5] = form.importdegreelevel.value;
            row[20] = form.importfinalprojecttypeid?.value || '';
            row[6] = Number(form.importfinalyearproject.value);
            row[7] = form.importsubmissiondate.value;

            const finalProjectFile = $('[name=importfinalprojectfile]')[0].files[0];

            if (finalProjectFile) {
                importFileStore.set(selected.id, finalProjectFile); // simpan File baru
                row[8] = finalProjectFile.name;                     // tampilkan nama di DataTable
            }

            const approvalSheetFile = $('[name=importapprovalsheet]')[0].files[0];

            if (approvalSheetFile) {
                approvalSheetStore.set(selected.id, approvalSheetFile);
                row[9] = approvalSheetFile.name;
            }

            editedImportRow.data(row).invalidate();

            importTable.draw(false);

            refreshOpenedImportDetails();

            closeModal();
        }

    });

    /* ----------------------------------------------------------------
     * BAGIAN: Validasi — Folder impor
     * Cari: #folderform
     * ---------------------------------------------------------------- */
    $('#folderform').validate({
        ignore: [],
        errorElement: 'div',
        errorClass: 'validation-tooltip',

        rules: {
            folderfile: {
                required: true
            }
        },

        messages: {
            folderfile: {
                required: 'Folder tugas akhir harus diisi!'
            }
        },

        highlight(element) {
            $(element).addClass('input-error');
        
            if (element.name === 'folderfile') {
                $('.upload-box').addClass('input-error');
            }
        },
        
        unhighlight(element) {
            $(element).removeClass('input-error');
        
            if (element.name === 'folderfile') {
                $('.upload-box').removeClass('input-error');
            }
        
            const fieldKey = element.name;
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            $('#' + fieldKey + '-error').remove();
        },
        
        errorPlacement(errorElement, inputElement) {
            const fieldKey = inputElement.attr('name');
            const tooltipId = fieldKey + '-error';
        
            $('#' + tooltipId).remove();
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            errorElement.attr('id', tooltipId);
        
            $('body').append(errorElement);
        
            poppers[fieldKey] = Popper.createPopper(
                inputElement[0],
                errorElement[0],
                {
                    placement:
                        window.innerWidth <= 768
                            ? 'bottom'
                            : 'top-end',
        
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 8]
                            }
                        },
                        {
                            name: 'flip',
                            options: {
                                fallbackPlacements: [
                                    'bottom',
                                    'bottom-start',
                                    'bottom-end',
                                    'top',
                                    'left',
                                    'right'
                                ]
                            }
                        },
                        {
                            name: 'preventOverflow',
                            options: {
                                boundary: document.body,
                                padding: 8
                            }
                        }
                    ]
                }
            );
        },
        
        success(labelElement) {
            const fieldKey = labelElement
                .attr('id')
                ?.replace('-error', '');
        
            if (!fieldKey) {
                labelElement.remove();
                return;
            }
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            labelElement.remove();
        },

        submitHandler: function(form, event) {

            event.preventDefault();

            // Reader AI (/reader/import) tidak dapat dijalankan offline
            if (typeof ensureAiImportOnline === 'function' && !ensureAiImportOnline()) {
                return;
            }

            (async () => {
                closeModal();

                const ok = await importPdfFiles();

                if (!ok) return;

                showSuccess(
                    'Data Impor berhasil disimpan.',
                    'Data impor tugas akhir telah berhasil disimpan ke data tabel.'
                );

            })();
        }
    });

    /* ----------------------------------------------------------------
     * BAGIAN: Validasi — Program Studi
     * Cari: #studyprogramform
     * ---------------------------------------------------------------- */
    $('#studyprogramform').validate({
        errorElement: 'div',
        errorClass: 'validation-tooltip',
        rules: {
            studyprogramname: 'required',
            studyprogramcluster: 'required'
        },
        messages: {
            studyprogramname: 'Program studi harus diisi!',
            studyprogramcluster: 'Rumpun ilmu harus diisi!'
        },
        highlight(element) {
            $(element).addClass('input-error');
        },
        
        unhighlight(element) {
            $(element).removeClass('input-error');
        
            const fieldKey = element.name;
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            $('#' + fieldKey + '-error').remove();
        },
        
        errorPlacement(errorElement, inputElement) {
            const fieldKey = inputElement.attr('name');
            const tooltipId = fieldKey + '-error';
        
            $('#' + tooltipId).remove();
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            errorElement.attr('id', tooltipId);
        
            $('body').append(errorElement);
        
            poppers[fieldKey] = Popper.createPopper(
                inputElement[0],
                errorElement[0],
                {
                    placement:
                        window.innerWidth <= 768
                            ? 'bottom'
                            : 'top-end',
        
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 8]
                            }
                        },
                        {
                            name: 'flip',
                            options: {
                                fallbackPlacements: [
                                    'bottom',
                                    'bottom-start',
                                    'bottom-end',
                                    'top',
                                    'left',
                                    'right'
                                ]
                            }
                        },
                        {
                            name: 'preventOverflow',
                            options: {
                                boundary: document.body,
                                padding: 8
                            }
                        }
                    ]
                }
            );
        },
        
        success(labelElement) {
            const fieldKey = labelElement
                .attr('id')
                ?.replace('-error', '');
        
            if (!fieldKey) {
                labelElement.remove();
                return;
            }
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            labelElement.remove();
        },
        submitHandler(form) {
            const selected = getSelectedStudyProgram();

            const fields = {
                _token: csrfToken,
                studyprogramid: selected.id,
                study_program: form.studyprogramname.value,
                cluster: form.studyprogramcluster.value
            };

            const queueOffline = async () => {
                await OfflineSync.queueSave('studyprogram', fields);
                closeModal();
                showSuccess(
                    'Tersimpan secara luring.',
                    'Data Program Studi disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: '/storestudyprogram',
                type: 'POST',
                data: fields,
                success: () => (
                    closeModal(),
                    studyProgramTable.ajax.reload(null, false),
                    refreshStudyProgramOptions(),
                    showSuccess(
                        'Data Program Studi berhasil disimpan.',
                        'Data Program Studi telah berhasil disimpan ke basis data.'
                    )
                ),
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Data Program Studi gagal disimpan.',
                        'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        }
    });

    /* ----------------------------------------------------------------
     * BAGIAN: Validasi — Pengguna
     * Cari: #userform
     * ---------------------------------------------------------------- */
    $('#userform').validate({
        errorElement: 'div',
        errorClass: 'validation-tooltip',
        ignore: ':hidden:not(#userrole)',
        rules: {
            useridentitynumber: 'required',
            username: 'required',
            useremail: { required: true, email: true },
            userpassword: {
                required() {
                    return !getSelectedUser().id;
                }
            },
            userrole: 'required'
        },
        messages: {
            useridentitynumber: 'Nomor identitas harus diisi!',
            username: 'Nama harus diisi!',
            useremail: {
                required: 'Email harus diisi!',
                email: 'Format email tidak valid!'
            },
            userpassword: 'Kata sandi wajib diisi.',
            userrole: 'Peran harus dipilih!'
        },
        highlight(element) {
            $(element).addClass('input-error');
        
            if (element.name === 'userrole') {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .addClass('input-error');
            }
        },
        
        unhighlight(element) {
            $(element).removeClass('input-error');
        
            if (element.name === 'userrole') {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .removeClass('input-error');
            }
        
            const fieldKey = element.name;
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            $('#' + fieldKey + '-error').remove();
        },
        
        errorPlacement(errorElement, inputElement) {
            const fieldKey = inputElement.attr('name');
            const tooltipId = fieldKey + '-error';
        
            $('#' + tooltipId).remove();
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            errorElement.attr('id', tooltipId);
        
            $('body').append(errorElement);
        
            const referenceElement =
                fieldKey === 'userrole'
                    ? inputElement
                          .closest('.custom-select')
                          .find('.custom-select-trigger')[0]
                    : inputElement[0];
        
            poppers[fieldKey] = Popper.createPopper(
                referenceElement,
                errorElement[0],
                {
                    placement:
                        window.innerWidth <= 768
                            ? 'bottom'
                            : 'top-end',
        
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 8]
                            }
                        },
                        {
                            name: 'flip',
                            options: {
                                fallbackPlacements: [
                                    'bottom',
                                    'bottom-start',
                                    'bottom-end',
                                    'top',
                                    'left',
                                    'right'
                                ]
                            }
                        },
                        {
                            name: 'preventOverflow',
                            options: {
                                boundary: document.body,
                                padding: 8
                            }
                        }
                    ]
                }
            );
        },
        
        success(labelElement) {
            const fieldKey = labelElement
                .attr('id')
                ?.replace('-error', '');
        
            if (!fieldKey) {
                labelElement.remove();
                return;
            }
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            labelElement.remove();
        },
        submitHandler(form) {
            const selected = getSelectedUser();

            const fields = {
                _token: csrfToken,
                userid: selected.id,
                identity_number: form.useridentitynumber.value,
                name: form.username.value,
                email: form.useremail.value,
                password: form.userpassword.value,
                role: form.userrole.value
            };

            const queueOffline = async () => {
                await OfflineSync.queueSave('user', fields);
                closeModal();
                showSuccess(
                    'Tersimpan secara luring.',
                    'Data Pengguna disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: '/storeuser',
                type: 'POST',
                data: fields,
                success: () => (
                    closeModal(),
                    userTable.ajax.reload(null, false),
                    showSuccess(
                        'Data Pengguna berhasil disimpan.',
                        'Data Pengguna telah berhasil disimpan ke basis data.'
                    )
                ),
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Data Pengguna gagal disimpan.',
                        xhr.responseJSON ?? 'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        }
    });
    
    /* ----------------------------------------------------------------
     * BAGIAN: Validasi — Profil
     * Cari: #profileform
     * ---------------------------------------------------------------- */
    $('#profileform').validate({
        errorElement: 'div',
        errorClass: 'validation-tooltip',
    
        rules: {
            name: 'required',
            email: {
                required: true,
                email: true
            },
            password: {
                minlength: 8
            },
            password_confirmation: {
                equalTo: '#profilepassword'
            }
        },
    
        messages: {
            name: 'Nama harus diisi!',
            email: {
                required: 'Email harus diisi!',
                email: 'Format email tidak valid!'
            },
            password: {
                minlength: 'Kata sandi minimal 8 karakter!'
            },
            password_confirmation: {
                equalTo: 'Konfirmasi kata sandi tidak cocok!'
            }
        },
    
        highlight(element) {
            $(element).addClass('input-error');
        },
    
        unhighlight(element) {
            $(element).removeClass('input-error');
    
            const fieldKey = element.name;
    
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
    
            $('#' + fieldKey + '-error').remove();
        },
    
        errorPlacement(errorElement, inputElement) {
            const fieldKey = inputElement.attr('name');
            const tooltipId = fieldKey + '-error';
    
            // Hapus tooltip lama bila masih ada
            $('#' + tooltipId).remove();
    
            // Hancurkan popper lama
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
    
            // Pastikan id konsisten
            errorElement.attr('id', tooltipId);
    
            // Pindahkan ke body untuk Popper
            $('body').append(errorElement);
    
            // Buat Popper baru
            poppers[fieldKey] = Popper.createPopper(
                inputElement[0],
                errorElement[0],
                {
                    placement: 'top-end',
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 8]
                            }
                        },
                        {
                            name: 'flip',
                            options: {
                                fallbackPlacements: [
                                    'bottom-end',
                                    'left',
                                    'right'
                                ]
                            }
                        }
                    ]
                }
            );
        },
    
        success(labelElement) {
            const fieldKey = labelElement.attr('id')?.replace('-error', '');
    
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
    
            labelElement.remove();
        },
    
        submitHandler(form) {
            const fields = {
                _token: csrfToken,
                identity_number: form.elements.namedItem('identity_number')?.value ?? '',
                name: form.name.value,
                email: form.email.value,
                password: form.password.value,
                password_confirmation: form.password_confirmation.value
            };
    
            const applyProfileUi = () => {
                form.password.value = '';
                form.password_confirmation.value = '';
    
                document
                    .querySelectorAll(
                        '.profile-info .profile-name, .profile-dropdown .profile-name'
                    )
                    .forEach(el => {
                        el.textContent = fields.name;
                    });
    
                const identityEl =
                    document.querySelector('.profile-dropdown .profile-role');
    
                if (identityEl) {
                    const digits = String(
                        fields.identity_number || ''
                    ).replace(/\D+/g, '');
    
                    identityEl.textContent =
                        digits.length === 18
                            ? `${digits.slice(0, 8)} ${digits.slice(8, 14)} ${digits.slice(14, 15)} ${digits.slice(15)}`
                            : (fields.identity_number || '—');
                }
            };
    
            const queueOffline = async () => {
                await OfflineSync.queueAction({
                    key: 'action:storeprofile',
                    url: '/storeprofile',
                    module: 'profile',
                    fields
                });
    
                applyProfileUi();
    
                showSuccess(
                    'Tersimpan secara luring.',
                    'Perubahan profil disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };
    
            if (OfflineSync.isOffline()) {
                return void crudClient.fail(
                    { status: 0 },
                    { queueOffline }
                );
            }
    
            $.ajax({
                url: '/storeprofile',
                type: 'POST',
                data: fields,
    
                success: message => {
                    applyProfileUi();
    
                    showSuccess(
                        'Profil diperbarui.',
                        typeof message === 'string'
                            ? message
                            : 'Data profil berhasil disimpan.'
                    );
                },
    
                error: (xhr, textStatus) =>
                    crudClient.fail(xhr, {
                        queueOffline,
                        failTitle: 'Gagal memperbarui profil.',
                        textStatus
                    })
            });
        }
    });

    /* ----------------------------------------------------------------
     * BAGIAN: Validasi — Jenis Tugas Akhir
     * Cari: #finalprojecttypeform
     * ---------------------------------------------------------------- */
    $('#finalprojecttypeform').validate({
        errorElement: 'div',
        errorClass: 'validation-tooltip',
        rules: {
            finalprojecttypelevel: 'required',
            finalprojecttypename: 'required'
        },
        messages: {
            finalprojecttypelevel: 'Jenjang harus diisi!',
            finalprojecttypename: 'Jenis harus diisi!'
        },
        highlight(element) {
            $(element).addClass('input-error');
        },
        
        unhighlight(element) {
            $(element).removeClass('input-error');
        
            const fieldKey = element.name;
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            $('#' + fieldKey + '-error').remove();
        },
        
        errorPlacement(errorElement, inputElement) {
            const fieldKey = inputElement.attr('name');
            const tooltipId = fieldKey + '-error';
        
            $('#' + tooltipId).remove();
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            errorElement.attr('id', tooltipId);
        
            $('body').append(errorElement);
        
            poppers[fieldKey] = Popper.createPopper(
                inputElement[0],
                errorElement[0],
                {
                    placement:
                        window.innerWidth <= 768
                            ? 'bottom'
                            : 'top-end',
        
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 8]
                            }
                        },
                        {
                            name: 'flip',
                            options: {
                                fallbackPlacements: [
                                    'bottom',
                                    'bottom-start',
                                    'bottom-end',
                                    'top',
                                    'left',
                                    'right'
                                ]
                            }
                        },
                        {
                            name: 'preventOverflow',
                            options: {
                                boundary: document.body,
                                padding: 8
                            }
                        }
                    ]
                }
            );
        },
        
        success(labelElement) {
            const fieldKey = labelElement
                .attr('id')
                ?.replace('-error', '');
        
            if (!fieldKey) {
                labelElement.remove();
                return;
            }
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            labelElement.remove();
        },
        submitHandler(form) {
            const selected = getSelectedFinalProjectType();

            const fields = {
                _token: csrfToken,
                finalprojecttypeid: selected.id,
                level: form.finalprojecttypelevel.value,
                type: form.finalprojecttypename.value
            };

            const queueOffline = async () => {
                await OfflineSync.queueSave('finalprojecttype', fields);
                closeModal();
                showSuccess(
                    'Tersimpan secara luring.',
                    'Data Jenis Tugas Akhir disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: '/storefinalprojecttype',
                type: 'POST',
                data: fields,
                success: () => (
                    closeModal(),
                    finalProjectTypeTable.ajax.reload(null, false),
                    refreshDegreeLevelOptions(),
                    showSuccess(
                        'Data Jenis Tugas Akhir berhasil disimpan.',
                        'Data Jenis Tugas Akhir telah berhasil disimpan ke basis data. Muat ulang halaman untuk memperbarui menu navigasi.'
                    )
                ),
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Data Jenis Tugas Akhir gagal disimpan.',
                        'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        }
    });

    const membershipSelectFields = [
        'membershipuserid',
        'membershipstudyprogramme',
        'membershipgender'
    ];

    /* ----------------------------------------------------------------
     * BAGIAN: Validasi — Keanggotaan
     * Cari: #membershipform
     * ---------------------------------------------------------------- */
    $('#membershipform').validate({
        errorElement: 'div',
        errorClass: 'validation-tooltip',
        ignore: ':hidden:not(#membershipuserid):not(#membershipstudyprogramme):not(#membershipgender)',
        rules: {
            membershipuserid: 'required',
            membershipstudyprogramme: 'required',
            membershipgender: 'required',
            membershipbirthdate: 'required',
            membershipbirthplace: {
                required: true,
                maxlength: 255
            },
            membershiporigin: 'required',
            membershipcurrent: 'required'
        },
        messages: {
            membershipuserid: 'Mahasiswa harus dipilih!',
            membershipstudyprogramme: 'Program studi harus dipilih!',
            membershipgender: 'Jenis kelamin harus dipilih!',
            membershipbirthdate: 'Tanggal lahir harus diisi!',
            membershipbirthplace: {
                required: 'Tempat lahir harus diisi!',
                maxlength: 'Tempat lahir maksimal 255 karakter!'
            },
            membershiporigin: 'Alamat asal harus diisi!',
            membershipcurrent: 'Alamat sekarang harus diisi!'
        },
        highlight(element) {
            $(element).addClass('input-error');

            if (membershipSelectFields.includes(element.name)) {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .addClass('input-error');
            }
        },

        unhighlight(element) {
            $(element).removeClass('input-error');

            if (membershipSelectFields.includes(element.name)) {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .removeClass('input-error');
            }

            const fieldKey = element.name;

            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];

            $('#' + fieldKey + '-error').remove();
        },

        errorPlacement(errorElement, inputElement) {
            const fieldKey = inputElement.attr('name');
            const tooltipId = fieldKey + '-error';

            $('#' + tooltipId).remove();

            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];

            errorElement.attr('id', tooltipId);

            $('body').append(errorElement);

            const referenceElement = membershipSelectFields.includes(fieldKey)
                ? inputElement
                    .closest('.custom-select')
                    .find('.custom-select-trigger')[0]
                : inputElement[0];

            poppers[fieldKey] = Popper.createPopper(
                referenceElement,
                errorElement[0],
                {
                    placement:
                        window.innerWidth <= 768
                            ? 'bottom'
                            : 'top-end',

                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 8]
                            }
                        },
                        {
                            name: 'flip',
                            options: {
                                fallbackPlacements: [
                                    'bottom',
                                    'bottom-start',
                                    'bottom-end',
                                    'top',
                                    'left',
                                    'right'
                                ]
                            }
                        },
                        {
                            name: 'preventOverflow',
                            options: {
                                boundary: document.body,
                                padding: 8
                            }
                        }
                    ]
                }
            );
        },

        success(labelElement) {
            const fieldKey = labelElement
                .attr('id')
                ?.replace('-error', '');

            if (!fieldKey) {
                labelElement.remove();
                return;
            }

            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];

            labelElement.remove();
        },
        submitHandler(form) {
            const selected = getSelectedMembership();

            const fields = {
                _token: csrfToken,
                membershipid: selected.id,
                user_id: form.membershipuserid.value,
                study_program_id: form.membershipstudyprogramme.value,
                gender: form.membershipgender.value,
                place_of_birth: form.membershipbirthplace.value,
                date_of_birth: membershipBirthDatepicker.selectedDates[0]
                    ?.toLocaleDateString('sv-SE'),
                origin_address: form.membershiporigin.value,
                current_address: form.membershipcurrent.value
            };

            const queueOffline = async () => {
                await OfflineSync.queueSave('membership', fields);
                closeModal();
                showSuccess(
                    'Tersimpan secara luring.',
                    'Data Keanggotaan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: '/storemembership',
                type: 'POST',
                data: fields,
                success: () => (
                    closeModal(),
                    membershipTable.ajax.reload(null, false),
                    showSuccess(
                        'Data Keanggotaan berhasil disimpan.',
                        'Data Keanggotaan telah berhasil disimpan ke basis data.'
                    )
                ),
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Data Keanggotaan gagal disimpan.',
                        firstValidationMessage(xhr.responseJSON)
                            || 'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        }
    });

    /* ----------------------------------------------------------------
     * BAGIAN: Validasi — Pengunjung
     * Cari: #visitorform
     * ---------------------------------------------------------------- */
    $('#visitorform').validate({
        errorElement: 'div',
        errorClass: 'validation-tooltip',
        ignore: ':hidden:not(#visitorprogramstudy)',
        rules: {
            visitornim: 'required',
            visitorstudentname: 'required',
            visitorprogramstudy: 'required',
            visitorneeds: 'required'
        },
        messages: {
            visitornim: 'NIM harus diisi!',
            visitorstudentname: 'Nama mahasiswa harus diisi!',
            visitorprogramstudy: 'Program studi harus dipilih!',
            visitorneeds: 'Keperluan harus diisi!'
        },
        highlight(element) {
            $(element).addClass('input-error');
        
            if (element.name === 'visitorprogramstudy') {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .addClass('input-error');
            }
        },
        
        unhighlight(element) {
            $(element).removeClass('input-error');
        
            if (element.name === 'visitorprogramstudy') {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .removeClass('input-error');
            }
        
            const fieldKey = element.name;
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            $('#' + fieldKey + '-error').remove();
        },
        
        errorPlacement(errorElement, inputElement) {
            const fieldKey = inputElement.attr('name');
            const tooltipId = fieldKey + '-error';
        
            $('#' + tooltipId).remove();
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            errorElement.attr('id', tooltipId);
        
            $('body').append(errorElement);
        
            const referenceElement =
                fieldKey === 'visitorprogramstudy'
                    ? inputElement
                          .closest('.custom-select')
                          .find('.custom-select-trigger')[0]
                    : inputElement[0];
        
            poppers[fieldKey] = Popper.createPopper(
                referenceElement,
                errorElement[0],
                {
                    placement:
                        window.innerWidth <= 768
                            ? 'bottom'
                            : 'top-end',
        
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 8]
                            }
                        },
                        {
                            name: 'flip',
                            options: {
                                fallbackPlacements: [
                                    'bottom',
                                    'bottom-start',
                                    'bottom-end',
                                    'top',
                                    'left',
                                    'right'
                                ]
                            }
                        },
                        {
                            name: 'preventOverflow',
                            options: {
                                boundary: document.body,
                                padding: 8
                            }
                        }
                    ]
                }
            );
        },
        
        success(labelElement) {
            const fieldKey = labelElement
                .attr('id')
                ?.replace('-error', '');
        
            if (!fieldKey) {
                labelElement.remove();
                return;
            }
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            labelElement.remove();
        },
        submitHandler(form) {
            const selected = getSelectedVisitor();

            const fields = {
                _token: csrfToken,
                visitorid: selected.id,
                nim: form.visitornim.value,
                student_name: form.visitorstudentname.value,
                program_study: form.visitorprogramstudy.value,
                needs: form.visitorneeds.value,
                supervision: authUserName,
                user_id: authUserId
            };

            const queueOffline = async () => {
                await OfflineSync.queueSave('visitor', fields);
                closeModal();
                showSuccess(
                    'Tersimpan secara luring.',
                    'Data Pengunjung disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: '/storevisitor',
                type: 'POST',
                data: fields,
                success: () => (
                    closeModal(),
                    visitorTable.ajax.reload(null, false),
                    showSuccess(
                        'Data Pengunjung berhasil disimpan.',
                        'Data Pengunjung telah berhasil disimpan ke basis data.'
                    )
                ),
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Data Pengunjung gagal disimpan.',
                        'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        }
    });

    /* ----------------------------------------------------------------
     * BAGIAN: Validasi — Hibah Buku
     * Cari: #bookgrantform
     * ---------------------------------------------------------------- */
    $('#bookgrantform').validate({
        errorElement: 'div',
        errorClass: 'validation-tooltip',
        ignore: ':hidden:not(#bookgrantfinalprojectid):not(#bookgrantmembership)',
        rules: {
            bookgrantfinalprojectid: 'required',
            bookgranttitle: 'required',
            bookgrantyear: 'required',
            bookgrantauthor: 'required',
            bookgrantmembership: 'required'
        },
        messages: {
            bookgrantfinalprojectid: 'Tugas akhir harus dipilih!',
            bookgranttitle: 'Judul buku harus diisi!',
            bookgrantyear: 'Tahun buku harus diisi!',
            bookgrantauthor: 'Pengarang harus diisi!',
            bookgrantmembership: 'Status keanggotaan harus dipilih!'
        },
        highlight(element) {
            $(element).addClass('input-error');
        
            if (
                element.name === 'bookgrantfinalprojectid' ||
                element.name === 'bookgrantmembership'
            ) {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .addClass('input-error');
            }
        },
        
        unhighlight(element) {
            $(element).removeClass('input-error');
        
            if (
                element.name === 'bookgrantfinalprojectid' ||
                element.name === 'bookgrantmembership'
            ) {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .removeClass('input-error');
            }
        
            const fieldKey = element.name;
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            $('#' + fieldKey + '-error').remove();
        },
        
        errorPlacement(errorElement, inputElement) {
            const fieldKey = inputElement.attr('name');
            const tooltipId = fieldKey + '-error';
        
            $('#' + tooltipId).remove();
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            errorElement.attr('id', tooltipId);
        
            $('body').append(errorElement);
        
            const referenceElement =
                fieldKey === 'bookgrantfinalprojectid' ||
                fieldKey === 'bookgrantmembership'
                    ? inputElement
                          .closest('.custom-select')
                          .find('.custom-select-trigger')[0]
                    : inputElement[0];
        
            poppers[fieldKey] = Popper.createPopper(
                referenceElement,
                errorElement[0],
                {
                    placement:
                        window.innerWidth <= 768
                            ? 'bottom'
                            : 'top-end',
        
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 8]
                            }
                        },
                        {
                            name: 'flip',
                            options: {
                                fallbackPlacements: [
                                    'bottom',
                                    'bottom-start',
                                    'bottom-end',
                                    'top',
                                    'left',
                                    'right'
                                ]
                            }
                        },
                        {
                            name: 'preventOverflow',
                            options: {
                                boundary: document.body,
                                padding: 8
                            }
                        }
                    ]
                }
            );
        },
        
        success(labelElement) {
            const fieldKey = labelElement
                .attr('id')
                ?.replace('-error', '');
        
            if (!fieldKey) {
                labelElement.remove();
                return;
            }
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            labelElement.remove();
        },
        submitHandler(form) {
            const selected = getSelectedBookGrant();

            const fields = {
                _token: csrfToken,
                bookgrantid: selected.id,
                final_project_id: form.bookgrantfinalprojectid.value,
                book_title: form.bookgranttitle.value,
                book_year: form.bookgrantyear.value,
                author: form.bookgrantauthor.value,
                membership_registered: form.bookgrantmembership.value
            };

            const queueOffline = async () => {
                await OfflineSync.queueSave('bookgrant', fields);
                closeModal();
                showSuccess(
                    'Tersimpan secara luring.',
                    'Data Hibah Buku disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: '/storebookgrant',
                type: 'POST',
                data: fields,
                success: () => (
                    closeModal(),
                    bookGrantTable.ajax.reload(null, false),
                    showSuccess(
                        'Data Hibah Buku berhasil disimpan.',
                        'Data Hibah Buku telah berhasil disimpan ke basis data.'
                    )
                ),
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Data Hibah Buku gagal disimpan.',
                        'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        }
    });

    /* ----------------------------------------------------------------
     * BAGIAN: Validasi — Bebas Pustaka
     * Cari: #libraryclearanceform
     * ---------------------------------------------------------------- */
    $('#libraryclearanceform').validate({
        errorElement: 'div',
        errorClass: 'validation-tooltip',
        ignore: ':hidden:not(#libraryclearancefinalprojectid):not(#libraryclearancemembership)',
        rules: {
            libraryclearancefinalprojectid: 'required',
            libraryclearancemembership: 'required',
            libraryclearanceemail: { required: true, email: true },
            libraryclearancephone: 'required',
            libraryclearancegraduationyear: 'required',
            libraryclearancesemester: { required: true, digits: true }
        },
        messages: {
            libraryclearancefinalprojectid: 'Tugas akhir harus dipilih!',
            libraryclearancemembership: 'Status keanggotaan harus dipilih!',
            libraryclearanceemail: {
                required: 'Email harus diisi!',
                email: 'Format email tidak valid!'
            },
            libraryclearancephone: 'Nomor telepon harus diisi!',
            libraryclearancegraduationyear: 'Tahun lulus harus diisi!',
            libraryclearancesemester: {
                required: 'Semester lulus harus diisi!',
                digits: 'Semester lulus harus berupa angka!'
            }
        },
        highlight(element) {
            $(element).addClass('input-error');
        
            if (
                ['libraryclearancefinalprojectid', 'libraryclearancemembership']
                    .includes(element.name)
            ) {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .addClass('input-error');
            }
        },
        
        unhighlight(element) {
            $(element).removeClass('input-error');
        
            if (
                ['libraryclearancefinalprojectid', 'libraryclearancemembership']
                    .includes(element.name)
            ) {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .removeClass('input-error');
            }
        
            const fieldKey = element.name;
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            $('#' + fieldKey + '-error').remove();
        },
        
        errorPlacement(errorElement, inputElement) {
            const fieldKey = inputElement.attr('name');
            const tooltipId = fieldKey + '-error';
        
            $('#' + tooltipId).remove();
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            errorElement.attr('id', tooltipId);
        
            $('body').append(errorElement);
        
            const referenceElement =
                ['libraryclearancefinalprojectid', 'libraryclearancemembership']
                    .includes(fieldKey)
                    ? inputElement
                        .closest('.custom-select')
                        .find('.custom-select-trigger')[0]
                    : inputElement[0];
        
            poppers[fieldKey] = Popper.createPopper(
                referenceElement,
                errorElement[0],
                {
                    placement:
                        window.innerWidth <= 768
                            ? 'bottom'
                            : 'top-end',
        
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 8]
                            }
                        },
                        {
                            name: 'flip',
                            options: {
                                fallbackPlacements: [
                                    'bottom',
                                    'bottom-start',
                                    'bottom-end',
                                    'top',
                                    'left',
                                    'right'
                                ]
                            }
                        },
                        {
                            name: 'preventOverflow',
                            options: {
                                boundary: document.body,
                                padding: 8
                            }
                        }
                    ]
                }
            );
        },
        
        success(labelElement) {
            const fieldKey = labelElement
                .attr('id')
                ?.replace('-error', '');
        
            if (!fieldKey) {
                labelElement.remove();
                return;
            }
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            labelElement.remove();
        },
        submitHandler(form) {
            const selected = getSelectedLibraryClearance();

            const fields = {
                _token: csrfToken,
                libraryclearanceid: selected.id,
                final_project_id: form.libraryclearancefinalprojectid.value,
                membership_registered: form.libraryclearancemembership.value,
                email: form.libraryclearanceemail.value,
                phone_number: form.libraryclearancephone.value,
                graduation_year: form.libraryclearancegraduationyear.value,
                semester_graduated: form.libraryclearancesemester.value
            };

            const queueOffline = async () => {
                await OfflineSync.queueSave('libraryclearance', fields);
                closeModal();
                showSuccess(
                    'Tersimpan secara luring.',
                    'Data Bebas Pustaka disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: '/storelibraryclearance',
                type: 'POST',
                data: fields,
                success: () => (
                    closeModal(),
                    libraryClearanceTable.ajax.reload(null, false),
                    showSuccess(
                        'Data Bebas Pustaka berhasil disimpan.',
                        'Data Bebas Pustaka telah berhasil disimpan ke basis data.'
                    )
                ),
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Data Bebas Pustaka gagal disimpan.',
                        'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        }
    });

    /* ----------------------------------------------------------------
     * BAGIAN: Validasi — Kode Batang
     * Cari: #barcodeform
     * ---------------------------------------------------------------- */
    $('#barcodeform').validate({
        errorElement: 'div',
        errorClass: 'validation-tooltip',
        ignore: ':hidden:not(#barcodefinalprojectid)',
        rules: {
            barcodefinalprojectid: 'required',
            barcodelabel: 'required',
            barcodecode: 'required'
        },
        messages: {
            barcodefinalprojectid: 'Tugas akhir harus dipilih!',
            barcodelabel: 'Label kode batang wajib diisi.',
            barcodecode: 'Kode batang wajib diisi.'
        },
        highlight(element) {
            $(element).addClass('input-error');
        
            if (element.name === 'barcodefinalprojectid') {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .addClass('input-error');
            }
        },
        
        unhighlight(element) {
            $(element).removeClass('input-error');
        
            if (element.name === 'barcodefinalprojectid') {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .removeClass('input-error');
            }
        
            const fieldKey = element.name;
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            $('#' + fieldKey + '-error').remove();
        },
        
        errorPlacement(errorElement, inputElement) {
            const fieldKey = inputElement.attr('name');
            const tooltipId = fieldKey + '-error';
        
            $('#' + tooltipId).remove();
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            errorElement.attr('id', tooltipId);
        
            $('body').append(errorElement);
        
            const referenceElement =
                fieldKey === 'barcodefinalprojectid'
                    ? inputElement
                        .closest('.custom-select')
                        .find('.custom-select-trigger')[0]
                    : inputElement[0];
        
            poppers[fieldKey] = Popper.createPopper(
                referenceElement,
                errorElement[0],
                {
                    placement:
                        window.innerWidth <= 768
                            ? 'bottom'
                            : 'top-end',
        
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 8]
                            }
                        },
                        {
                            name: 'flip',
                            options: {
                                fallbackPlacements: [
                                    'bottom',
                                    'bottom-start',
                                    'bottom-end',
                                    'top',
                                    'left',
                                    'right'
                                ]
                            }
                        },
                        {
                            name: 'preventOverflow',
                            options: {
                                boundary: document.body,
                                padding: 8
                            }
                        }
                    ]
                }
            );
        },
        
        success(labelElement) {
            const fieldKey = labelElement
                .attr('id')
                ?.replace('-error', '');
        
            if (!fieldKey) {
                labelElement.remove();
                return;
            }
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            labelElement.remove();
        },
        submitHandler(form) {
            const selected = getSelectedBarcode();

            const fields = {
                _token: csrfToken,
                barcodeid: selected.id,
                final_project_id: form.barcodefinalprojectid.value,
                label: form.barcodelabel.value,
                barcode: form.barcodecode.value
            };

            const queueOffline = async () => {
                try {
                    await OfflineSync.queueSave('barcode', fields);
                    closeModal();
                    showSuccess(
                        'Tersimpan secara luring.',
                        'Data Kode Batang disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                    );
                } catch (error) {
                    showError(
                        'Tidak dapat menyimpan Kode Batang.',
                        error?.message
                            || 'Kode batang sudah digunakan. Tidak dapat menyimpan data dengan kode yang sama.'
                    );
                }
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: '/storebarcode',
                type: 'POST',
                data: fields,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: () => (
                    closeModal(),
                    barcodeTable.ajax.reload(null, false),
                    showSuccess(
                        'Data Kode Batang berhasil disimpan.',
                        'Data Kode Batang telah berhasil disimpan ke basis data.'
                    )
                ),
                error: xhr => {
                    if (xhr.status === 0) return void queueOffline();

                    const barcodeError = xhr.responseJSON?.errors?.barcode?.[0];
                    const message = barcodeError
                        || xhr.responseJSON?.message
                        || (typeof xhr.responseJSON === 'string' ? xhr.responseJSON : null)
                        || 'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.';

                    showError('Tidak dapat menyimpan Kode Batang.', message);
                }
            });
        }
    });

    if ($('#destructionform').length) {
    /* ----------------------------------------------------------------
     * BAGIAN: Validasi — Pemusnahan
     * Cari: #destructionform
     * ---------------------------------------------------------------- */
    $('#destructionform').validate({
        errorElement: 'div',
        errorClass: 'validation-tooltip',
        ignore: ':hidden:not(#destructionfinalprojectid)',
        rules: {
            destructionfinalprojectid: 'required',
            destructiondescription: 'required'
        },
        messages: {
            destructionfinalprojectid: 'Tugas akhir harus dipilih!',
            destructiondescription: 'Keterangan harus diisi!'
        },
        highlight(element) {
            $(element).addClass('input-error');
        
            if (element.name === 'destructionfinalprojectid') {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .addClass('input-error');
            }
        },
        
        unhighlight(element) {
            $(element).removeClass('input-error');
        
            if (element.name === 'destructionfinalprojectid') {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .removeClass('input-error');
            }
        
            const fieldKey = element.name;
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            $('#' + fieldKey + '-error').remove();
        },
        
        errorPlacement(errorElement, inputElement) {
            const fieldKey = inputElement.attr('name');
            const tooltipId = fieldKey + '-error';
        
            $('#' + tooltipId).remove();
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            errorElement.attr('id', tooltipId);
        
            $('body').append(errorElement);
        
            const referenceElement =
                fieldKey === 'destructionfinalprojectid'
                    ? inputElement
                          .closest('.custom-select')
                          .find('.custom-select-trigger')[0]
                    : inputElement[0];
        
            poppers[fieldKey] = Popper.createPopper(
                referenceElement,
                errorElement[0],
                {
                    placement:
                        window.innerWidth <= 768
                            ? 'bottom'
                            : 'top-end',
        
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 8]
                            }
                        },
                        {
                            name: 'flip',
                            options: {
                                fallbackPlacements: [
                                    'bottom',
                                    'bottom-start',
                                    'bottom-end',
                                    'top',
                                    'left',
                                    'right'
                                ]
                            }
                        },
                        {
                            name: 'preventOverflow',
                            options: {
                                boundary: document.body,
                                padding: 8
                            }
                        }
                    ]
                }
            );
        },
        
        success(labelElement) {
            const fieldKey = labelElement
                .attr('id')
                ?.replace('-error', '');
        
            if (!fieldKey) {
                labelElement.remove();
                return;
            }
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            labelElement.remove();
        },
        submitHandler(form) {
            const selected = getSelectedDestruction();

            const fields = {
                _token: csrfToken,
                destructionid: selected.id,
                final_project_id: form.destructionfinalprojectid.value,
                description: form.destructiondescription.value
            };

            const queueOffline = async () => {
                await OfflineSync.queueSave('destruction', fields);
                closeModal();
                showSuccess(
                    'Tersimpan secara luring.',
                    'Data Pemusnahan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: '/storedestruction',
                type: 'POST',
                data: fields,
                success: () => (
                    closeModal(),
                    destructionTable.ajax.reload(null, false),
                    showSuccess(
                        'Data Pemusnahan berhasil disimpan.',
                        'Data Pemusnahan telah berhasil disimpan ke basis data.'
                    )
                ),
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Data Pemusnahan gagal disimpan.',
                        xhr.responseJSON ?? 'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        }
    });
    }

    /* ----------------------------------------------------------------
     * BAGIAN: Validasi — Arsip
     * Cari: #archiveform
     * ---------------------------------------------------------------- */
    $('#archiveform').validate({
        errorElement: 'div',
        errorClass: 'validation-tooltip',
        ignore: ':hidden:not(#archivefinalprojectid)',
        rules: {
            archivefinalprojectid: 'required',
            archivedescription: 'required'
        },
        messages: {
            archivefinalprojectid: 'Tugas akhir harus dipilih!',
            archivedescription: 'Keterangan harus diisi!'
        },
        highlight(element) {
            $(element).addClass('input-error');
        
            if (element.name === 'archivefinalprojectid') {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .addClass('input-error');
            }
        },
        
        unhighlight(element) {
            $(element).removeClass('input-error');
        
            if (element.name === 'archivefinalprojectid') {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .removeClass('input-error');
            }
        
            const fieldKey = element.name;
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            $('#' + fieldKey + '-error').remove();
        },
        
        errorPlacement(errorElement, inputElement) {
            const fieldKey = inputElement.attr('name');
            const tooltipId = fieldKey + '-error';
        
            $('#' + tooltipId).remove();
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            errorElement.attr('id', tooltipId);
        
            $('body').append(errorElement);
        
            const referenceElement =
                fieldKey === 'archivefinalprojectid'
                    ? inputElement
                          .closest('.custom-select')
                          .find('.custom-select-trigger')[0]
                    : inputElement[0];
        
            poppers[fieldKey] = Popper.createPopper(
                referenceElement,
                errorElement[0],
                {
                    placement:
                        window.innerWidth <= 768
                            ? 'bottom'
                            : 'top-end',
        
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 8]
                            }
                        },
                        {
                            name: 'flip',
                            options: {
                                fallbackPlacements: [
                                    'bottom',
                                    'bottom-start',
                                    'bottom-end',
                                    'top',
                                    'left',
                                    'right'
                                ]
                            }
                        },
                        {
                            name: 'preventOverflow',
                            options: {
                                boundary: document.body,
                                padding: 8
                            }
                        }
                    ]
                }
            );
        },
        
        success(labelElement) {
            const fieldKey = labelElement
                .attr('id')
                ?.replace('-error', '');
        
            if (!fieldKey) {
                labelElement.remove();
                return;
            }
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            labelElement.remove();
        },
        submitHandler(form) {
            const selected = getSelectedArchive();

            const fields = {
                _token: csrfToken,
                archiveid: selected.id,
                final_project_id: form.archivefinalprojectid.value,
                description: form.archivedescription.value
            };

            const queueOffline = async () => {
                await OfflineSync.queueSave('archive', fields);
                closeModal();
                showSuccess(
                    'Tersimpan secara luring.',
                    'Data Arsip disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: '/storearchive',
                type: 'POST',
                data: fields,
                success: () => (
                    closeModal(),
                    archiveTable.ajax.reload(null, false),
                    showSuccess(
                        'Data Arsip berhasil disimpan.',
                        'Data Arsip telah berhasil disimpan ke basis data.'
                    )
                ),
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Data Arsip gagal disimpan.',
                        'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        }
    });

    /* ----------------------------------------------------------------
     * BAGIAN: Validasi — Unduh arsip
     * Cari: #archivedownloadform
     * ---------------------------------------------------------------- */
    $('#archivedownloadform').validate({
        errorElement: 'div',
        errorClass: 'validation-tooltip',
        ignore: ':hidden:not(#archivedownloadstudyprogram)',
        rules: {
            archivedownloadstudyprogram: 'required'
        },
        messages: {
            archivedownloadstudyprogram: 'Program studi harus dipilih!'
        },
        highlight(element) {
            $(element).addClass('input-error');
        
            if (element.name === 'archivedownloadstudyprogram') {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .addClass('input-error');
            }
        },
        
        unhighlight(element) {
            $(element).removeClass('input-error');
        
            if (element.name === 'archivedownloadstudyprogram') {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .removeClass('input-error');
            }
        
            const fieldKey = element.name;
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            $('#' + fieldKey + '-error').remove();
        },
        
        errorPlacement(errorElement, inputElement) {
            const fieldKey = inputElement.attr('name');
            const tooltipId = fieldKey + '-error';
        
            $('#' + tooltipId).remove();
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            errorElement.attr('id', tooltipId);
        
            $('body').append(errorElement);
        
            const referenceElement =
                fieldKey === 'archivedownloadstudyprogram'
                    ? inputElement
                          .closest('.custom-select')
                          .find('.custom-select-trigger')[0]
                    : inputElement[0];
        
            poppers[fieldKey] = Popper.createPopper(
                referenceElement,
                errorElement[0],
                {
                    placement:
                        window.innerWidth <= 768
                            ? 'bottom'
                            : 'top-end',
        
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 8]
                            }
                        },
                        {
                            name: 'flip',
                            options: {
                                fallbackPlacements: [
                                    'bottom',
                                    'bottom-start',
                                    'bottom-end',
                                    'top',
                                    'left',
                                    'right'
                                ]
                            }
                        },
                        {
                            name: 'preventOverflow',
                            options: {
                                boundary: document.body,
                                padding: 8
                            }
                        }
                    ]
                }
            );
        },
        
        success(labelElement) {
            const fieldKey = labelElement
                .attr('id')
                ?.replace('-error', '');
        
            if (!fieldKey) {
                labelElement.remove();
                return;
            }
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            labelElement.remove();
        },
        submitHandler(form) {
            const studyProgram = form.archivedownloadstudyprogram.value;
            const rows = getArchiveTableRows().filter(row => (row[5] ?? '') === studyProgram);

            if (rows.length === 0) {
                return showError('Gagal mengunduh arsip program studi.', 'Tidak ada data arsip untuk program studi yang dipilih!');
            }

            closeModal();
            openArchivePrintTab(rows, studyProgram);
        }
    });

    /* ----------------------------------------------------------------
     * BAGIAN: Validasi — Laporan
     * Cari: #reportform
     * ---------------------------------------------------------------- */
    $('#reportform').validate({
        errorElement: 'div',
        errorClass: 'validation-tooltip',
        ignore: ':hidden:not(#reportfinalprojectid)',
        rules: {
            reportfinalprojectid: 'required',
            reportdescription: 'required'
        },
        messages: {
            reportfinalprojectid: 'Tugas akhir harus dipilih!',
            reportdescription: 'Keterangan harus diisi!'
        },
        highlight(element) {
            $(element).addClass('input-error');
        
            if (element.name === 'reportfinalprojectid') {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .addClass('input-error');
            }
        },
        
        unhighlight(element) {
            $(element).removeClass('input-error');
        
            if (element.name === 'reportfinalprojectid') {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .removeClass('input-error');
            }
        
            const fieldKey = element.name;
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            $('#' + fieldKey + '-error').remove();
        },
        
        errorPlacement(errorElement, inputElement) {
            const fieldKey = inputElement.attr('name');
            const tooltipId = fieldKey + '-error';
        
            $('#' + tooltipId).remove();
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            errorElement.attr('id', tooltipId);
        
            $('body').append(errorElement);
        
            const referenceElement =
                fieldKey === 'reportfinalprojectid'
                    ? inputElement
                          .closest('.custom-select')
                          .find('.custom-select-trigger')[0]
                    : inputElement[0];
        
            poppers[fieldKey] = Popper.createPopper(
                referenceElement,
                errorElement[0],
                {
                    placement:
                        window.innerWidth <= 768
                            ? 'bottom'
                            : 'top-end',
        
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 8]
                            }
                        },
                        {
                            name: 'flip',
                            options: {
                                fallbackPlacements: [
                                    'bottom',
                                    'bottom-start',
                                    'bottom-end',
                                    'top',
                                    'left',
                                    'right'
                                ]
                            }
                        },
                        {
                            name: 'preventOverflow',
                            options: {
                                boundary: document.body,
                                padding: 8
                            }
                        }
                    ]
                }
            );
        },
        
        success(labelElement) {
            const fieldKey = labelElement
                .attr('id')
                ?.replace('-error', '');
        
            if (!fieldKey) {
                labelElement.remove();
                return;
            }
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            labelElement.remove();
        },
        submitHandler(form) {
            const selected = getSelectedReport();

            const fields = {
                _token: csrfToken,
                reportid: selected.id,
                final_project_id: form.reportfinalprojectid.value,
                user_id: authUserId,
                description: form.reportdescription.value
            };

            const queueOffline = async () => {
                await OfflineSync.queueSave('report', fields);
                closeModal();
                showSuccess(
                    'Tersimpan secara luring.',
                    'Data Laporan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: '/storereport',
                type: 'POST',
                data: fields,
                success: () => (
                    closeModal(),
                    reportTable.ajax.reload(null, false),
                    showSuccess(
                        'Data Laporan berhasil disimpan.',
                        'Data Laporan telah berhasil disimpan ke basis data.'
                    )
                ),
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Data Laporan gagal disimpan.',
                        'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        }
    });

    /* ----------------------------------------------------------------
     * BAGIAN: Validasi — Unduh laporan
     * Cari: #reportdownloadform
     * ---------------------------------------------------------------- */
    $('#reportdownloadform').validate({
        errorElement: 'div',
        errorClass: 'validation-tooltip',
        ignore: ':hidden:not(#reportdownloadfinalprojectyear)',
        rules: {
            reportdownloadfinalprojectyear: 'required'
        },
        messages: {
            reportdownloadfinalprojectyear: 'Tahun tugas akhir harus dipilih!'
        },
        highlight(element) {
            $(element).addClass('input-error');
        
            if (element.name === 'reportdownloadfinalprojectyear') {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .addClass('input-error');
            }
        },
        
        unhighlight(element) {
            $(element).removeClass('input-error');
        
            if (element.name === 'reportdownloadfinalprojectyear') {
                $(element)
                    .closest('.custom-select')
                    .find('.custom-select-trigger')
                    .removeClass('input-error');
            }
        
            const fieldKey = element.name;
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            $('#' + fieldKey + '-error').remove();
        },
        
        errorPlacement(errorElement, inputElement) {
            const fieldKey = inputElement.attr('name');
            const tooltipId = fieldKey + '-error';
        
            $('#' + tooltipId).remove();
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            errorElement.attr('id', tooltipId);
        
            $('body').append(errorElement);
        
            const referenceElement =
                fieldKey === 'reportdownloadfinalprojectyear'
                    ? inputElement
                          .closest('.custom-select')
                          .find('.custom-select-trigger')[0]
                    : inputElement[0];
        
            poppers[fieldKey] = Popper.createPopper(
                referenceElement,
                errorElement[0],
                {
                    placement:
                        window.innerWidth <= 768
                            ? 'bottom'
                            : 'top-end',
        
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 8]
                            }
                        },
                        {
                            name: 'flip',
                            options: {
                                fallbackPlacements: [
                                    'bottom',
                                    'bottom-start',
                                    'bottom-end',
                                    'top',
                                    'left',
                                    'right'
                                ]
                            }
                        },
                        {
                            name: 'preventOverflow',
                            options: {
                                boundary: document.body,
                                padding: 8
                            }
                        }
                    ]
                }
            );
        },
        
        success(labelElement) {
            const fieldKey = labelElement
                .attr('id')
                ?.replace('-error', '');
        
            if (!fieldKey) {
                labelElement.remove();
                return;
            }
        
            poppers[fieldKey]?.destroy();
            delete poppers[fieldKey];
        
            labelElement.remove();
        },
        submitHandler(form) {
            const finalProjectYear = form.reportdownloadfinalprojectyear.value;
            const rows = getReportTableRows().filter(row => String(row[7] ?? '') === String(finalProjectYear));

            if (rows.length === 0) {
                return showError('Gagal unduh laporan tahun TA.', 'Tidak ada data laporan untuk tahun tugas akhir yang dipilih!');
            }

            closeModal();
            openReportPrintTab(rows, finalProjectYear);
        }
    });

    /* ----------------------------------------------------------------
     * BAGIAN: Tambah data
     * Membuka modal kosong dan mengosongkan isian.
     * ---------------------------------------------------------------- */
    // CRUD: Trigger Add Modal
    $('body').on('click', '#buttonaddfinalproject', event => {
        event.preventDefault();
        finalProjectModal.classList.add('show');
        document.body.style.overflow = 'hidden';
        const $form = $('#finalprojectform');
        $form[0].reset();
        $form.find('.custom-option').removeClass('selected');
        $form.find('.custom-select .custom-select-trigger span').text('Pilih Program Studi');
        $form.find('.file-name').text('Belum ada file dipilih');
        $form.find('[name=studyprogramme]').val('');
        $('#buttonfinalproject').attr('data-id', JSON.stringify({ id: null }));
    });

    $('body').on('click', '#buttonaddstudyprogram', event => {
        event.preventDefault();
        studyProgramModal.classList.add('show');
        document.body.style.overflow = 'hidden';
        $('#studyprogramform')[0].reset();
        $('#buttonstudyprogram').attr('data-id', JSON.stringify({ id: null }));
    });

    $('body').on('click', '#buttonaddmembership', event => {
        event.preventDefault();
        membershipModal.classList.add('show');
        document.body.style.overflow = 'hidden';
        $('#membershipform')[0].reset();
        $('#membershipform .custom-option').removeClass('selected');
        $('#membershipform #membershipuserid').closest('.custom-select').find('.custom-select-trigger span').text('Pilih Mahasiswa');
        $('#membershipform #membershipstudyprogramme').closest('.custom-select').find('.custom-select-trigger span').text('Pilih Program Studi');
        $('#membershipform #membershipgender').closest('.custom-select').find('.custom-select-trigger span').text('Pilih Jenis Kelamin');
        $('#membershipuserid, #membershipstudyprogramme, #membershipgender').val('');
        membershipBirthDatepicker?.clear({ silent: true });
        $('#buttonmembership').attr('data-id', JSON.stringify({ id: null }));
    });

    $('body').on('click', '#buttonaddfinalprojecttype', event => {
        event.preventDefault();
        finalProjectTypeModal.classList.add('show');
        document.body.style.overflow = 'hidden';
        $('#finalprojecttypeform')[0].reset();
        $('#buttonfinalprojecttype').attr('data-id', JSON.stringify({ id: null }));
    });

    $('body').on('click', '#buttonaddvisitor', event => {
        event.preventDefault();
        visitorModal.classList.add('show');
        document.body.style.overflow = 'hidden';
        $('#visitorform')[0].reset();
        $('#visitorform .custom-option').removeClass('selected');
        $('#visitorform #visitorprogramstudy').closest('.custom-select').find('.custom-select-trigger span').text('Pilih Program Studi');
        $('#visitorprogramstudy').val('');
        $('#buttonvisitor').attr('data-id', JSON.stringify({ id: null }));
    });

    /* Toggle akses formulir kunjungan publik */
    (() => {
        const accessSwitch = document.getElementById('visitoraccessswitch');
        const accessLabel = document.getElementById('visitoraccesslabel');
        if (!accessSwitch || !canMutateData) return;

        const setAccessUi = open => {
            accessSwitch.checked = !!open;
            if (accessLabel) {
                accessLabel.textContent = open ? 'Akses Form: Buka' : 'Akses Form: Tutup';
            }
        };

        fetch('/visitor/access', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(response => response.json())
            .then(data => setAccessUi(data.open))
            .catch(() => { /* biarkan status UI sebelumnya */ });

        accessSwitch.addEventListener('change', () => {
            const open = accessSwitch.checked;
            setAccessUi(open);

            const queueOffline = async () => {
                await OfflineSync.queueAction({
                    key: 'action:visitor-access',
                    url: '/visitor/access',
                    module: 'visitor',
                    fields: {
                        _token: csrfToken,
                        open: open ? 1 : 0
                    }
                });
                showSuccess(
                    'Tersimpan secara luring.',
                    'Perubahan akses form akan disinkronkan saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: '/visitor/access',
                type: 'POST',
                data: {
                    _token: csrfToken,
                    open: open ? 1 : 0,
                },
                success: response => {
                    setAccessUi(response.open);
                    showSuccess(
                        response.open ? 'Akses dibuka.' : 'Akses ditutup.',
                        response.message || ''
                    );
                },
                error: xhr => {
                    if (xhr.status === 0) return void queueOffline();
                    setAccessUi(!open);
                    showError(
                        'Gagal mengubah akses form.',
                        xhr.responseJSON?.message || 'Terjadi kesalahan pada peladen.'
                    );
                },
            });
        });
    })();

    /* Toggle akses pemindaian kode batang publik */
    (() => {
        const accessSwitch = document.getElementById('barcodeaccessswitch');
        const accessLabel = document.getElementById('barcodeaccesslabel');
        if (!accessSwitch || !canMutateData) return;

        const setAccessUi = open => {
            accessSwitch.checked = !!open;
            if (accessLabel) {
                accessLabel.textContent = open ? 'Akses Pindai: Buka' : 'Akses Pindai: Tutup';
            }
        };

        fetch('/barcode/access', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(response => response.json())
            .then(data => setAccessUi(data.open))
            .catch(() => { /* biarkan status UI sebelumnya */ });

        accessSwitch.addEventListener('change', () => {
            const open = accessSwitch.checked;
            setAccessUi(open);

            const queueOffline = async () => {
                await OfflineSync.queueAction({
                    key: 'action:barcode-access',
                    url: '/barcode/access',
                    module: 'barcode',
                    fields: {
                        _token: csrfToken,
                        open: open ? 1 : 0
                    }
                });
                showSuccess(
                    'Tersimpan secara luring.',
                    'Perubahan akses pindai akan disinkronkan saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: '/barcode/access',
                type: 'POST',
                data: {
                    _token: csrfToken,
                    open: open ? 1 : 0,
                },
                success: response => {
                    setAccessUi(response.open);
                    showSuccess(
                        response.open ? 'Akses dibuka.' : 'Akses ditutup.',
                        response.message || ''
                    );
                },
                error: xhr => {
                    if (xhr.status === 0) return void queueOffline();
                    setAccessUi(!open);
                    showError(
                        'Gagal mengubah akses pindai.',
                        xhr.responseJSON?.message || 'Terjadi kesalahan pada peladen.'
                    );
                },
            });
        });
    })();

    /* Toggle akses formulir penyerahan publik */
    (() => {
        const accessSwitch = document.getElementById('submissionaccessswitch');
        const accessLabel = document.getElementById('submissionaccesslabel');
        if (!accessSwitch || !canMutateData) return;

        const setAccessUi = open => {
            accessSwitch.checked = !!open;
            if (accessLabel) {
                accessLabel.textContent = open ? 'Akses Form: Buka' : 'Akses Form: Tutup';
            }
        };

        fetch('/submission/access', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(response => response.json())
            .then(data => setAccessUi(data.open))
            .catch(() => { /* biarkan status UI sebelumnya */ });

        accessSwitch.addEventListener('change', () => {
            const open = accessSwitch.checked;
            setAccessUi(open);

            const queueOffline = async () => {
                await OfflineSync.queueAction({
                    key: 'action:submission-access',
                    url: '/submission/access',
                    module: 'submission',
                    fields: {
                        _token: csrfToken,
                        open: open ? 1 : 0
                    }
                });
                showSuccess(
                    'Tersimpan secara luring.',
                    'Perubahan akses form penyerahan akan disinkronkan saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: '/submission/access',
                type: 'POST',
                data: {
                    _token: csrfToken,
                    open: open ? 1 : 0,
                },
                success: response => {
                    setAccessUi(response.open);
                    showSuccess(
                        response.open ? 'Akses dibuka.' : 'Akses ditutup.',
                        response.message || ''
                    );
                },
                error: xhr => {
                    if (xhr.status === 0) return void queueOffline();
                    setAccessUi(!open);
                    showError(
                        'Gagal mengubah akses form penyerahan.',
                        xhr.responseJSON?.message || 'Terjadi kesalahan pada peladen.'
                    );
                },
            });
        });
    })();

    $('body').on('click', '#buttondownloadvisitor', event => {
        event.preventDefault();

        const rows = getVisitorTableRows();

        if (rows.length === 0) {
            return showError('Gagal unduh pengunjung.', 'Tidak ada data pengunjung!');
        }

        openVisitorPrintTab(rows);
    });

    $('body').on('click', '#buttonaddbookgrant', event => {
        event.preventDefault();
        bookGrantModal.classList.add('show');
        document.body.style.overflow = 'hidden';
        $('#bookgrantform')[0].reset();
        $('#bookgrantform .custom-option').removeClass('selected');
        $('#bookgrantform #bookgrantfinalprojectid').closest('.custom-select').find('.custom-select-trigger span').text('Pilih Tugas Akhir');
        $('#bookgrantform #bookgrantmembership').closest('.custom-select').find('.custom-select-trigger span').text('Pilih Status Keanggotaan');
        $('#bookgrantfinalprojectid, #bookgrantmembership').val('');
        bookGrantYearDatepicker?.clear();
        $('#buttonbookgrant').attr('data-id', JSON.stringify({ id: null }));
    });

    $('body').on('click', '#buttonaddlibraryclearance', event => {
        event.preventDefault();
        libraryClearanceModal.classList.add('show');
        document.body.style.overflow = 'hidden';
        $('#libraryclearanceform')[0].reset();
        $('#libraryclearanceform .custom-option').removeClass('selected');
        $('#libraryclearanceform #libraryclearancefinalprojectid').closest('.custom-select').find('.custom-select-trigger span').text('Pilih Tugas Akhir');
        $('#libraryclearanceform #libraryclearancemembership').closest('.custom-select').find('.custom-select-trigger span').text('Pilih Status Keanggotaan');
        $('#libraryclearancefinalprojectid, #libraryclearancemembership').val('');
        libraryClearanceGraduationYearDatepicker?.clear();
        $('#buttonlibraryclearance').attr('data-id', JSON.stringify({ id: null }));
    });

    $('body').on('click', '#buttonaddbarcode', event => {
        event.preventDefault();
        barcodeModal.classList.add('show');
        document.body.style.overflow = 'hidden';
        $('#barcodeform')[0].reset();
        $('#barcodeform .custom-option').removeClass('selected');
        $('#barcodeform #barcodefinalprojectid').closest('.custom-select').find('.custom-select-trigger span').text('Pilih Tugas Akhir');
        $('#barcodefinalprojectid').val('');
        $('#buttonbarcode').attr('data-id', JSON.stringify({ id: null }));
    });

    $('body').on('click', '#buttondownloadbarcode', event => {
        event.preventDefault();

        if (!OfflineSync.requireOnline(
            'Gagal mengunduh kode batang.',
            'Mengunduh PDF kode batang membutuhkan koneksi daring.'
        )) return;

        if (typeof barcodeTable === 'undefined' || barcodeTable.rows().count() === 0) {
            return showError('Gagal mengunduh kode batang.', 'Tidak ada data kode batang.');
        }

        window.open('/barcode/pdf', '_blank');
    });

    $('body').on('click', '#buttonadddestruction', event => {
        event.preventDefault();
        if (!destructionModal) return;
        destructionModal.classList.add('show');
        document.body.style.overflow = 'hidden';
        $('#destructionform')[0].reset();
        $('#destructionform .custom-option').removeClass('selected');
        $('#destructionform #destructionfinalprojectid').closest('.custom-select').find('.custom-select-trigger span').text('Pilih Tugas Akhir (Tidak Berlaku)');
        $('#destructionfinalprojectid').val('');
        $('#buttondestruction').attr('data-id', JSON.stringify({ id: null }));
    });

    $('body').on('click', '#buttonaddarchive', event => {
        event.preventDefault();
        archiveModal.classList.add('show');
        document.body.style.overflow = 'hidden';
        $('#archiveform')[0].reset();
        $('#archiveform .custom-option').removeClass('selected');
        $('#archiveform #archivefinalprojectid').closest('.custom-select').find('.custom-select-trigger span').text('Pilih Tugas Akhir');
        $('#archivefinalprojectid').val('');
        $('#buttonarchive').attr('data-id', JSON.stringify({ id: null }));
    });

    $('body').on('click', '#buttondownloadarchive', event => {
        event.preventDefault();

        const rows = getArchiveTableRows();

        if (rows.length === 0) {
            return showError('Gagal unduh arsip.', 'Tidak ada data arsip!');
        }

        openArchivePrintTab(rows);
    });

    $('body').on('click', '#buttondownloadarchivestudyprogram', event => {
        event.preventDefault();

        const rows = getArchiveTableRows();

        if (rows.length === 0) {
            return showError('Gagal mengunduh arsip program studi.', 'Tidak ada data arsip!');
        }

        const programs = [...new Set(rows.map(row => row[5]).filter(Boolean))].sort((a, b) =>
            String(a).localeCompare(String(b), 'id')
        );

        if (programs.length === 0) {
            return showError('Gagal mengunduh arsip program studi.', 'Tidak ada program studi pada data arsip!');
        }

        const dropdown = $('#archivedownloadstudydroptions');
        dropdown.empty();
        programs.forEach(program => {
            const option = document.createElement('div');
            option.className = 'custom-option';
            option.dataset.id = program;
            option.dataset.value = program;
            option.textContent = program;
            dropdown.append(option);
        });

        archiveDownloadModal.classList.add('show');
        document.body.style.overflow = 'hidden';
        $('#archivedownloadform')[0].reset();
        $('#archivedownloadform .custom-option').removeClass('selected');
        $('#archivedownloadform .custom-select-trigger span').text('Pilih Program Studi');
        $('#archivedownloadstudyprogram').val('');
        $('#archivedownloadform').validate().resetForm();
        $('#archivedownloadform').find('.error, .valid, .input-error').removeClass('error valid input-error');
    });

    $('body').on('click', '#buttonaddreport', event => {
        event.preventDefault();
        reportModal.classList.add('show');
        document.body.style.overflow = 'hidden';
        $('#reportform')[0].reset();
        $('#reportform .custom-option').removeClass('selected');
        $('#reportform #reportfinalprojectid').closest('.custom-select').find('.custom-select-trigger span').text('Pilih Tugas Akhir');
        $('#reportfinalprojectid').val('');
        $('#buttonreport').attr('data-id', JSON.stringify({ id: null }));
    });

    $('body').on('click', '#buttondownloadreport', event => {
        event.preventDefault();

        const rows = getReportTableRows();

        if (rows.length === 0) {
            return showError('Gagal unduh laporan.', 'Tidak ada data laporan!');
        }

        openReportPrintTab(rows);
    });

    $('body').on('click', '#buttondownloadreportfinalprojectyear', event => {
        event.preventDefault();

        const rows = getReportTableRows();

        if (rows.length === 0) {
            return showError('Gagal unduh laporan tahun TA.', 'Tidak ada data laporan!');
        }

        const years = [...new Set(
            rows.map(row => row[7]).filter(year => year !== null && year !== undefined && year !== '' && year !== '-')
        )].sort((a, b) => Number(b) - Number(a));

        if (years.length === 0) {
            return showError('Gagal unduh laporan tahun TA.', 'Tidak ada tahun tugas akhir pada data laporan!');
        }

        const dropdown = $('#reportdownloadyearoptions');
        dropdown.empty();
        years.forEach(year => {
            const option = document.createElement('div');
            option.className = 'custom-option';
            option.dataset.id = String(year);
            option.dataset.value = String(year);
            option.textContent = String(year);
            dropdown.append(option);
        });

        reportDownloadModal.classList.add('show');
        document.body.style.overflow = 'hidden';
        $('#reportdownloadform')[0].reset();
        $('#reportdownloadform .custom-option').removeClass('selected');
        $('#reportdownloadform .custom-select-trigger span').text('Pilih Tahun Tugas Akhir');
        $('#reportdownloadfinalprojectyear').val('');
        $('#reportdownloadform').validate().resetForm();
        $('#reportdownloadform').find('.error, .valid, .input-error').removeClass('error valid input-error');
    });

    $('body').on('click', '#buttonaddsubmission', event => {
        event.preventDefault();
        submissionModal.classList.add('show');
        document.body.style.overflow = 'hidden';
        $('#submissionform')[0].reset();
        $('#submissionform .custom-option').removeClass('selected');
        $('#submissionform #submissionstudyprogramme').closest('.custom-select').find('.custom-select-trigger span').text('Pilih Program Studi');
        $('#submissionform #submissiondegreelevel').closest('.custom-select').find('.custom-select-trigger span').text('Pilih Jenjang');
        $('#submissionform .file-name').text('Belum ada file dipilih');
        $('#submissionstudyprogramme, #submissiondegreelevel').val('');
        submissionYearDatepicker?.clear();
        submissionSubmissionDatepicker?.clear();
        $('#buttonsubmission').attr('data-id', JSON.stringify({ id: null }));
    });

    $('body').on('click', '#buttonadduser', event => {
        event.preventDefault();
        userModal.classList.add('show');
        document.body.style.overflow = 'hidden';
        $('#userform')[0].reset();
        $('#userform .custom-option').removeClass('selected');
        $('#userform #userrole').closest('.custom-select').find('.custom-select-trigger span').text('Pilih Peran');
        $('#userrole').val('');
        $('#userpasswordlabel').addClass('required-label').text('Kata Sandi');
        $('#userpassword').attr('placeholder', 'Kata sandi');
        $('#buttonuser').attr('data-id', JSON.stringify({ id: null }));
    });

    /* ----------------------------------------------------------------
     * BAGIAN: Impor
     * Modal impor. Pembaca AI wajib online.
     * ---------------------------------------------------------------- */
    // IMPORT: Trigger Import Modal (AI reader wajib online — bukan antrean PWA)
    $('body').on('click', '#buttonaddimport', function (event) {
        event.preventDefault();

        if (typeof ensureAiImportOnline === 'function' && !ensureAiImportOnline()) {
            return;
        }

        $('#folderform')[0].reset();
        $('#folderfile').val('');
        $('.file-name').text('Belum ada folder dipilih');
        $('#folderform').validate().resetForm();
        $('#folderform').find('.error, .valid').removeClass('error valid');

        folderModal.classList.add('show');
        document.body.style.overflow = 'hidden';
    });

    /* ----------------------------------------------------------------
     * BAGIAN: Unduh berkas
     * Tugas akhir dan lembar pengesahan, wajib online.
     * ---------------------------------------------------------------- */
    // Unduh berkas TA / lembar pengesahan dari server — wajib online
    $('body').on('click', 'a[href*="/download/"]', function (event) {
        if (!OfflineSync.requireOnline(
            'Gagal unduh berkas.',
            'Unduh berkas membutuhkan koneksi daring.'
        )) {
            event.preventDefault();
        }
    });

    $('body').on('click', '.detail-file-download', function(event) {
        event.preventDefault();
        showError('Gagal download berkas.', 'Tidak ada berkas yang dipilih!');
    });

    $('body').on('click', '.detail-barcode-download', function(event) {
        event.preventDefault();
        const code = $(this).data('barcode') || $(this).text();
        if (!downloadCode128Barcode(code)) {
            showError('Gagal mengunduh kode batang.', 'Tidak ada kode batang yang dipilih.');
        }
    });

    $('body').on('click', '.import-file-download', function (event) {
        event.preventDefault();

        const id = $(this).data('row');
        const type = $(this).data('type');

        const file = type === 'finalproject'
            ? importFileStore.get(id)
            : approvalSheetStore.get(id);

        if (!file) return;

        const url = URL.createObjectURL(file);

        window.open(url, '_blank');

        setTimeout(() => URL.revokeObjectURL(url), 5000);
    });

    $('body').on('click', '#buttonsaveimport', async function (event) {
        event.preventDefault();

        if (importTable.rows().count() === 0) {
            return showError(
                'Gagal menyimpan data Impor.',
                'Tidak ada data yang diimpor!'
            );
        }

        const rows = importTable.rows().data().toArray();
        const total = rows.length;

        const finishOffline = async () => {
            setProgress(
                'Menyimpan Impor.',
                'Data impor disimpan secara luring di perangkat.',
                100,
                'Antre luring'
            );

            await OfflineSync.queueImportBatch(csrfToken, rows, rowId => ({
                final_project_file: importFileStore.get(rowId) ?? null,
                approval_sheet: approvalSheetStore.get(rowId) ?? null
            }));

            importTable.clear().draw();
            importFileStore.clear();
            approvalSheetStore.clear();
            $('#folderfile').val('');

            showSuccess(
                'Tersimpan secara luring.',
                `${total} data impor disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.`
            );
        };

        if (OfflineSync.isOffline()) return void finishOffline();

        const formData = new FormData();
        formData.append('_token', csrfToken);

        // Tampilkan progress awal
        setProgress(
            'Menyimpan Impor.',
            'Data impor sedang dipersiapkan untuk dikirim ke basis data.',
            0,
            'Menyiapkan data...'
        );

        // Menyiapkan FormData
        for (let i = 0; i < total; i++) {

            const row = rows[i];

            const percent = Math.round((i / total) * 50);

            setProgress(
                'Menyimpan Impor.',
                'Data impor sedang dipersiapkan untuk dikirim ke basis data.',
                percent,
                `Menyiapkan data ${i + 1} dari ${total}...`
            );

            // Beri kesempatan browser me-render progress
            await new Promise(resolve => requestAnimationFrame(resolve));

            Object.entries({
                degree_level: row[5],
                final_project_type_id: row[20] || '',
                nim: row[1],
                student_name: row[2],
                final_project_title: row[3],
                program_study: row[4],
                final_project_year: row[6],
                submission_date: row[7]
            }).forEach(([key, value]) => formData.append(`${key}[]`, value));

            const finalProjectFile = importFileStore.get(row[0]);
            const approvalSheet = approvalSheetStore.get(row[0]);

            // Indeks eksplisit agar selaras dengan baris di Processor
            if (finalProjectFile instanceof File) {
                formData.append(`final_project_file[${i}]`, finalProjectFile);
            }

            if (approvalSheet instanceof File) {
                formData.append(`approval_sheet[${i}]`, approvalSheet);
            }
        }

        // Persiapan selesai
        setProgress(
            'Menyimpan Impor.',
            'Mengirim data ke basis data...',
            50,
            'Mengunggah data...'
        );

        $.ajax({
            url: '/storeimport',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,

            xhr() {

                const xhr = $.ajaxSettings.xhr();

                if (xhr.upload) {

                    xhr.upload.onprogress = function (event) {

                        if (event.lengthComputable) {

                            // Upload menggunakan 50% - 100%
                            const uploadPercent = 50 + Math.round((event.loaded / event.total) * 50);

                            setProgress(
                                'Menyimpan Impor.',
                                'Mengirim data ke basis data...',
                                uploadPercent,
                                `Mengunggah data ${uploadPercent}%...`
                            );
                        }
                    };
                }

                return xhr;
            },

            success() {

                setProgress(
                    'Menyimpan Impor.',
                    'Data impor berhasil disimpan ke basis data.',
                    100,
                    'Selesai'
                );

                // Kosongkan datatable & berkas sementara setelah berhasil disimpan
                importTable.clear().draw();
                importFileStore.clear();
                approvalSheetStore.clear();
                $('#folderfile').val('');

                showSuccess(
                    'Data Impor berhasil disimpan.',
                    'Data Impor telah berhasil disimpan ke basis data.'
                );
            },

            error(xhr) {
                if (xhr.status === 0) return void finishOffline();
                showError(
                    'Gagal menyimpan data Impor.',
                    (typeof firstValidationMessage === 'function' ? firstValidationMessage(xhr.responseJSON) : null)
                        || xhr.responseJSON?.message
                        || 'Terjadi kesalahan pada peladen.'
                );
            }
        });
    });

    $('body').on('click', '#buttoninfoimport', function (event) {
        event.preventDefault();

        importInformationModal.classList.add('show');
        document.body.style.overflow = 'hidden';

        const info = JSON.parse($(this).attr('data-id'));

        $('#importmodel').text(info.model ?? '');
        $('#importtemperature').text(info.temperature ?? '');
        $('#importprocessingtimems').text(info.processing_time_ms ?? '');
        $('#importprompttokens').text(info.prompt_tokens ?? '');
        $('#importcompletiontokens').text(info.completion_tokens ?? '');
        $('#importtotaltokens').text(info.total_tokens ?? '');
        $('#importfinishreason').text(info.finish_reason ?? '');
        $('#importextractedfields').text(info.extracted_fields ?? '');
        $('#importtotalfields').text(info.total_fields ?? '');
        $('#importcompletionpercentage').text(info.completion_percentage ?? '');
    });

    /* ----------------------------------------------------------------
     * BAGIAN: Ubah data
     * Mengisi modal dari baris yang dipilih.
     * ---------------------------------------------------------------- */
    // CRUD: Trigger Edit Modal & Data Binding
    $('body').on('click', '#buttoneditfinalproject', function(event) {
        event.preventDefault();
        finalProjectModal.classList.add('show');
        document.body.style.overflow = 'hidden';

        const data = JSON.parse($(this).attr('data-id')),
              form = $('#finalprojectform')[0];

        form.nim.value = data.nim ?? '';
        form.studentsname.value = data.student_name ?? '';
        form.finalprojecttitle.value = data.final_project_title ?? '';

        applyCustomSelectValue(form, 'studyprogramme', data.program_study, 'Pilih Program Studi');

        [[data.final_project_file, getStoredFileSize(data.final_project_file)], [data.approval_sheet, getStoredFileSize(data.approval_sheet)]]
        .forEach(([filePath, fileSize], i) =>
            $('.upload-box').eq(i).find('.file-name').empty().append(
                checkedFileLabel(getDisplayFileName(filePath), fileSize)
            ));

        (data.final_project_year) ? finalProjectYearDatepicker.selectDate(new Date(parseInt(data.final_project_year), 0, 1)) : finalProjectYearDatepicker.clear();
        (data.submission_date) ? submissionDatepicker.selectDate(new Date(data.submission_date)) : submissionDatepicker.clear();
        $('#buttonfinalproject').attr('data-id', JSON.stringify({ id: data.id }));
    });

    $('body').on('click', '#buttoneditstudyprogram', function (event) {
        event.preventDefault();
        studyProgramModal.classList.add('show');
        document.body.style.overflow = 'hidden';

        const data = JSON.parse($(this).attr('data-id'));
        const form = $('#studyprogramform')[0];

        form.studyprogramname.value = data.study_program ?? '';
        form.studyprogramcluster.value = data.cluster ?? '';
        $('#buttonstudyprogram').attr('data-id', JSON.stringify({ id: data.id }));
    });

    $('body').on('click', '#buttondownloadmembershipletter', function (event) {
        event.preventDefault();
        event.stopPropagation();

        if (!OfflineSync.requireOnline(
            'Gagal unduh surat.',
            'Unduh surat keanggotaan membutuhkan koneksi daring.'
        )) return;

        const id = $(this).attr('data-id');

        if (!id) {
            return showError('Gagal unduh surat.', 'Data keanggotaan tidak ditemukan!');
        }

        window.open(`/membership/surat/${id}`, '_blank');
    });

    $('body').on('click', '#buttoneditmembership', function (event) {
        event.preventDefault();
        membershipModal.classList.add('show');
        document.body.style.overflow = 'hidden';

        const data = JSON.parse($(this).attr('data-id'));
        const form = $('#membershipform')[0];
        const text = value => {
            const cleaned = String(value ?? '').trim();
            return cleaned === '-' ? '' : cleaned;
        };

        form.membershipbirthplace.value = text(data.place_of_birth);
        form.membershiporigin.value = text(data.origin_address);
        form.membershipcurrent.value = text(data.current_address);

        applyCustomSelectValue(form, 'membershipuserid', data.user_id, 'Pilih Mahasiswa');
        applyCustomSelectValue(form, 'membershipstudyprogramme', data.study_program_id, 'Pilih Program Studi');
        applyCustomSelectValue(form, 'membershipgender', text(data.gender), 'Pilih Jenis Kelamin');
        selectDatepickerDate(membershipBirthDatepicker, text(data.date_of_birth));

        $('#buttonmembership').attr('data-id', JSON.stringify({ id: data.id }));
    });

    $('body').on('click', '#buttoneditfinalprojecttype', function (event) {
        event.preventDefault();
        finalProjectTypeModal.classList.add('show');
        document.body.style.overflow = 'hidden';

        const data = JSON.parse($(this).attr('data-id'));
        const form = $('#finalprojecttypeform')[0];

        form.finalprojecttypelevel.value = data.level ?? '';
        form.finalprojecttypename.value = data.type ?? '';
        $('#buttonfinalprojecttype').attr('data-id', JSON.stringify({ id: data.id }));
    });

    $('body').on('click', '#buttoneditvisitor', function (event) {
        event.preventDefault();
        visitorModal.classList.add('show');
        document.body.style.overflow = 'hidden';

        const data = JSON.parse($(this).attr('data-id'));
        const form = $('#visitorform')[0];

        form.visitornim.value = data.nim ?? '';
        form.visitorstudentname.value = data.student_name ?? '';
        form.visitorneeds.value = data.needs ?? '';

        const hiddenInput = $(form).find('#visitorprogramstudy');
        const select = hiddenInput.closest('.custom-select');

        select.find('.custom-option').removeClass('selected');

        const option = select.find(`.custom-option[data-value="${data.program_study}"]`);
        if (option.length) option.addClass('selected');

        hiddenInput.val(data.program_study ?? '');
        select.find('.custom-select-trigger span').text(data.program_study || 'Pilih Program Studi');

        $('#buttonvisitor').attr('data-id', JSON.stringify({ id: data.id }));
    });

    $('body').on('click', '#buttondownloadbookgrantletter', function (event) {
        event.preventDefault();
        event.stopPropagation();

        if (!OfflineSync.requireOnline(
            'Gagal unduh surat.',
            'Unduh surat hibah buku membutuhkan koneksi daring.'
        )) return;

        const id = $(this).attr('data-id');
        const membership = String($(this).attr('data-membership') ?? '').trim();

        if (!id) {
            return showError('Gagal unduh surat.', 'Data hibah buku tidak ditemukan!');
        }

        if (membership !== 'Terdaftar') {
            return showError(
                'Gagal unduh surat.',
                'Keanggotaan belum terdaftar. Surat hanya dapat diunduh untuk tugas akhir yang sudah terdaftar keanggotaan.'
            );
        }

        window.open(`/bookgrant/surat/${id}`, '_blank');
    });

    $('body').on('click', '#buttoneditbookgrant', function (event) {
        event.preventDefault();
        bookGrantModal.classList.add('show');
        document.body.style.overflow = 'hidden';

        const data = JSON.parse($(this).attr('data-id'));
        const form = $('#bookgrantform')[0];

        form.bookgranttitle.value = data.book_title ?? '';
        form.bookgrantauthor.value = data.author ?? '';

        [
            {
                input: 'bookgrantfinalprojectid',
                value: data.final_project_id,
                placeholder: 'Pilih Tugas Akhir'
            },
            {
                input: 'bookgrantmembership',
                value: data.membership_registered,
                placeholder: 'Pilih Status Keanggotaan'
            }
        ].forEach(({ input, value, placeholder }) => {
            applyCustomSelectValue(form, input, value, placeholder);
        });

        if (data.book_year) {
            bookGrantYearDatepicker.selectDate(new Date(parseInt(data.book_year, 10), 0, 1));
        } else {
            bookGrantYearDatepicker.clear();
        }

        $('#buttonbookgrant').attr('data-id', JSON.stringify({ id: data.id }));
    });

    $('body').on('click', '#buttondownloadlibraryclearanceletter', function (event) {
        event.preventDefault();
        event.stopPropagation();

        if (!OfflineSync.requireOnline(
            'Gagal unduh surat.',
            'Unduh surat bebas pustaka membutuhkan koneksi daring.'
        )) return;

        const id = $(this).attr('data-id');
        const membership = String($(this).attr('data-membership') ?? '').trim();

        if (!id) {
            return showError('Gagal unduh surat.', 'Data bebas pustaka tidak ditemukan!');
        }

        if (membership !== 'Terdaftar') {
            return showError(
                'Gagal unduh surat.',
                'Keanggotaan belum terdaftar. Surat hanya dapat diunduh untuk tugas akhir yang sudah terdaftar keanggotaan.'
            );
        }

        window.open(`/libraryclearance/surat/${id}`, '_blank');
    });

    $('body').on('click', '#buttoneditlibraryclearance', function (event) {
        event.preventDefault();
        libraryClearanceModal.classList.add('show');
        document.body.style.overflow = 'hidden';

        const data = JSON.parse($(this).attr('data-id'));
        const form = $('#libraryclearanceform')[0];

        form.libraryclearanceemail.value = data.email ?? '';
        form.libraryclearancephone.value = data.phone_number ?? '';
        form.libraryclearancesemester.value = data.semester_graduated ?? '';

        [
            {
                input: 'libraryclearancefinalprojectid',
                value: data.final_project_id,
                placeholder: 'Pilih Tugas Akhir'
            },
            {
                input: 'libraryclearancemembership',
                value: data.membership_registered,
                placeholder: 'Pilih Status Keanggotaan'
            }
        ].forEach(({ input, value, placeholder }) => {
            applyCustomSelectValue(form, input, value, placeholder);
        });

        if (data.graduation_year) {
            libraryClearanceGraduationYearDatepicker.selectDate(new Date(parseInt(data.graduation_year, 10), 0, 1));
        } else {
            libraryClearanceGraduationYearDatepicker.clear();
        }

        $('#buttonlibraryclearance').attr('data-id', JSON.stringify({ id: data.id }));
    });

    $('body').on('click', '#buttoneditbarcode', function (event) {
        event.preventDefault();
        barcodeModal.classList.add('show');
        document.body.style.overflow = 'hidden';

        const data = JSON.parse($(this).attr('data-id'));
        const form = $('#barcodeform')[0];

        form.barcodelabel.value = data.label ?? '';
        form.barcodecode.value = data.barcode ?? '';

        [
            {
                input: 'barcodefinalprojectid',
                value: data.final_project_id,
                placeholder: 'Pilih Tugas Akhir'
            }
        ].forEach(({ input, value, placeholder }) => {
            applyCustomSelectValue(form, input, value, placeholder);
        });

        $('#buttonbarcode').attr('data-id', JSON.stringify({ id: data.id }));
    });

    $('body').on('click', '#buttoneditdestruction', function (event) {
        event.preventDefault();
        if (!destructionModal || userRole === 'Koordinator') return;

        const data = JSON.parse($(this).attr('data-id'));

        if (data.agreement === 'Disetujui') {
            return showError(
                'Tidak dapat diubah.',
                'Pemusnahan yang sudah disetujui tidak dapat diubah. Batalkan persetujuan melalui Koordinator jika perlu.'
            );
        }

        destructionModal.classList.add('show');
        document.body.style.overflow = 'hidden';

        const form = $('#destructionform')[0];
        form.destructiondescription.value = data.description ?? '';

        applyCustomSelectValue(form, 'destructionfinalprojectid', data.final_project_id, 'Pilih Tugas Akhir (Tidak Berlaku)');

        $('#buttondestruction').attr('data-id', JSON.stringify({ id: data.id }));
    });

    $('body').on('click', '#buttoneditarchive', function (event) {
        event.preventDefault();
        archiveModal.classList.add('show');
        document.body.style.overflow = 'hidden';

        const data = JSON.parse($(this).attr('data-id'));
        const form = $('#archiveform')[0];

        form.archivedescription.value = data.description ?? '';

        [
            {
                input: 'archivefinalprojectid',
                value: data.final_project_id,
                placeholder: 'Pilih Tugas Akhir'
            }
        ].forEach(({ input, value, placeholder }) => {
            applyCustomSelectValue(form, input, value, placeholder);
        });

        $('#buttonarchive').attr('data-id', JSON.stringify({ id: data.id }));
    });

    $('body').on('click', '#buttoneditreport', function (event) {
        event.preventDefault();
        reportModal.classList.add('show');
        document.body.style.overflow = 'hidden';

        const data = JSON.parse($(this).attr('data-id'));
        const form = $('#reportform')[0];

        form.reportdescription.value = data.description ?? '';

        applyCustomSelectValue(form, 'reportfinalprojectid', data.final_project_id, 'Pilih Tugas Akhir');

        $('#buttonreport').attr('data-id', JSON.stringify({ id: data.id }));
    });

    $('body').on('click', '#buttoneditsubmission', function (event) {
        event.preventDefault();
        submissionModal.classList.add('show');
        document.body.style.overflow = 'hidden';

        const data = JSON.parse($(this).attr('data-id'));
        const form = $('#submissionform')[0];

        form.submissionnim.value = data.nim ?? '';
        form.submissionstudentsname.value = data.student_name ?? '';
        form.submissionfinalprojecttitle.value = data.final_project_title ?? '';

        [
            {
                input: 'submissionstudyprogramme',
                value: data.program_study,
                placeholder: 'Pilih Program Studi'
            }
        ].forEach(({ input, value, placeholder }) => {
            const hiddenInput = $(form).find(`#${input}`);
            const select = hiddenInput.closest('.custom-select');

            select.find('.custom-option').removeClass('selected');

            const option = select.find(`.custom-option[data-value="${value}"]`);
            if (option.length) {
                option.addClass('selected');
                select.find('.custom-select-trigger span').text(option.text().trim());
            } else {
                select.find('.custom-select-trigger span').text(value || placeholder);
            }

            hiddenInput.val(value ?? '');
        });

        applyDegreeSelectValue(
            form,
            'submissiondegreelevel',
            data.degree_level,
            data.final_project_type_id,
            'Pilih Jenjang'
        );

        $('#submissionform .upload-box').eq(0).find('.file-name').empty().append(
            checkedFileLabel(getDisplayFileName(data.final_project_file), getStoredFileSize(data.final_project_file))
        );
        $('#submissionform .upload-box').eq(1).find('.file-name').empty().append(
            checkedFileLabel(getDisplayFileName(data.approval_sheet), getStoredFileSize(data.approval_sheet))
        );

        data.final_project_year
            ? submissionYearDatepicker.selectDate(new Date(parseInt(data.final_project_year, 10), 0, 1))
            : submissionYearDatepicker.clear();
        data.submission_date
            ? submissionSubmissionDatepicker.selectDate(new Date(data.submission_date))
            : submissionSubmissionDatepicker.clear();

        $('#buttonsubmission').attr('data-id', JSON.stringify({ id: data.id }));
    });

    $('body').on('click', '#buttonedituser', function (event) {
        event.preventDefault();
        userModal.classList.add('show');
        document.body.style.overflow = 'hidden';

        const data = JSON.parse($(this).attr('data-id'));
        const form = $('#userform')[0];

        form.useridentitynumber.value = data.identity_number && data.identity_number !== '-' ? data.identity_number : '';
        form.username.value = data.name ?? '';
        form.useremail.value = data.email ?? '';
        form.userpassword.value = '';
        $('#userpasswordlabel').removeClass('required-label').text('Kata sandi (opsional)');
        $('#userpassword').attr('placeholder', 'Kosongkan jika tidak diubah');

        const roleSelect = $(form).find('#userrole').closest('.custom-select');
        roleSelect.find('.custom-option').removeClass('selected');
        const roleOption = roleSelect.find(`.custom-option[data-value="${data.role}"]`);
        if (roleOption.length) {
            roleOption.addClass('selected');
            roleSelect.find('.custom-select-trigger span').text(roleOption.text().trim());
            $(form).find('#userrole').val(data.role);
        } else {
            roleSelect.find('.custom-select-trigger span').text('Pilih Peran');
            $(form).find('#userrole').val('');
        }

        $('#buttonuser').attr('data-id', JSON.stringify({ id: data.id }));
    });

    $('body').on('click', '#buttoneditimport', function (event) {

        event.preventDefault();

        importModal.classList.add('show');
        document.body.style.overflow = 'hidden';

        const tableRow = $(this).closest('tr');
        editedImportRow = importTable.row(tableRow.hasClass('child') ? tableRow.prev() : tableRow);

        const data = JSON.parse($(this).attr('data-id'));
        const form = $('#importform')[0];

        // FORM
        form.importnim.value = data.nim ?? '';
        form.importstudentsname.value = data.student_name ?? '';
        form.importfinalprojecttitle.value = data.final_project_title ?? '';

        // CUSTOM SELECT
        [
            {
                input: 'importstudyprogramme',
                value: data.program_study,
                placeholder: 'Pilih Program Studi'
            }
        ].forEach(({ input, value, placeholder }) => {

            const hiddenInput = $(form).find(`#${input}`);
            const select = hiddenInput.closest('.custom-select');

            select.find('.custom-option').removeClass('selected');

            const option = select.find(`.custom-option[data-value="${value}"]`);

            if (option.length) {
                option.addClass('selected');
            }

            hiddenInput.val(value ?? '');

            select.find('.custom-select-trigger span')
                .text(value || placeholder);

        });

        applyDegreeSelectValue(
            form,
            'importdegreelevel',
            data.degree_level,
            data.final_project_type_id,
            'Pilih Jenjang'
        );

        // FILE
        const finalProjectInput = form.importfinalprojectfile;
        const approvalSheetInput = form.importapprovalsheet;

        // kosongkan dulu supaya tidak membawa file sebelumnya
        finalProjectInput.value = '';
        approvalSheetInput.value = '';

        const finalProjectFile = importFileStore.get(data.id);
        const approvalSheetFile = approvalSheetStore.get(data.id);

        if (finalProjectFile) {
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(finalProjectFile);
            finalProjectInput.files = dataTransfer.files;
        }

        if (approvalSheetFile) {
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(approvalSheetFile);
            approvalSheetInput.files = dataTransfer.files;
        }

        // NAMA FILE
        const uploadBoxes = $(form).find('.upload-box');

        const files = [
            {
                file: finalProjectFile,
                path: data.final_project_file,
                size: finalProjectFile
                    ? formatImportFileSize(finalProjectFile)
                    : getStoredFileSize(data.final_project_file)
            },
            {
                file: approvalSheetFile,
                path: data.approval_sheet,
                size: approvalSheetFile
                    ? formatImportFileSize(approvalSheetFile)
                    : getStoredFileSize(data.approval_sheet)
            }
        ];

        files.forEach(({ file, path, size }, i) => {

            const fileName = file?.name ?? path?.split('/').pop();

            const name = uploadBoxes.eq(i).find('.file-name');
            if (!fileName) {
                name.text('Belum ada file dipilih');
                return;
            }
            name.empty().append(checkedFileLabel(fileName, size));

        });

        // DATEPICKER
        if (data.final_project_year) {
            importFinalProjectYearDatepicker.selectDate(new Date(parseInt(data.final_project_year), 0, 1));
        } else {
            importFinalProjectYearDatepicker.clear();
        }

        if (data.submission_date) {
            importSubmissionDatepicker.selectDate(new Date(data.submission_date));
        } else {
            importSubmissionDatepicker.clear();
        }

        // BUTTON DATA
        $('#buttonimport').attr(
            'data-id',
            JSON.stringify({
                id: data.id
            })
        );

    });

    /* ----------------------------------------------------------------
     * BAGIAN: Hapus satu
     * Satu baris, lalu konfirmasi.
     * ---------------------------------------------------------------- */
    // CRUD: Single Delete
    $('body').on('click', '#buttondeletefinalproject', function(event) {
        event.preventDefault();
        const typeLabel = (typeof getFinalProjectTypeContext === 'function'
            ? getFinalProjectTypeContext().type
            : null) || 'Tugas Akhir';
        alertModal.dataset.source = 'finalproject';
        alertModal.dataset.id = this.dataset.id;
        showAlert(`Apakah Anda yakin menghapus data ${typeLabel}?`, 'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!');
    });

    $('body').on('click', '#buttondeletestudyprogram', function (event) {
        event.preventDefault();
        alertModal.dataset.source = 'studyprogram';
        alertModal.dataset.id = this.dataset.id;
        showAlert(
            'Apakah Anda yakin menghapus data Program Studi?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeletemembership', function (event) {
        event.preventDefault();
        alertModal.dataset.source = 'membership';
        delete alertModal.dataset.ids;
        alertModal.dataset.id = this.dataset.id;
        showAlert(
            'Apakah Anda yakin menghapus data Keanggotaan?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeletefinalprojecttype', function (event) {
        event.preventDefault();
        alertModal.dataset.source = 'finalprojecttype';
        alertModal.dataset.id = this.dataset.id;
        showAlert(
            'Apakah Anda yakin menghapus data Jenis Tugas Akhir?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeletevisitor', function (event) {
        event.preventDefault();
        alertModal.dataset.source = 'visitor';
        alertModal.dataset.id = this.dataset.id;
        showAlert(
            'Apakah Anda yakin menghapus data Pengunjung?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeletebookgrant', function (event) {
        event.preventDefault();
        alertModal.dataset.source = 'bookgrant';
        alertModal.dataset.id = this.dataset.id;
        showAlert(
            'Apakah Anda yakin menghapus data Hibah Buku?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeletelibraryclearance', function (event) {
        event.preventDefault();
        alertModal.dataset.source = 'libraryclearance';
        alertModal.dataset.id = this.dataset.id;
        showAlert(
            'Apakah Anda yakin menghapus data Bebas Pustaka?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeletebarcode', function (event) {
        event.preventDefault();
        alertModal.dataset.source = 'barcode';
        alertModal.dataset.id = this.dataset.id;
        showAlert(
            'Apakah Anda yakin menghapus data Kode Batang?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeletedestruction', function (event) {
        event.preventDefault();
        alertModal.dataset.source = 'destruction';
        alertModal.dataset.id = this.dataset.id;
        showAlert(
            'Apakah Anda yakin menghapus data Pemusnahan?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeletearchive', function (event) {
        event.preventDefault();
        alertModal.dataset.source = 'archive';
        alertModal.dataset.id = this.dataset.id;
        showAlert(
            'Apakah Anda yakin menghapus data Arsip?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeletereport', function (event) {
        event.preventDefault();
        alertModal.dataset.source = 'report';
        alertModal.dataset.id = this.dataset.id;
        showAlert(
            'Apakah Anda yakin menghapus data Laporan?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeletesubmission', function (event) {
        event.preventDefault();
        alertModal.dataset.source = 'submission';
        alertModal.dataset.id = this.dataset.id;
        showAlert(
            'Apakah Anda yakin menghapus data Penyerahan?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeleteuser', function (event) {
        event.preventDefault();
        alertModal.dataset.source = 'user';
        alertModal.dataset.id = this.dataset.id;
        showAlert(
            'Apakah Anda yakin menghapus data Pengguna?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeleteimport', function(event) {
        event.preventDefault();
        alertModal.dataset.source = 'import';
        alertModal.dataset.rowIndex = importTable.row($(this).closest('tr')).index();
        showAlert(
            'Apakah Anda yakin menghapus data Impor?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    /* ----------------------------------------------------------------
     * BAGIAN: Hapus massal
     * Beberapa baris, lalu konfirmasi.
     * ---------------------------------------------------------------- */
    // CRUD: Bulk/Batch Delete
    $('body').on('click', '#buttondeleteallfinalproject', event => {
        event.preventDefault();
        const typeLabel = (typeof getFinalProjectTypeContext === 'function'
            ? getFinalProjectTypeContext().type
            : null) || 'Tugas Akhir';
        alertModal.dataset.source = 'finalproject';
        const ids = finalProjectTable.$('.row-check:checked').map((_, element) => element.value).get();
        if (!ids.length) return showError(`Gagal hapus data ${typeLabel}.`, 'Tidak ada data yang dipilih!');
        alertModal.dataset.ids = JSON.stringify(ids);
        showAlert(`Apakah Anda yakin menghapus data ${typeLabel}?`, 'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!');
    });

    $('body').on('click', '#buttondeleteallstudyprogram', event => {
        event.preventDefault();
        alertModal.dataset.source = 'studyprogram';
        const ids = studyProgramTable.$('.row-check:checked').map((_, element) => element.value).get();
        if (!ids.length) return showError('Gagal hapus data Program Studi.', 'Tidak ada data yang dipilih!');
        alertModal.dataset.ids = JSON.stringify(ids);
        showAlert(
            'Apakah Anda yakin menghapus data Program Studi?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeleteallmembership', event => {
        event.preventDefault();
        alertModal.dataset.source = 'membership';
        delete alertModal.dataset.id;
        const ids = membershipTable.$('.row-check:checked').map((_, element) => element.value).get();
        if (!ids.length) return showError('Gagal hapus data Keanggotaan.', 'Tidak ada data yang dipilih!');
        alertModal.dataset.ids = JSON.stringify(ids);
        showAlert(
            'Apakah Anda yakin menghapus data Keanggotaan?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeleteallfinalprojecttype', event => {
        event.preventDefault();
        alertModal.dataset.source = 'finalprojecttype';
        const ids = finalProjectTypeTable.$('.row-check:checked').map((_, element) => element.value).get();
        if (!ids.length) return showError('Gagal hapus data Jenis Tugas Akhir.', 'Tidak ada data yang dipilih!');
        alertModal.dataset.ids = JSON.stringify(ids);
        showAlert(
            'Apakah Anda yakin menghapus data Jenis Tugas Akhir?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeleteallvisitor', event => {
        event.preventDefault();
        alertModal.dataset.source = 'visitor';
        const ids = visitorTable.$('.row-check:checked').map((_, element) => element.value).get();
        if (!ids.length) return showError('Gagal hapus data Pengunjung.', 'Tidak ada data yang dipilih!');
        alertModal.dataset.ids = JSON.stringify(ids);
        showAlert(
            'Apakah Anda yakin menghapus data Pengunjung?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeleteallbookgrant', event => {
        event.preventDefault();
        alertModal.dataset.source = 'bookgrant';
        const ids = bookGrantTable.$('.row-check:checked').map((_, element) => element.value).get();
        if (!ids.length) return showError('Gagal hapus data Hibah Buku.', 'Tidak ada data yang dipilih!');
        alertModal.dataset.ids = JSON.stringify(ids);
        showAlert(
            'Apakah Anda yakin menghapus data Hibah Buku?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeletealllibraryclearance', event => {
        event.preventDefault();
        alertModal.dataset.source = 'libraryclearance';
        const ids = libraryClearanceTable.$('.row-check:checked').map((_, element) => element.value).get();
        if (!ids.length) return showError('Gagal hapus data Bebas Pustaka.', 'Tidak ada data yang dipilih!');
        alertModal.dataset.ids = JSON.stringify(ids);
        showAlert(
            'Apakah Anda yakin menghapus data Bebas Pustaka?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeleteallbarcode', event => {
        event.preventDefault();
        alertModal.dataset.source = 'barcode';
        const ids = barcodeTable.$('.row-check:checked').map((_, element) => element.value).get();
        if (!ids.length) return showError('Gagal hapus data Kode Batang.', 'Tidak ada data yang dipilih!');
        alertModal.dataset.ids = JSON.stringify(ids);
        showAlert(
            'Apakah Anda yakin menghapus data Kode Batang?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeletealldestruction', event => {
        event.preventDefault();
        alertModal.dataset.source = 'destruction';
        const ids = destructionTable.$('.row-check:checked').map((_, element) => element.value).get();
        if (!ids.length) return showError('Gagal hapus data Pemusnahan.', 'Tidak ada data yang dipilih!');
        alertModal.dataset.ids = JSON.stringify(ids);
        showAlert(
            'Apakah Anda yakin menghapus data Pemusnahan?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeleteallarchive', event => {
        event.preventDefault();
        alertModal.dataset.source = 'archive';
        const ids = archiveTable.$('.row-check:checked').map((_, element) => element.value).get();
        if (!ids.length) return showError('Gagal hapus data Arsip.', 'Tidak ada data yang dipilih!');
        alertModal.dataset.ids = JSON.stringify(ids);
        showAlert(
            'Apakah Anda yakin menghapus data Arsip?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeleteallreport', event => {
        event.preventDefault();
        alertModal.dataset.source = 'report';
        const ids = reportTable.$('.row-check:checked').map((_, element) => element.value).get();
        if (!ids.length) return showError('Gagal hapus data Laporan.', 'Tidak ada data yang dipilih!');
        alertModal.dataset.ids = JSON.stringify(ids);
        showAlert(
            'Apakah Anda yakin menghapus data Laporan?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeleteallsubmission', event => {
        event.preventDefault();
        alertModal.dataset.source = 'submission';
        const ids = submissionTable.$('.row-check:checked').map((_, element) => element.value).get();
        if (!ids.length) return showError('Gagal hapus data Penyerahan.', 'Tidak ada data yang dipilih!');
        alertModal.dataset.ids = JSON.stringify(ids);
        showAlert(
            'Apakah Anda yakin menghapus data Penyerahan?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeletealluser', event => {
        event.preventDefault();
        alertModal.dataset.source = 'user';
        const ids = userTable.$('.row-check:checked').map((_, element) => element.value).get();
        if (!ids.length) return showError('Gagal hapus data Pengguna.', 'Tidak ada data yang dipilih!');
        alertModal.dataset.ids = JSON.stringify(ids);
        showAlert(
            'Apakah Anda yakin menghapus data Pengguna?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    $('body').on('click', '#buttondeleteallimport', event => {
        event.preventDefault();
        alertModal.dataset.source = 'import';
        const ids = importTable.$('.row-check:checked').map((_, element) => element.value).get();
        if (!ids.length) return showError('Gagal hapus data Impor.', 'Tidak ada data yang dipilih!');
        alertModal.dataset.ids = JSON.stringify(ids);
        delete alertModal.dataset.rowIndex; // pastikan tidak bentrok dengan single delete

        showAlert(
            'Apakah Anda yakin menghapus data Impor?',
            'Tindakan ini tidak dapat dibatalkan. Anda tidak dapat mengembalikan data ini!'
        );
    });

    /* ----------------------------------------------------------------
     * BAGIAN: Konfirmasi hapus
     * Permintaan AJAX hapus satu atau massal, termasuk antrean offline.
     * ---------------------------------------------------------------- */
    // AJAX Confirm Action for Single or Mass Delete
    $('body').on('click', '.btn-submit-confirm', async function () {
        if (alertModal.dataset.source === 'import' && alertModal.dataset.rowIndex) {
            const rowIndex = parseInt(alertModal.dataset.rowIndex, 10);
            const rowNumber = importTable.row(rowIndex).data()?.[0];

            importTable.row(rowIndex).remove().draw(false);
            if (rowNumber) {
                importFileStore.delete(rowNumber);
                approvalSheetStore.delete(rowNumber);
            }

            closeAlert();
            delete alertModal.dataset.source;
            delete alertModal.dataset.rowIndex;

            showSuccess(
                'Berhasil hapus data Impor.',
                'Data impor telah dihapus dari tabel.'
            );
            return;
        } else if (alertModal.dataset.source === 'import' && alertModal.dataset.ids) {

            const ids = JSON.parse(alertModal.dataset.ids);
            const total = ids.length;

            setProgress(
                'Menghapus Data Impor.',
                'Data impor sedang dihapus dari tabel.',
                0,
                'Memulai proses...'
            );

            // Agar pencarian cepat (O(1))
            const idSet = new Set(ids.map(String));

            // Hapus file dari Map sambil update progress
            const batchSize = 100;

            for (let i = 0; i < total; i++) {

                const id = ids[i];

                importFileStore.delete(+id);
                approvalSheetStore.delete(+id);

                // Update progress setiap 100 data atau saat data terakhir
                if ((i + 1) % batchSize === 0 || i === total - 1) {

                    setProgress(
                        'Menghapus Data Impor.',
                        'Data impor sedang dihapus dari tabel.',
                        Math.round(((i + 1) / total) * 100),
                        `Menghapus ${i + 1} dari ${total} data...`
                    );

                    await new Promise(resolve => requestAnimationFrame(resolve));
                }
            }

            // Hapus semua baris sekaligus (jauh lebih cepat)
            importTable.rows((_, data) => idSet.has(String(data[0]))).remove().draw(false);

            closeAlert();

            delete alertModal.dataset.source;
            delete alertModal.dataset.ids;

            setProgress(
                'Menghapus Data Impor.',
                'Data impor berhasil dihapus dari tabel.',
                100,
                'Selesai'
            );

            showSuccess(
                'Berhasil hapus data Impor.',
                `${total} data impor telah dihapus dari tabel.`
            );

            return;
        } else if (alertModal.dataset.source === 'finalproject' && alertModal.dataset.id) {
            // Single Delete Request
            const id = alertModal.dataset.id;

            const finishSingleDelete = (header, paragraph) => {
                closeModal(); closeAlert(); delete alertModal.dataset.source; delete alertModal.dataset.id;
                showSuccess(header, paragraph);
            };

            // Offline: antre hapus di IndexedDB, dikirim saat koneksi kembali
            const queueOffline = async () => {
                await OfflineSync.queueDelete('finalproject', id, csrfToken);
                finishSingleDelete(
                    'Terhapus secara luring.',
                    'Penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: `/finalproject/destroy/${id}`, type: 'POST', data: { _method: 'DELETE', _token: csrfToken },
                success: () => {
                    const typeLabel = (typeof getFinalProjectTypeContext === 'function'
                        ? getFinalProjectTypeContext().type
                        : null) || 'Tugas Akhir';
                    finalProjectTable.ajax.reload(null, false);
                    finishSingleDelete(`Berhasil hapus data ${typeLabel}.`, `Data ${typeLabel} telah berhasil dihapus dari basis data.`);
                },
                error: xhr => {
                    const typeLabel = (typeof getFinalProjectTypeContext === 'function'
                        ? getFinalProjectTypeContext().type
                        : null) || 'Tugas Akhir';
                    return xhr.status === 0
                        ? queueOffline()
                        : showError(`Gagal hapus data ${typeLabel}.`, 'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.');
                }
            });
        } else if (alertModal.dataset.source === 'studyprogram' && alertModal.dataset.id) {
            const id = alertModal.dataset.id;

            const finishSingleDelete = (header, paragraph) => {
                closeModal(); closeAlert(); delete alertModal.dataset.source; delete alertModal.dataset.id;
                showSuccess(header, paragraph);
            };

            const queueOffline = async () => {
                await OfflineSync.queueDelete('studyprogram', id, csrfToken);
                finishSingleDelete(
                    'Terhapus secara luring.',
                    'Penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: `/studyprogram/destroy/${id}`,
                type: 'POST',
                data: { _method: 'DELETE', _token: csrfToken },
                success: () => {
                    studyProgramTable.ajax.reload(null, false);
                    refreshStudyProgramOptions();
                    finishSingleDelete(
                        'Berhasil hapus data Program Studi.',
                        'Data Program Studi telah berhasil dihapus dari basis data.'
                    );
                },
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Gagal hapus data Program Studi.',
                        'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        } else if (alertModal.dataset.source === 'membership' && alertModal.dataset.id) {
            const id = alertModal.dataset.id;

            const finishSingleDelete = (header, paragraph) => {
                closeModal(); closeAlert(); delete alertModal.dataset.source; delete alertModal.dataset.id;
                showSuccess(header, paragraph);
            };

            const queueOffline = async () => {
                await OfflineSync.queueDelete('membership', id, csrfToken);
                finishSingleDelete(
                    'Terhapus secara luring.',
                    'Penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: `/membership/destroy/${id}`,
                type: 'POST',
                data: { _method: 'DELETE', _token: csrfToken },
                success: () => {
                    membershipTable.ajax.reload(null, false);
                    finishSingleDelete(
                        'Berhasil hapus data Keanggotaan.',
                        'Data Keanggotaan telah berhasil dihapus dari basis data.'
                    );
                },
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Gagal hapus data Keanggotaan.',
                        'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        } else if (alertModal.dataset.source === 'finalprojecttype' && alertModal.dataset.id) {
            const id = alertModal.dataset.id;

            const finishSingleDelete = (header, paragraph) => {
                closeModal(); closeAlert(); delete alertModal.dataset.source; delete alertModal.dataset.id;
                showSuccess(header, paragraph);
            };

            const queueOffline = async () => {
                await OfflineSync.queueDelete('finalprojecttype', id, csrfToken);
                finishSingleDelete(
                    'Terhapus secara luring.',
                    'Penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: `/finalprojecttype/destroy/${id}`,
                type: 'POST',
                data: { _method: 'DELETE', _token: csrfToken },
                success: () => {
                    finalProjectTypeTable.ajax.reload(null, false);
                    refreshDegreeLevelOptions();
                    finishSingleDelete(
                        'Berhasil hapus data Jenis Tugas Akhir.',
                        'Data Jenis Tugas Akhir telah berhasil dihapus dari basis data. Muat ulang halaman untuk memperbarui menu navigasi.'
                    );
                },
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Gagal hapus data Jenis Tugas Akhir.',
                        'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        } else if (alertModal.dataset.source === 'visitor' && alertModal.dataset.id) {
            const id = alertModal.dataset.id;

            const finishSingleDelete = (header, paragraph) => {
                closeModal(); closeAlert(); delete alertModal.dataset.source; delete alertModal.dataset.id;
                showSuccess(header, paragraph);
            };

            const queueOffline = async () => {
                await OfflineSync.queueDelete('visitor', id, csrfToken);
                finishSingleDelete(
                    'Terhapus secara luring.',
                    'Penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: `/visitor/destroy/${id}`,
                type: 'POST',
                data: { _method: 'DELETE', _token: csrfToken },
                success: () => {
                    visitorTable.ajax.reload(null, false);
                    
                    finishSingleDelete(
                        'Berhasil hapus data Pengunjung.',
                        'Data Pengunjung telah berhasil dihapus dari basis data.'
                    );
                },
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Gagal hapus data Pengunjung.',
                        'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        } else if (alertModal.dataset.source === 'bookgrant' && alertModal.dataset.id) {
            const id = alertModal.dataset.id;

            const finishSingleDelete = (header, paragraph) => {
                closeModal(); closeAlert(); delete alertModal.dataset.source; delete alertModal.dataset.id;
                showSuccess(header, paragraph);
            };

            const queueOffline = async () => {
                await OfflineSync.queueDelete('bookgrant', id, csrfToken);
                finishSingleDelete(
                    'Terhapus secara luring.',
                    'Penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: `/bookgrant/destroy/${id}`,
                type: 'POST',
                data: { _method: 'DELETE', _token: csrfToken },
                success: () => {
                    bookGrantTable.ajax.reload(null, false);
                    
                    finishSingleDelete(
                        'Berhasil hapus data Hibah Buku.',
                        'Data Hibah Buku telah berhasil dihapus dari basis data.'
                    );
                },
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Gagal hapus data Hibah Buku.',
                        'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        } else if (alertModal.dataset.source === 'libraryclearance' && alertModal.dataset.id) {
            const id = alertModal.dataset.id;

            const finishSingleDelete = (header, paragraph) => {
                closeModal(); closeAlert(); delete alertModal.dataset.source; delete alertModal.dataset.id;
                showSuccess(header, paragraph);
            };

            const queueOffline = async () => {
                await OfflineSync.queueDelete('libraryclearance', id, csrfToken);
                finishSingleDelete(
                    'Terhapus secara luring.',
                    'Penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: `/libraryclearance/destroy/${id}`,
                type: 'POST',
                data: { _method: 'DELETE', _token: csrfToken },
                success: () => {
                    libraryClearanceTable.ajax.reload(null, false);
                    
                    finishSingleDelete(
                        'Berhasil hapus data Bebas Pustaka.',
                        'Data Bebas Pustaka telah berhasil dihapus dari basis data.'
                    );
                },
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Gagal hapus data Bebas Pustaka.',
                        'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        } else if (alertModal.dataset.source === 'barcode' && alertModal.dataset.id) {
            const id = alertModal.dataset.id;

            const finishSingleDelete = (header, paragraph) => {
                closeModal(); closeAlert(); delete alertModal.dataset.source; delete alertModal.dataset.id;
                showSuccess(header, paragraph);
            };

            const queueOffline = async () => {
                await OfflineSync.queueDelete('barcode', id, csrfToken);
                finishSingleDelete(
                    'Terhapus secara luring.',
                    'Penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: `/barcode/destroy/${id}`,
                type: 'POST',
                data: { _method: 'DELETE', _token: csrfToken },
                success: () => {
                    barcodeTable.ajax.reload(null, false);
                    
                    finishSingleDelete(
                        'Berhasil hapus data Kode Batang.',
                        'Data Kode Batang telah berhasil dihapus dari basis data.'
                    );
                },
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Gagal hapus data Kode Batang.',
                        'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        } else if (alertModal.dataset.source === 'destruction' && alertModal.dataset.id) {
            const id = alertModal.dataset.id;

            const finishSingleDelete = (header, paragraph) => {
                closeModal(); closeAlert(); delete alertModal.dataset.source; delete alertModal.dataset.id;
                showSuccess(header, paragraph);
            };

            const queueOffline = async () => {
                await OfflineSync.queueDelete('destruction', id, csrfToken);
                finishSingleDelete(
                    'Terhapus secara luring.',
                    'Penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: `/destruction/destroy/${id}`,
                type: 'POST',
                data: { _method: 'DELETE', _token: csrfToken },
                success: () => {
                    destructionTable.ajax.reload(null, false);
                    
                    finishSingleDelete(
                        'Berhasil hapus data Pemusnahan.',
                        'Data Pemusnahan telah berhasil dihapus dari basis data.'
                    );
                },
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Gagal hapus data Pemusnahan.',
                        'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        } else if (alertModal.dataset.source === 'archive' && alertModal.dataset.id) {
            const id = alertModal.dataset.id;

            const finishSingleDelete = (header, paragraph) => {
                closeModal(); closeAlert(); delete alertModal.dataset.source; delete alertModal.dataset.id;
                showSuccess(header, paragraph);
            };

            const queueOffline = async () => {
                await OfflineSync.queueDelete('archive', id, csrfToken);
                finishSingleDelete(
                    'Terhapus secara luring.',
                    'Penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: `/archive/destroy/${id}`,
                type: 'POST',
                data: { _method: 'DELETE', _token: csrfToken },
                success: () => {
                    archiveTable.ajax.reload(null, false);
                    
                    finishSingleDelete(
                        'Berhasil hapus data Arsip.',
                        'Data Arsip telah berhasil dihapus dari basis data.'
                    );
                },
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Gagal hapus data Arsip.',
                        'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        } else if (alertModal.dataset.source === 'report' && alertModal.dataset.id) {
            const id = alertModal.dataset.id;

            const finishSingleDelete = (header, paragraph) => {
                closeModal(); closeAlert(); delete alertModal.dataset.source; delete alertModal.dataset.id;
                showSuccess(header, paragraph);
            };

            const queueOffline = async () => {
                await OfflineSync.queueDelete('report', id, csrfToken);
                finishSingleDelete(
                    'Terhapus secara luring.',
                    'Penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: `/report/destroy/${id}`,
                type: 'POST',
                data: { _method: 'DELETE', _token: csrfToken },
                success: () => {
                    reportTable.ajax.reload(null, false);
                    
                    finishSingleDelete(
                        'Berhasil hapus data Laporan.',
                        'Data Laporan telah berhasil dihapus dari basis data.'
                    );
                },
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Gagal hapus data Laporan.',
                        'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        } else if (alertModal.dataset.source === 'submission' && alertModal.dataset.id) {
            const id = alertModal.dataset.id;

            const finishSingleDelete = (header, paragraph) => {
                closeModal(); closeAlert(); delete alertModal.dataset.source; delete alertModal.dataset.id;
                showSuccess(header, paragraph);
            };

            const queueOffline = async () => {
                await OfflineSync.queueDelete('submission', id, csrfToken);
                finishSingleDelete(
                    'Terhapus secara luring.',
                    'Penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: `/submission/destroy/${id}`,
                type: 'POST',
                data: { _method: 'DELETE', _token: csrfToken },
                success: () => {
                    submissionTable.ajax.reload(null, false);
                    
                    finishSingleDelete(
                        'Berhasil hapus data Penyerahan.',
                        'Data Penyerahan telah berhasil dihapus dari basis data.'
                    );
                },
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Gagal hapus data Penyerahan.',
                        'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        } else if (alertModal.dataset.source === 'user' && alertModal.dataset.id) {
            const id = alertModal.dataset.id;

            const finishSingleDelete = (header, paragraph) => {
                closeModal(); closeAlert(); delete alertModal.dataset.source; delete alertModal.dataset.id;
                showSuccess(header, paragraph);
            };

            const queueOffline = async () => {
                await OfflineSync.queueDelete('user', id, csrfToken);
                finishSingleDelete(
                    'Terhapus secara luring.',
                    'Penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.'
                );
            };

            if (OfflineSync.isOffline()) return void queueOffline();

            $.ajax({
                url: `/user/destroy/${id}`,
                type: 'POST',
                data: { _method: 'DELETE', _token: csrfToken },
                success: () => {
                    userTable.ajax.reload(null, false);
                    finishSingleDelete(
                        'Berhasil hapus data Pengguna.',
                        'Data Pengguna telah berhasil dihapus dari basis data.'
                    );
                },
                error: xhr => xhr.status === 0
                    ? queueOffline()
                    : showError(
                        'Gagal hapus data Pengguna.',
                        'Terjadi kesalahan pada peladen. Coba beberapa saat lagi.'
                    )
            });
        } else if (alertModal.dataset.source === 'finalproject' && alertModal.dataset.ids) {
            // Mass Delete Requests
            const ids = JSON.parse(alertModal.dataset.ids);

            // Offline: antre seluruh penghapusan di IndexedDB
            if (OfflineSync.isOffline()) {
                for (const id of ids) await OfflineSync.queueDelete('finalproject', id, csrfToken);

                closeModal(); closeAlert();
                delete alertModal.dataset.source; delete alertModal.dataset.ids;

                showSuccess(
                    'Terhapus secara luring.',
                    `${ids.length} penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.`
                );
                return;
            }

            let completed = 0;
            const succeeded = [], failed = [];

            // Progress awal
            setProgress(
                'Menghapus Data.',
                'Data sedang dihapus dari basis data.',
                0,
                `Menghapus 0 dari ${ids.length} data...`
            );

            ids.forEach(id => {

                $.ajax({
                    url: `/finalproject/destroy/${id}`,
                    type: 'POST',
                    data: {
                        _method: 'DELETE',
                        _token: csrfToken
                    },

                    complete(xhr) {

                        if (xhr.status < 400) {
                            succeeded.push(id);
                        } else {
                            failed.push(id);
                        }

                        completed++;

                        const percent = Math.round((completed / ids.length) * 100);

                        setProgress(
                            'Menghapus Data.',
                            'Data sedang dihapus dari basis data.',
                            percent,
                            `Menghapus ${completed} dari ${ids.length} data...`
                        );

                        if (completed === ids.length) {

                            closeModal();
                            closeAlert();

                            delete alertModal.dataset.source;
                            delete alertModal.dataset.ids;

                            finalProjectTable.ajax.reload(null, false);

                            setProgress(
                                'Menghapus Data.',
                                'Data berhasil dihapus dari basis data.',
                                100,
                                'Selesai'
                            );

                            showSuccess(
                                `Berhasil hapus data ${(typeof getFinalProjectTypeContext === 'function' ? getFinalProjectTypeContext().type : null) || 'Tugas Akhir'}.`,
                                `Data ${(typeof getFinalProjectTypeContext === 'function' ? getFinalProjectTypeContext().type : null) || 'Tugas Akhir'} telah berhasil dihapus dari basis data. Proses selesai berhasil: ${succeeded.length} gagal: ${failed.length}`
                            );
                        }
                    }
                });

            });
        } else if (alertModal.dataset.source === 'studyprogram' && alertModal.dataset.ids) {
            const ids = JSON.parse(alertModal.dataset.ids);

            if (OfflineSync.isOffline()) {
                for (const id of ids) await OfflineSync.queueDelete('studyprogram', id, csrfToken);

                closeModal(); closeAlert();
                delete alertModal.dataset.source; delete alertModal.dataset.ids;

                showSuccess(
                    'Terhapus secara luring.',
                    `${ids.length} penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.`
                );
                return;
            }

            let completed = 0;
            const succeeded = [], failed = [];

            setProgress(
                'Menghapus Data.',
                'Data sedang dihapus dari basis data.',
                0,
                `Menghapus 0 dari ${ids.length} data...`
            );

            ids.forEach(id => {
                $.ajax({
                    url: `/studyprogram/destroy/${id}`,
                    type: 'POST',
                    data: { _method: 'DELETE', _token: csrfToken },
                    complete(xhr) {
                        if (xhr.status < 400) {
                            succeeded.push(id);
                        } else {
                            failed.push(id);
                        }

                        completed++;

                        setProgress(
                            'Menghapus Data.',
                            'Data sedang dihapus dari basis data.',
                            Math.round((completed / ids.length) * 100),
                            `Menghapus ${completed} dari ${ids.length} data...`
                        );

                        if (completed === ids.length) {
                            closeModal();
                            closeAlert();
                            delete alertModal.dataset.source;
                            delete alertModal.dataset.ids;

                            studyProgramTable.ajax.reload(null, false);
                            refreshStudyProgramOptions();

                            setProgress(
                                'Menghapus Data.',
                                'Data berhasil dihapus dari basis data.',
                                100,
                                'Selesai'
                            );

                            showSuccess(
                                'Berhasil hapus data Program Studi.',
                                `Data Program Studi telah berhasil dihapus dari basis data. Proses selesai berhasil: ${succeeded.length} gagal: ${failed.length}`
                            );
                        }
                    }
                });
            });
        } else if (alertModal.dataset.source === 'membership' && alertModal.dataset.ids) {
            const ids = JSON.parse(alertModal.dataset.ids);

            if (OfflineSync.isOffline()) {
                for (const id of ids) await OfflineSync.queueDelete('membership', id, csrfToken);

                closeModal(); closeAlert();
                delete alertModal.dataset.source; delete alertModal.dataset.ids;

                showSuccess(
                    'Terhapus secara luring.',
                    `${ids.length} penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.`
                );
                return;
            }

            let completed = 0;
            const succeeded = [], failed = [];

            setProgress(
                'Menghapus Data.',
                'Data sedang dihapus dari basis data.',
                0,
                `Menghapus 0 dari ${ids.length} data...`
            );

            ids.forEach(id => {
                $.ajax({
                    url: `/membership/destroy/${id}`,
                    type: 'POST',
                    data: { _method: 'DELETE', _token: csrfToken },
                    complete(xhr) {
                        if (xhr.status < 400) {
                            succeeded.push(id);
                        } else {
                            failed.push(id);
                        }

                        completed++;

                        setProgress(
                            'Menghapus Data.',
                            'Data sedang dihapus dari basis data.',
                            Math.round((completed / ids.length) * 100),
                            `Menghapus ${completed} dari ${ids.length} data...`
                        );

                        if (completed === ids.length) {
                            closeModal();
                            closeAlert();
                            delete alertModal.dataset.source;
                            delete alertModal.dataset.ids;

                            membershipTable.ajax.reload(null, false);

                            setProgress(
                                'Menghapus Data.',
                                'Data berhasil dihapus dari basis data.',
                                100,
                                'Selesai'
                            );

                            showSuccess(
                                'Berhasil hapus data Keanggotaan.',
                                `Data Keanggotaan telah berhasil dihapus dari basis data. Proses selesai berhasil: ${succeeded.length} gagal: ${failed.length}`
                            );
                        }
                    }
                });
            });
        } else if (alertModal.dataset.source === 'finalprojecttype' && alertModal.dataset.ids) {
            const ids = JSON.parse(alertModal.dataset.ids);

            if (OfflineSync.isOffline()) {
                for (const id of ids) await OfflineSync.queueDelete('finalprojecttype', id, csrfToken);

                closeModal(); closeAlert();
                delete alertModal.dataset.source; delete alertModal.dataset.ids;

                showSuccess(
                    'Terhapus secara luring.',
                    `${ids.length} penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.`
                );
                return;
            }

            let completed = 0;
            const succeeded = [], failed = [];

            setProgress(
                'Menghapus Data.',
                'Data sedang dihapus dari basis data.',
                0,
                `Menghapus 0 dari ${ids.length} data...`
            );

            ids.forEach(id => {
                $.ajax({
                    url: `/finalprojecttype/destroy/${id}`,
                    type: 'POST',
                    data: { _method: 'DELETE', _token: csrfToken },
                    complete(xhr) {
                        if (xhr.status < 400) {
                            succeeded.push(id);
                        } else {
                            failed.push(id);
                        }

                        completed++;

                        setProgress(
                            'Menghapus Data.',
                            'Data sedang dihapus dari basis data.',
                            Math.round((completed / ids.length) * 100),
                            `Menghapus ${completed} dari ${ids.length} data...`
                        );

                        if (completed === ids.length) {
                            closeModal();
                            closeAlert();
                            delete alertModal.dataset.source;
                            delete alertModal.dataset.ids;

                            finalProjectTypeTable.ajax.reload(null, false);
                            refreshDegreeLevelOptions();

                            setProgress(
                                'Menghapus Data.',
                                'Data berhasil dihapus dari basis data.',
                                100,
                                'Selesai'
                            );

                            showSuccess(
                                'Berhasil hapus data Jenis Tugas Akhir.',
                                `Data Jenis Tugas Akhir telah berhasil dihapus dari basis data. Proses selesai berhasil: ${succeeded.length} gagal: ${failed.length}`
                            );
                        }
                    }
                });
            });
        } else if (alertModal.dataset.source === 'visitor' && alertModal.dataset.ids) {
            const ids = JSON.parse(alertModal.dataset.ids);

            if (OfflineSync.isOffline()) {
                for (const id of ids) await OfflineSync.queueDelete('visitor', id, csrfToken);

                closeModal(); closeAlert();
                delete alertModal.dataset.source; delete alertModal.dataset.ids;

                showSuccess(
                    'Terhapus secara luring.',
                    `${ids.length} penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.`
                );
                return;
            }

            let completed = 0;
            const succeeded = [], failed = [];

            setProgress(
                'Menghapus Data.',
                'Data sedang dihapus dari basis data.',
                0,
                `Menghapus 0 dari ${ids.length} data...`
            );

            ids.forEach(id => {
                $.ajax({
                    url: `/visitor/destroy/${id}`,
                    type: 'POST',
                    data: { _method: 'DELETE', _token: csrfToken },
                    complete(xhr) {
                        if (xhr.status < 400) {
                            succeeded.push(id);
                        } else {
                            failed.push(id);
                        }

                        completed++;

                        setProgress(
                            'Menghapus Data.',
                            'Data sedang dihapus dari basis data.',
                            Math.round((completed / ids.length) * 100),
                            `Menghapus ${completed} dari ${ids.length} data...`
                        );

                        if (completed === ids.length) {
                            closeModal();
                            closeAlert();
                            delete alertModal.dataset.source;
                            delete alertModal.dataset.ids;

                            visitorTable.ajax.reload(null, false);

                            setProgress(
                                'Menghapus Data.',
                                'Data berhasil dihapus dari basis data.',
                                100,
                                'Selesai'
                            );

                            showSuccess(
                                'Berhasil hapus data Pengunjung.',
                                `Data Pengunjung telah berhasil dihapus dari basis data. Proses selesai berhasil: ${succeeded.length} gagal: ${failed.length}`
                            );
                        }
                    }
                });
            });
        } else if (alertModal.dataset.source === 'bookgrant' && alertModal.dataset.ids) {
            const ids = JSON.parse(alertModal.dataset.ids);

            if (OfflineSync.isOffline()) {
                for (const id of ids) await OfflineSync.queueDelete('bookgrant', id, csrfToken);

                closeModal(); closeAlert();
                delete alertModal.dataset.source; delete alertModal.dataset.ids;

                showSuccess(
                    'Terhapus secara luring.',
                    `${ids.length} penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.`
                );
                return;
            }

            let completed = 0;
            const succeeded = [], failed = [];

            setProgress(
                'Menghapus Data.',
                'Data sedang dihapus dari basis data.',
                0,
                `Menghapus 0 dari ${ids.length} data...`
            );

            ids.forEach(id => {
                $.ajax({
                    url: `/bookgrant/destroy/${id}`,
                    type: 'POST',
                    data: { _method: 'DELETE', _token: csrfToken },
                    complete(xhr) {
                        if (xhr.status < 400) {
                            succeeded.push(id);
                        } else {
                            failed.push(id);
                        }

                        completed++;

                        setProgress(
                            'Menghapus Data.',
                            'Data sedang dihapus dari basis data.',
                            Math.round((completed / ids.length) * 100),
                            `Menghapus ${completed} dari ${ids.length} data...`
                        );

                        if (completed === ids.length) {
                            closeModal();
                            closeAlert();
                            delete alertModal.dataset.source;
                            delete alertModal.dataset.ids;

                            bookGrantTable.ajax.reload(null, false);

                            setProgress(
                                'Menghapus Data.',
                                'Data berhasil dihapus dari basis data.',
                                100,
                                'Selesai'
                            );

                            showSuccess(
                                'Berhasil hapus data Hibah Buku.',
                                `Data Hibah Buku telah berhasil dihapus dari basis data. Proses selesai berhasil: ${succeeded.length} gagal: ${failed.length}`
                            );
                        }
                    }
                });
            });
        } else if (alertModal.dataset.source === 'libraryclearance' && alertModal.dataset.ids) {
            const ids = JSON.parse(alertModal.dataset.ids);

            if (OfflineSync.isOffline()) {
                for (const id of ids) await OfflineSync.queueDelete('libraryclearance', id, csrfToken);

                closeModal(); closeAlert();
                delete alertModal.dataset.source; delete alertModal.dataset.ids;

                showSuccess(
                    'Terhapus secara luring.',
                    `${ids.length} penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.`
                );
                return;
            }

            let completed = 0;
            const succeeded = [], failed = [];

            setProgress(
                'Menghapus Data.',
                'Data sedang dihapus dari basis data.',
                0,
                `Menghapus 0 dari ${ids.length} data...`
            );

            ids.forEach(id => {
                $.ajax({
                    url: `/libraryclearance/destroy/${id}`,
                    type: 'POST',
                    data: { _method: 'DELETE', _token: csrfToken },
                    complete(xhr) {
                        if (xhr.status < 400) {
                            succeeded.push(id);
                        } else {
                            failed.push(id);
                        }

                        completed++;

                        setProgress(
                            'Menghapus Data.',
                            'Data sedang dihapus dari basis data.',
                            Math.round((completed / ids.length) * 100),
                            `Menghapus ${completed} dari ${ids.length} data...`
                        );

                        if (completed === ids.length) {
                            closeModal();
                            closeAlert();
                            delete alertModal.dataset.source;
                            delete alertModal.dataset.ids;

                            libraryClearanceTable.ajax.reload(null, false);

                            setProgress(
                                'Menghapus Data.',
                                'Data berhasil dihapus dari basis data.',
                                100,
                                'Selesai'
                            );

                            showSuccess(
                                'Berhasil hapus data Bebas Pustaka.',
                                `Data Bebas Pustaka telah berhasil dihapus dari basis data. Proses selesai berhasil: ${succeeded.length} gagal: ${failed.length}`
                            );
                        }
                    }
                });
            });
        } else if (alertModal.dataset.source === 'barcode' && alertModal.dataset.ids) {
            const ids = JSON.parse(alertModal.dataset.ids);

            if (OfflineSync.isOffline()) {
                for (const id of ids) await OfflineSync.queueDelete('barcode', id, csrfToken);

                closeModal(); closeAlert();
                delete alertModal.dataset.source; delete alertModal.dataset.ids;

                showSuccess(
                    'Terhapus secara luring.',
                    `${ids.length} penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.`
                );
                return;
            }

            let completed = 0;
            const succeeded = [], failed = [];

            setProgress(
                'Menghapus Data.',
                'Data sedang dihapus dari basis data.',
                0,
                `Menghapus 0 dari ${ids.length} data...`
            );

            ids.forEach(id => {
                $.ajax({
                    url: `/barcode/destroy/${id}`,
                    type: 'POST',
                    data: { _method: 'DELETE', _token: csrfToken },
                    complete(xhr) {
                        if (xhr.status < 400) {
                            succeeded.push(id);
                        } else {
                            failed.push(id);
                        }

                        completed++;

                        setProgress(
                            'Menghapus Data.',
                            'Data sedang dihapus dari basis data.',
                            Math.round((completed / ids.length) * 100),
                            `Menghapus ${completed} dari ${ids.length} data...`
                        );

                        if (completed === ids.length) {
                            closeModal();
                            closeAlert();
                            delete alertModal.dataset.source;
                            delete alertModal.dataset.ids;

                            barcodeTable.ajax.reload(null, false);

                            setProgress(
                                'Menghapus Data.',
                                'Data berhasil dihapus dari basis data.',
                                100,
                                'Selesai'
                            );

                            showSuccess(
                                'Berhasil hapus data Kode Batang.',
                                `Data Kode Batang telah berhasil dihapus dari basis data. Proses selesai berhasil: ${succeeded.length} gagal: ${failed.length}`
                            );
                        }
                    }
                });
            });
        } else if (alertModal.dataset.source === 'destruction' && alertModal.dataset.ids) {
            const ids = JSON.parse(alertModal.dataset.ids);

            if (OfflineSync.isOffline()) {
                for (const id of ids) await OfflineSync.queueDelete('destruction', id, csrfToken);

                closeModal(); closeAlert();
                delete alertModal.dataset.source; delete alertModal.dataset.ids;

                showSuccess(
                    'Terhapus secara luring.',
                    `${ids.length} penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.`
                );
                return;
            }

            let completed = 0;
            const succeeded = [], failed = [];

            setProgress(
                'Menghapus Data.',
                'Data sedang dihapus dari basis data.',
                0,
                `Menghapus 0 dari ${ids.length} data...`
            );

            ids.forEach(id => {
                $.ajax({
                    url: `/destruction/destroy/${id}`,
                    type: 'POST',
                    data: { _method: 'DELETE', _token: csrfToken },
                    complete(xhr) {
                        if (xhr.status < 400) {
                            succeeded.push(id);
                        } else {
                            failed.push(id);
                        }

                        completed++;

                        setProgress(
                            'Menghapus Data.',
                            'Data sedang dihapus dari basis data.',
                            Math.round((completed / ids.length) * 100),
                            `Menghapus ${completed} dari ${ids.length} data...`
                        );

                        if (completed === ids.length) {
                            closeModal();
                            closeAlert();
                            delete alertModal.dataset.source;
                            delete alertModal.dataset.ids;

                            destructionTable.ajax.reload(null, false);

                            setProgress(
                                'Menghapus Data.',
                                'Data berhasil dihapus dari basis data.',
                                100,
                                'Selesai'
                            );

                            showSuccess(
                                'Berhasil hapus data Pemusnahan.',
                                `Data Pemusnahan telah berhasil dihapus dari basis data. Proses selesai berhasil: ${succeeded.length} gagal: ${failed.length}`
                            );
                        }
                    }
                });
            });
        } else if (alertModal.dataset.source === 'archive' && alertModal.dataset.ids) {
            const ids = JSON.parse(alertModal.dataset.ids);

            if (OfflineSync.isOffline()) {
                for (const id of ids) await OfflineSync.queueDelete('archive', id, csrfToken);

                closeModal(); closeAlert();
                delete alertModal.dataset.source; delete alertModal.dataset.ids;

                showSuccess(
                    'Terhapus secara luring.',
                    `${ids.length} penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.`
                );
                return;
            }

            let completed = 0;
            const succeeded = [], failed = [];

            setProgress(
                'Menghapus Data.',
                'Data sedang dihapus dari basis data.',
                0,
                `Menghapus 0 dari ${ids.length} data...`
            );

            ids.forEach(id => {
                $.ajax({
                    url: `/archive/destroy/${id}`,
                    type: 'POST',
                    data: { _method: 'DELETE', _token: csrfToken },
                    complete(xhr) {
                        if (xhr.status < 400) {
                            succeeded.push(id);
                        } else {
                            failed.push(id);
                        }

                        completed++;

                        setProgress(
                            'Menghapus Data.',
                            'Data sedang dihapus dari basis data.',
                            Math.round((completed / ids.length) * 100),
                            `Menghapus ${completed} dari ${ids.length} data...`
                        );

                        if (completed === ids.length) {
                            closeModal();
                            closeAlert();
                            delete alertModal.dataset.source;
                            delete alertModal.dataset.ids;

                            archiveTable.ajax.reload(null, false);

                            setProgress(
                                'Menghapus Data.',
                                'Data berhasil dihapus dari basis data.',
                                100,
                                'Selesai'
                            );

                            showSuccess(
                                'Berhasil hapus data Arsip.',
                                `Data Arsip telah berhasil dihapus dari basis data. Proses selesai berhasil: ${succeeded.length} gagal: ${failed.length}`
                            );
                        }
                    }
                });
            });
        } else if (alertModal.dataset.source === 'report' && alertModal.dataset.ids) {
            const ids = JSON.parse(alertModal.dataset.ids);

            if (OfflineSync.isOffline()) {
                for (const id of ids) await OfflineSync.queueDelete('report', id, csrfToken);

                closeModal(); closeAlert();
                delete alertModal.dataset.source; delete alertModal.dataset.ids;

                showSuccess(
                    'Terhapus secara luring.',
                    `${ids.length} penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.`
                );
                return;
            }

            let completed = 0;
            const succeeded = [], failed = [];

            setProgress(
                'Menghapus Data.',
                'Data sedang dihapus dari basis data.',
                0,
                `Menghapus 0 dari ${ids.length} data...`
            );

            ids.forEach(id => {
                $.ajax({
                    url: `/report/destroy/${id}`,
                    type: 'POST',
                    data: { _method: 'DELETE', _token: csrfToken },
                    complete(xhr) {
                        if (xhr.status < 400) {
                            succeeded.push(id);
                        } else {
                            failed.push(id);
                        }

                        completed++;

                        setProgress(
                            'Menghapus Data.',
                            'Data sedang dihapus dari basis data.',
                            Math.round((completed / ids.length) * 100),
                            `Menghapus ${completed} dari ${ids.length} data...`
                        );

                        if (completed === ids.length) {
                            closeModal();
                            closeAlert();
                            delete alertModal.dataset.source;
                            delete alertModal.dataset.ids;

                            reportTable.ajax.reload(null, false);

                            setProgress(
                                'Menghapus Data.',
                                'Data berhasil dihapus dari basis data.',
                                100,
                                'Selesai'
                            );

                            showSuccess(
                                'Berhasil hapus data Laporan.',
                                `Data Laporan telah berhasil dihapus dari basis data. Proses selesai berhasil: ${succeeded.length} gagal: ${failed.length}`
                            );
                        }
                    }
                });
            });
        } else if (alertModal.dataset.source === 'submission' && alertModal.dataset.ids) {
            const ids = JSON.parse(alertModal.dataset.ids);

            if (OfflineSync.isOffline()) {
                for (const id of ids) await OfflineSync.queueDelete('submission', id, csrfToken);

                closeModal(); closeAlert();
                delete alertModal.dataset.source; delete alertModal.dataset.ids;

                showSuccess(
                    'Terhapus secara luring.',
                    `${ids.length} penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.`
                );
                return;
            }

            let completed = 0;
            const succeeded = [], failed = [];

            setProgress(
                'Menghapus Data.',
                'Data sedang dihapus dari basis data.',
                0,
                `Menghapus 0 dari ${ids.length} data...`
            );

            ids.forEach(id => {
                $.ajax({
                    url: `/submission/destroy/${id}`,
                    type: 'POST',
                    data: { _method: 'DELETE', _token: csrfToken },
                    complete(xhr) {
                        if (xhr.status < 400) {
                            succeeded.push(id);
                        } else {
                            failed.push(id);
                        }

                        completed++;

                        setProgress(
                            'Menghapus Data.',
                            'Data sedang dihapus dari basis data.',
                            Math.round((completed / ids.length) * 100),
                            `Menghapus ${completed} dari ${ids.length} data...`
                        );

                        if (completed === ids.length) {
                            closeModal();
                            closeAlert();
                            delete alertModal.dataset.source;
                            delete alertModal.dataset.ids;

                            submissionTable.ajax.reload(null, false);

                            setProgress(
                                'Menghapus Data.',
                                'Data berhasil dihapus dari basis data.',
                                100,
                                'Selesai'
                            );

                            showSuccess(
                                'Berhasil hapus data Penyerahan.',
                                `Data Penyerahan telah berhasil dihapus dari basis data. Proses selesai berhasil: ${succeeded.length} gagal: ${failed.length}`
                            );
                        }
                    }
                });
            });
        } else if (alertModal.dataset.source === 'user' && alertModal.dataset.ids) {
            const ids = JSON.parse(alertModal.dataset.ids);

            if (OfflineSync.isOffline()) {
                for (const id of ids) await OfflineSync.queueDelete('user', id, csrfToken);

                closeModal(); closeAlert();
                delete alertModal.dataset.source; delete alertModal.dataset.ids;

                showSuccess(
                    'Terhapus secara luring.',
                    `${ids.length} penghapusan disimpan di perangkat dan akan disinkronkan otomatis saat koneksi kembali.`
                );
                return;
            }

            let completed = 0;
            const succeeded = [], failed = [];

            setProgress(
                'Menghapus Data.',
                'Data sedang dihapus dari basis data.',
                0,
                `Menghapus 0 dari ${ids.length} data...`
            );

            ids.forEach(id => {
                $.ajax({
                    url: `/user/destroy/${id}`,
                    type: 'POST',
                    data: { _method: 'DELETE', _token: csrfToken },
                    complete(xhr) {
                        if (xhr.status < 400) {
                            succeeded.push(id);
                        } else {
                            failed.push(id);
                        }

                        completed++;

                        setProgress(
                            'Menghapus Data.',
                            'Data sedang dihapus dari basis data.',
                            Math.round((completed / ids.length) * 100),
                            `Menghapus ${completed} dari ${ids.length} data...`
                        );

                        if (completed === ids.length) {
                            closeModal();
                            closeAlert();
                            delete alertModal.dataset.source;
                            delete alertModal.dataset.ids;

                            userTable.ajax.reload(null, false);

                            setProgress(
                                'Menghapus Data.',
                                'Data berhasil dihapus dari basis data.',
                                100,
                                'Selesai'
                            );

                            showSuccess(
                                'Berhasil hapus data Pengguna.',
                                `Data Pengguna telah berhasil dihapus dari basis data. Proses selesai berhasil: ${succeeded.length} gagal: ${failed.length}`
                            );
                        }
                    }
                });
            });
        }

    });

});
