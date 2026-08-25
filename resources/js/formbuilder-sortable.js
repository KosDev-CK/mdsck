import Sortable from 'sortablejs';

window.FormBuilderSortable = {
    init(listEl, wireComponent) {
        if (listEl.__sortableInstance) {
            listEl.__sortableInstance.destroy();
        }

        listEl.__sortableInstance = new Sortable(listEl, {
            handle: '.drag-handle',
            animation: 150,
            onEnd() {
                const ids = [...listEl.children]
                    .map((el) => el.dataset.fieldId)
                    .filter(Boolean);

                wireComponent.call('reorderFields', ids);
            },
        });
    },
};
