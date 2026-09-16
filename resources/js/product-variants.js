function initProductVariants() {
    const container = document.querySelector(
        '[data-variant-manager]'
    );

    if (!container) {
        return;
    }

    /* Tránh khởi tạo cùng một Variant Manager nhiều lần. */
    if (container.dataset.initialized === 'true') {
        return;
    }

    container.dataset.initialized = 'true';

    const list = container.querySelector(
        '[data-variant-list]'
    );

    const template = container.querySelector(
        '[data-variant-template]'
    );

    const addButton = container.querySelector(
        '[data-add-variant]'
    );

    if (!list || !template || !addButton) {
        console.warn(
            'Product Variant Manager: thiếu thành phần HTML cần thiết.'
        );

        return;
    }

    let nextIndex = Number(
        container.dataset.nextIndex || 0
    );


    const getRows = () => {
        return [
            ...list.querySelectorAll(
                '[data-variant-row]'
            )
        ];
    };


    const updateEmptyState = () => {

        const rows = getRows();

        const visibleRows =
            rows.filter(
                row =>
                    !row.classList.contains('d-none')
            );

        const emptyState =
            container.querySelector(
                '[data-empty-variant]'
            );

        if (!emptyState) {
            return;
        }

        if (visibleRows.length === 0) {

            emptyState.classList.remove(
                'd-none'
            );

        } else {

            emptyState.classList.add(
                'd-none'
            );
        }
    };


    const renumberRows = () => {

        const rows = getRows();

        let visibleIndex = 1;

        rows.forEach((row) => {

            if (
                row.classList.contains('d-none')
            ) {
                return;
            }

            const title =
                row.querySelector(
                    '[data-variant-title]'
                );

            if (title) {
                title.textContent =
                    `Biến thể #${visibleIndex}`;
            }

            const sortInput =
                row.querySelector(
                    '[data-sort-order]'
                );

            if (
                sortInput &&
                (
                    sortInput.value === '' ||
                    sortInput.value === '0'
                )
            ) {
                sortInput.value =
                    visibleIndex;
            }

            visibleIndex++;
        });
    };


    const addRow = () => {

        const index = nextIndex++;

        const html =
            template.innerHTML
                .replaceAll(
                    '__INDEX__',
                    index
                );

        const emptyState =
            container.querySelector(
                '[data-empty-variant]'
            );

        if (emptyState) {

            emptyState.classList.add(
                'd-none'
            );
        }

        list.insertAdjacentHTML(
            'beforeend',
            html
        );

        renumberRows();

        updateEmptyState();

        const rows = getRows();

        const newRow =
            rows[rows.length - 1];

        if (newRow) {

            const nameInput =
                newRow.querySelector(
                    '[data-variant-name]'
                );

            if (nameInput) {
                nameInput.focus();
            }
        }
    };


    const removeRow = (button) => {

        const row =
            button.closest(
                '[data-variant-row]'
            );

        if (!row) {
            return;
        }

        const deleteInput =
            row.querySelector(
                '[data-delete-input]'
            );

        const existingId =
            row.querySelector(
                '[data-variant-id]'
            );


        if (
            existingId &&
            existingId.value &&
            deleteInput
        ) {

            deleteInput.value = '1';

            row.classList.add(
                'd-none'
            );

            renumberRows();

            updateEmptyState();

            return;
        }


        row.remove();

        renumberRows();

        updateEmptyState();
    };


    addButton.addEventListener(
        'click',
        function (event) {

            event.preventDefault();

            addRow();
        }
    );


    container.addEventListener(
        'click',
        function (event) {

            const removeButton =
                event.target.closest(
                    '[data-remove-variant]'
                );

            if (!removeButton) {
                return;
            }

            event.preventDefault();

            removeRow(
                removeButton
            );
        }
    );


    renumberRows();

    updateEmptyState();
}


if (
    document.readyState === 'loading'
) {

    document.addEventListener(
        'DOMContentLoaded',
        initProductVariants
    );

} else {

    initProductVariants();
}