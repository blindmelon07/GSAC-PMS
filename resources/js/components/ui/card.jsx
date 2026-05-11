import { cn } from '../../lib/utils';

export function Card({ className, children }) {
    return <div className={cn('rounded-lg border border-gray-200 bg-white shadow-sm', className)}>{children}</div>;
}

export function CardHeader({ className, children }) {
    return <div className={cn('flex flex-col space-y-1.5 p-6 pb-4', className)}>{children}</div>;
}

export function CardTitle({ className, children }) {
    return <h3 className={cn('text-base font-semibold leading-none tracking-tight text-gray-900', className)}>{children}</h3>;
}

export function CardContent({ className, children }) {
    return <div className={cn('p-6 pt-0', className)}>{children}</div>;
}
