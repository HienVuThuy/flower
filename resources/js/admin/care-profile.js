/* Đổi khối "thông tin chăm sóc" theo hình thức bán đang chọn. */

function applyProfile(profileByForm, formSelect) {
    const profile = profileByForm[formSelect.value] ?? null;

    document.querySelectorAll('[data-care-panel]').forEach((panel) => {
        const match = panel.dataset.carePanel === profile;

        panel.hidden = ! match;

        panel.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = ! match;
        });
    });
}

export function initCareProfile() {
    const formSelect = document.querySelector('[data-selling-form]');

    if (! formSelect) {
        return;
    }

    let profileByForm;

    try {
        profileByForm = JSON.parse(formSelect.dataset.careProfiles || '{}');
    } catch {
        return;
    }

    applyProfile(profileByForm, formSelect);
    formSelect.addEventListener('change', () => applyProfile(profileByForm, formSelect));
}
