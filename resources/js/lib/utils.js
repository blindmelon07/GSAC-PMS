import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs) {
    return twMerge(clsx(inputs));
}

export function formatPeso(amount) {
    return '₱' + Number(amount).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export function statusColor(status) {
    const map = {
        pending:    'bg-yellow-100 text-yellow-800',
        approved:   'bg-blue-100 text-blue-800',
        rejected:   'bg-red-100 text-red-800',
        in_transit: 'bg-purple-100 text-purple-800',
        delivered:  'bg-green-100 text-green-800',
        billed:     'bg-gray-100 text-gray-800',
        draft:      'bg-gray-100 text-gray-700',
        sent:       'bg-blue-100 text-blue-800',
        paid:       'bg-green-100 text-green-800',
        overdue:    'bg-red-100 text-red-800',
    };
    return map[status] ?? 'bg-gray-100 text-gray-700';
}

export function priorityColor(priority) {
    return {
        low:    'bg-slate-100 text-slate-600',
        normal: 'bg-blue-50 text-blue-600',
        urgent: 'bg-red-100 text-red-700',
    }[priority] ?? '';
}
