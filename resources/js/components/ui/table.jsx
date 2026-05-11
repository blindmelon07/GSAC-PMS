import { cn } from '../../lib/utils';

export function Table({ className, children }) {
    return (
        <div className="w-full overflow-auto">
            <table className={cn('w-full caption-bottom text-sm', className)}>{children}</table>
        </div>
    );
}
export function TableHeader({ children }) { return <thead className="[&_tr]:border-b">{children}</thead>; }
export function TableBody({ children }) { return <tbody className="[&_tr:last-child]:border-0">{children}</tbody>; }
export function TableRow({ className, children, ...props }) {
    return <tr className={cn('border-b transition-colors hover:bg-gray-50', className)} {...props}>{children}</tr>;
}
export function TableHead({ className, children }) {
    return <th className={cn('h-11 px-4 text-left align-middle text-xs font-semibold uppercase tracking-wide text-gray-500', className)}>{children}</th>;
}
export function TableCell({ className, children }) {
    return <td className={cn('px-4 py-3 align-middle text-gray-700', className)}>{children}</td>;
}
