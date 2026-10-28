const numberFormatter = new Intl.NumberFormat('id-ID');

const percentFormatter = new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const dateFormatter = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'UTC',
});

const shortDateFormatter = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    timeZone: 'UTC',
});

export function formatNumber(value) {
    return numberFormatter.format(value ?? 0);
}

export function formatPercent(value) {
    return `${percentFormatter.format(value ?? 0)}%`;
}

// ISO date (YYYY-MM-DD) is parsed as UTC, so format in UTC to keep the same day.
export function formatDate(isoDate) {
    return isoDate ? dateFormatter.format(new Date(isoDate)) : '-';
}

export function formatShortDate(isoDate) {
    return isoDate ? shortDateFormatter.format(new Date(isoDate)) : '-';
}
