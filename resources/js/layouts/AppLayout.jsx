import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard, ClipboardList, FileText, Building2, LogOut, ChevronRight,
} from 'lucide-react';
import { cn } from '../lib/utils';

const nav = [
    { label: 'Dashboard',   href: '/dashboard', icon: LayoutDashboard },
    { label: 'Form Orders', href: '/orders',     icon: ClipboardList },
    { label: 'Invoices',    href: '/invoices',   icon: FileText },
    { label: 'Branches',    href: '/branches',   icon: Building2 },
];

function NavItem({ item, current }) {
    const Icon = item.icon;
    const active = current.startsWith(item.href);
    return (
        <Link
            href={item.href}
            className={cn(
                'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                active
                    ? 'bg-white/15 text-white'
                    : 'text-white/70 hover:bg-white/10 hover:text-white',
            )}
        >
            <Icon size={16} />
            {item.label}
            {active && <ChevronRight size={14} className="ml-auto opacity-60" />}
        </Link>
    );
}

export default function AppLayout({ children, title }) {
    const { url, props } = usePage();
    const user = props.auth?.user;

    return (
        <div className="flex h-screen bg-gray-50">
            {/* Sidebar */}
            <aside className="flex w-60 flex-col bg-[#185FA5]">
                {/* Logo */}
                <div className="flex h-20 items-center justify-center px-4 py-3">
                    <img
                        src="/images/GSACLogo.png"
                        alt="GSAC"
                        className="h-full w-auto object-contain drop-shadow-sm"
                    />
                </div>

                <nav className="flex-1 space-y-0.5 px-3 py-2">
                    {nav.map((item) => (
                        <NavItem key={item.href} item={item} current={url} />
                    ))}
                </nav>

                <div className="border-t border-white/20 p-3">
                    <div className="flex items-center gap-2 rounded-lg px-2 py-2">
                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-xs font-bold text-white uppercase">
                            {user?.name?.charAt(0) ?? 'U'}
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-xs font-semibold text-white">{user?.name}</p>
                            <p className="truncate text-[10px] text-white/60 capitalize">{user?.role?.replace('_', ' ')}</p>
                        </div>
                        <Link href="/logout" method="post" as="button" className="text-white/60 hover:text-white">
                            <LogOut size={14} />
                        </Link>
                    </div>
                </div>
            </aside>

            {/* Main */}
            <div className="flex flex-1 flex-col overflow-hidden">
                <header className="flex h-16 items-center justify-between border-b border-gray-200 bg-white px-6">
                    <h1 className="text-lg font-semibold text-gray-900">{title}</h1>
                </header>

                <main className="flex-1 overflow-auto p-6">
                    {children}
                </main>
            </div>
        </div>
    );
}
