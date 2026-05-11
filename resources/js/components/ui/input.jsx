import { cn } from '../../lib/utils';

export function Input({ className, ...props }) {
    return (
        <input
            className={cn(
                'flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-gray-400 focus:border-[#185FA5] focus:outline-none focus:ring-1 focus:ring-[#185FA5] disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        />
    );
}

export function Select({ className, children, ...props }) {
    return (
        <select
            className={cn(
                'flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-sm focus:border-[#185FA5] focus:outline-none focus:ring-1 focus:ring-[#185FA5] disabled:opacity-50',
                className,
            )}
            {...props}
        >
            {children}
        </select>
    );
}
