import { cn } from '../../lib/utils';

const variants = {
    default:     'bg-[#185FA5] text-white hover:bg-[#14508d] shadow-sm',
    destructive: 'bg-red-600 text-white hover:bg-red-700 shadow-sm',
    outline:     'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50',
    ghost:       'text-gray-600 hover:bg-gray-100',
    secondary:   'bg-gray-100 text-gray-800 hover:bg-gray-200',
};

const sizes = {
    sm: 'h-8 px-3 text-xs',
    md: 'h-9 px-4 text-sm',
    lg: 'h-10 px-6 text-sm',
};

export function Button({ variant = 'default', size = 'md', className, disabled, children, ...props }) {
    return (
        <button
            className={cn(
                'inline-flex items-center gap-1.5 rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-[#185FA5] focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed',
                variants[variant],
                sizes[size],
                className,
            )}
            disabled={disabled}
            {...props}
        >
            {children}
        </button>
    );
}
