// assets/js/main.js
// Shared front-end behavior for Auren. Kept minimal in Phase 1 —
// interactive pieces (search-as-you-type filters, confirm-before-delete
// modals, etc.) get added feature-by-feature as those pages are built.

document.addEventListener('DOMContentLoaded', function () {
    // Auto-dismiss Bootstrap alerts (used for flash messages like
    // "Job posted successfully") after a few seconds.
    document.querySelectorAll('.alert-auto-dismiss').forEach(function (alertEl) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
            bsAlert.close();
        }, 4000);
    });
});
