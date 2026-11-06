let submissionDatepicker;
let finalProjectYearDatepicker;
let importSubmissionDatepicker;
let importFinalProjectYearDatepicker;
let bookGrantYearDatepicker;
let libraryClearanceGraduationYearDatepicker;
let submissionYearDatepicker;
let submissionSubmissionDatepicker;
let membershipBirthDatepicker;

$(document).ready(function() {
    const createDatepicker = (selector, options = {}) => {
        if (typeof AirDatepicker !== 'function' || !document.querySelector(selector)) {
            return null;
        }

        return new AirDatepicker(selector, { ...options });
    };

    finalProjectYearDatepicker = createDatepicker('#finalyearproject', {
        view: 'years',
        minView: 'years',
        dateFormat: 'yyyy',
        container: '#finalprojectmodal'
    });

    importFinalProjectYearDatepicker = createDatepicker('#importfinalyearproject', {
        view: 'years',
        minView: 'years',
        dateFormat: 'yyyy',
        container: '#importmodal'
    });

    submissionDatepicker = createDatepicker('#submissiondate', {
        container: '#finalprojectmodal',
        locale: datepickerLocale
    });

    importSubmissionDatepicker = createDatepicker('#importsubmissiondate', {
        container: '#importmodal',
        locale: datepickerLocale
    });

    bookGrantYearDatepicker = createDatepicker('#bookgrantyear', {
        view: 'years',
        minView: 'years',
        dateFormat: 'yyyy',
        container: '#bookgrantmodal'
    });

    libraryClearanceGraduationYearDatepicker = createDatepicker('#libraryclearancegraduationyear', {
        view: 'years',
        minView: 'years',
        dateFormat: 'yyyy',
        container: '#libraryclearancemodal'
    });

    submissionYearDatepicker = createDatepicker('#submissionfinalyearproject', {
        view: 'years',
        minView: 'years',
        dateFormat: 'yyyy',
        container: '#submissionmodal'
    });

    submissionSubmissionDatepicker = createDatepicker('#submissionsubmissiondate', {
        container: '#submissionmodal',
        locale: datepickerLocale
    });

    membershipBirthDatepicker = createDatepicker('#membershipbirthdate', {
        container: '#membershipmodal',
        locale: datepickerLocale,
        autoClose: true,
        maxDate: new Date(),
        startDate: new Date(2000, 0, 1),
        onSelect({ date }) {
            const selected = Array.isArray(date) ? date[0] : date;
            if (!selected) return;

            const field = $('#membershipbirthdate');
            if (field.hasClass('input-error')) {
                field.valid();
            }
        }
    });
});
