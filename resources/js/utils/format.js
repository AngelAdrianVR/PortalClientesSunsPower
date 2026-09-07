export function fmtMoney(value) {
    const n = Number(value ?? 0);

    return n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
}

export function fmtDate(value) {
    if (!value) {
        return '—';
    }

    const raw = String(value);
    const iso = raw.includes('T') ? raw : `${raw}T00:00:00`;

    return new Date(iso).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}
