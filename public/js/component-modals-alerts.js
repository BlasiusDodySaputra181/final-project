/** Kunci progress confirm (impor AI Groq) agar tidak bisa ditutup sebelum selesai. */
let progressConfirmLocked = false;

const lockProgressConfirm = () => {
    progressConfirmLocked = true;
    if (progressModal) {
        progressModal.classList.add('show', 'is-locked');
    }
};

const unlockProgressConfirm = () => {
    progressConfirmLocked = false;
    progressModal?.classList.remove('is-locked');
};

const showConfirm = (modalElement, header, paragraph) => {
    modalElement.classList.add('show');
    modalElement.querySelector('h2').textContent = header;
    modalElement.querySelector('p').textContent = paragraph;
};

const showAlert = (header, paragraph) => showConfirm(alertModal, header, paragraph);
const showError = (header, paragraph) => showConfirm(errorModal, header, paragraph);
const showSuccess = (header, paragraph) => showConfirm(successModal, header, paragraph);
const showProgress = (header, paragraph) => showConfirm(progressModal, header, paragraph);
const closeAlert = () => alertModal.classList.remove('show');

const closeModal = () => {
    $('.validation-tooltip, label.error').remove();

    ['#finalprojectform', '#studyprogramform', '#membershipform', '#finalprojecttypeform', '#visitorform', '#bookgrantform', '#libraryclearanceform', '#barcodeform', '#destructionform', '#archiveform', '#archivedownloadform', '#reportform', '#reportdownloadform', '#submissionform', '#userform', '#importform', '#profileform'].forEach(selector => {
        const form = $(selector);
        form.find('.error').removeClass('error');
        form.data('validator')?.resetForm();
    });

    [
        finalProjectModal,
        studyProgramModal,
        membershipModal,
        finalProjectTypeModal,
        visitorModal,
        bookGrantModal,
        libraryClearanceModal,
        barcodeModal,
        destructionModal,
        archiveModal,
        archiveDownloadModal,
        reportModal,
        reportDownloadModal,
        submissionModal,
        userModal,
        folderModal,
        importModal,
        progressModal,
        importInformationModal
    ].forEach(modal => {
        if (modal === progressModal && progressConfirmLocked) return;
        modal?.classList.remove('show');
    });

    if (!progressConfirmLocked) {
        document.body.style.overflow = '';
    }
};

$(document).ready(function() {
    const sidebarBackdrop = document.getElementById('sidebarbackdrop');

    const setMobileSidebar = open => {
        sidebar?.classList.toggle('show', open);
        sidebarToggle?.classList.toggle('active', open);
        document.body.classList.toggle('sidebar-open', open);
        sidebarBackdrop?.setAttribute('aria-hidden', open ? 'false' : 'true');
    };

    const closeMobileSidebar = () => setMobileSidebar(false);

    // Sidebar Responsiveness Toggle
    sidebarToggle?.addEventListener('click', () => {
        if (window.innerWidth <= 992) {
            setMobileSidebar(!sidebar?.classList.contains('show'));
        } else {
            sidebarToggle.classList.toggle('active');
            sidebar?.classList.toggle('collapsed');
            main?.classList.toggle('expand');
        }
    });

    sidebarBackdrop?.addEventListener('click', closeMobileSidebar);

    sidebar?.querySelectorAll('a[href]:not([href="#"])').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 992) closeMobileSidebar();
        });
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 992) {
            sidebar?.classList.remove('show');
            document.body.classList.remove('sidebar-open');
            sidebarBackdrop?.setAttribute('aria-hidden', 'true');
            if (!sidebar?.classList.contains('collapsed')) {
                sidebarToggle?.classList.remove('active');
            }
        }
    });

    // Close Modals on Dimmed Overlay Click
    $('body').on('click', '#finalprojectmodal', event => { if (event.target === finalProjectModal) closeModal(); });
    $('body').on('click', '#studyprogrammodal', event => { if (event.target === studyProgramModal) closeModal(); });
    $('body').on('click', '#membershipmodal', event => { if (event.target === membershipModal) closeModal(); });
    $('body').on('click', '#finalprojecttypemodal', event => { if (event.target === finalProjectTypeModal) closeModal(); });
    $('body').on('click', '#visitormodal', event => { if (event.target === visitorModal) closeModal(); });
    $('body').on('click', '#bookgrantmodal', event => { if (event.target === bookGrantModal) closeModal(); });
    $('body').on('click', '#libraryclearancemodal', event => { if (event.target === libraryClearanceModal) closeModal(); });
    $('body').on('click', '#barcodemodal', event => { if (event.target === barcodeModal) closeModal(); });
    $('body').on('click', '#destructionmodal', event => { if (event.target === destructionModal) closeModal(); });
    $('body').on('click', '#archivemodal', event => { if (event.target === archiveModal) closeModal(); });
    $('body').on('click', '#archivedownloadmodal', event => { if (event.target === archiveDownloadModal) closeModal(); });
    $('body').on('click', '#reportmodal', event => { if (event.target === reportModal) closeModal(); });
    $('body').on('click', '#reportdownloadmodal', event => { if (event.target === reportDownloadModal) closeModal(); });
    $('body').on('click', '#submissionmodal', event => { if (event.target === submissionModal) closeModal(); });
    $('body').on('click', '#usermodal', event => { if (event.target === userModal) closeModal(); });
    $('body').on('click', '#foldermodal', event => { if (event.target === folderModal) closeModal(); });
    $('body').on('click', '#importmodal', event => { if (event.target === importModal) closeModal(); });
    $('body').on('click', '#progressmodal', event => {
        if (event.target === progressModal && !progressConfirmLocked) closeModal();
    });
    $('body').on('click', '#informationimportmodal', event => { if (event.target === importInformationModal) closeModal(); });
    $('body').on('click', '.modal-exit', closeModal);

    // Global Confirm Modal Closer
    document.addEventListener('click', event => {
        const closeTrigger = event.target.closest?.('.confirm-close, .btn-cancel');
        const overlayClick = event.target.matches('.confirm-modal');

        if (!closeTrigger && !overlayClick) return;

        const open = closeTrigger?.closest('.confirm-modal')
            || (overlayClick ? event.target : null)
            || document.querySelector('.confirm-modal.show');

        if (!open?.classList.contains('show')) return;
        if (open === progressModal && progressConfirmLocked) return;

        open.classList.remove('show');
    });
});
