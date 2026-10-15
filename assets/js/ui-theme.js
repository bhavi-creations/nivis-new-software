document.addEventListener('DOMContentLoaded', () => {
    const statuses = new Map([
        ['active','good'], ['available','good'], ['enabled','good'], ['paid','good'], ['approved','good'], ['issued','good'],
        ['draft','warning'], ['pending','warning'], ['part-paid','warning'], ['unpaid','warning'],
        ['inactive','bad'], ['not available','bad'], ['disabled','bad'], ['cancelled','bad'], ['rejected','bad'], ['overdue','bad']
    ]);
    const decorate = root => {
        root.querySelectorAll('tbody td').forEach(cell => {
            if (cell.children.length) return;
            const value = cell.textContent.trim();
            const tone = statuses.get(value.toLowerCase());
            if (!tone) return;
            const badge = document.createElement('span');
            badge.className = 'nivis-status'; badge.dataset.tone = tone;
            badge.textContent = value.charAt(0).toUpperCase() + value.slice(1);
            cell.replaceChildren(badge);
        });
    };
    document.querySelectorAll('tbody').forEach(body => {
        new MutationObserver(() => decorate(body.parentElement)).observe(body, {childList:true});
        decorate(body.parentElement);
    });
    const section = document.querySelector('body > section');
    if (section && section.querySelector('tbody')) {
        const key = document.createElement('div');
        key.className = 'nivis-action-key';
        key.innerHTML = '<span><i class="fa-solid fa-eye" aria-hidden="true"></i> View details</span><span><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit record</span><span><i class="fa-solid fa-trash-can" aria-hidden="true"></i> Delete with confirmation</span>';
        const heading = section.querySelector('h1');
        const header = heading?.closest('[class$="header"]');
        (header || heading)?.insertAdjacentElement('afterend', key);
    }
});
