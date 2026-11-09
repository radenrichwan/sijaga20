

import Alpine from 'alpinejs';
import { Modal, Toast } from 'bootstrap';
import Chart from 'chart.js/auto';
import $ from 'jquery';
import select2 from 'select2';

window.Alpine = Alpine;
window.bootstrap = { Modal };
window.$ = window.jQuery = $;
select2(window, $);

Alpine.start();

const sidebarStateKey = 'sidebarState';
const sidebarToggle = document.querySelector('[data-sidebar-collapse-toggle]');

if (window.localStorage.getItem(sidebarStateKey) === 'collapsed') {
    document.documentElement.classList.add('sidebar-collapsed');
    sidebarToggle?.setAttribute('aria-pressed', 'true');
    sidebarToggle?.setAttribute('aria-label', 'Lebarkan menu utama');
}

$('select.select2-student').each(function () {
    $(this).select2({
        placeholder: 'Cari nama atau NIS siswa',
        width: '100%',
    });
});

document.querySelectorAll('[data-sijaga-toast]').forEach((element) => {
    new Toast(element, { delay: 4500 }).show();
});

document.querySelectorAll('[data-sidebar-collapse-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
        window.localStorage.setItem(sidebarStateKey, collapsed ? 'collapsed' : 'expanded');
        button.setAttribute('aria-pressed', String(collapsed));
        button.setAttribute('aria-label', collapsed ? 'Lebarkan menu utama' : 'Ciutkan menu utama');
    });
});

document.querySelectorAll('.sijaga-sidebar [data-sidebar-tooltip]').forEach((item) => {
    const updateTooltipPosition = () => {
        const { top, height } = item.getBoundingClientRect();
        item.style.setProperty('--sidebar-tooltip-top', `${top + height / 2}px`);
    };

    item.addEventListener('pointerenter', updateTooltipPosition);
    item.addEventListener('focus', updateTooltipPosition);
});

document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
    const configElement = document.getElementById(canvas.dataset.chart);

    if (!configElement) {
        return;
    }

    const chartData = JSON.parse(configElement.textContent);

    new Chart(canvas, chartData);
});

document.querySelectorAll('.modal').forEach((modal) => {
    modal.addEventListener('hidden.bs.modal', () => {
        modal.querySelectorAll('form').forEach((form) => form.reset());
    });
});

document.addEventListener('input', (event) => {
    const search = event.target.closest('[data-category-search]');

    if (!search) {
        return;
    }

    const select = document.getElementById(search.dataset.categorySearch);

    if (!select) {
        return;
    }

    const query = search.value.trim().toLocaleLowerCase();

    Array.from(select.options).forEach((option, index) => {
        const searchText = `${option.textContent} ${option.dataset.searchAlias ?? ''}`.toLocaleLowerCase();
        option.hidden = index !== 0 && !searchText.includes(query);
    });

    if (select.selectedOptions[0]?.hidden) {
        select.value = '';
    }
});

const violationRows = document.getElementById('violation-category-rows');
const violationTemplate = document.getElementById('violation-category-template');

if (violationRows && violationTemplate) {
    const updateRemoveButtons = () => {
        violationRows.querySelectorAll('[data-remove-violation]').forEach((button) => {
            button.disabled = violationRows.querySelectorAll('[data-violation-row]').length === 1;
        });
    };

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-add-violation]')) {
            const index = Number(violationRows.dataset.nextIndex);
            violationRows.dataset.nextIndex = String(index + 1);
            violationRows.insertAdjacentHTML('beforeend', violationTemplate.innerHTML.replaceAll('__INDEX__', String(index)));
            updateRemoveButtons();
        }

        const removeButton = event.target.closest('[data-remove-violation]');

        if (removeButton && !removeButton.disabled) {
            removeButton.closest('[data-violation-row]').remove();
            updateRemoveButtons();
        }
    });

    updateRemoveButtons();
}
