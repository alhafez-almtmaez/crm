const statusStyles = {
    on_track: {
        badge: 'border-transparent bg-[var(--status-success)] text-[var(--status-success-contrast)]',
        text: 'text-[var(--status-success)]',
        progress: 'bg-[var(--status-success)]',
    },
    completed: {
        badge: 'border-transparent bg-[var(--status-success)] text-[var(--status-success-contrast)]',
        text: 'text-[var(--status-success)]',
        progress: 'bg-[var(--status-success)]',
    },
    ahead: {
        badge: 'border-transparent bg-[var(--status-info)] text-[var(--status-info-contrast)]',
        text: 'text-[var(--status-info)]',
        progress: 'bg-[var(--status-info)]',
    },
    behind: {
        badge: 'border-transparent bg-[var(--status-danger)] text-[var(--status-danger-contrast)]',
        text: 'text-[var(--status-danger)]',
        progress: 'bg-[var(--status-danger)]',
    },
    not_due: {
        badge: 'border-transparent bg-[var(--status-neutral)] text-[var(--status-neutral-contrast)]',
        text: 'text-[var(--status-neutral)]',
        progress: 'bg-[var(--status-neutral)]',
    },
    missing_student_plan: {
        badge: 'border-transparent bg-[var(--status-warning)] text-[var(--status-warning-contrast)]',
        text: 'text-[var(--status-warning)]',
        progress: 'bg-[var(--status-warning)]',
    },
    plan_mismatch: {
        badge: 'border-transparent bg-[var(--status-warning)] text-[var(--status-warning-contrast)]',
        text: 'text-[var(--status-warning)]',
        progress: 'bg-[var(--status-warning)]',
    },
};

const fallbackStyle = {
    badge: 'border-transparent bg-[var(--status-neutral)] text-[var(--status-neutral-contrast)]',
    text: 'text-[var(--status-neutral)]',
    progress: 'bg-[var(--status-neutral)]',
};

const styleFor = (status) => statusStyles[status] ?? fallbackStyle;

export const planStatusBadgeClass = (status) => styleFor(status).badge;
export const planStatusTextClass = (status) => styleFor(status).text;
export const planStatusProgressClass = (status) => styleFor(status).progress;
