import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
    LayoutDashboard, ClipboardList, FileText, Building2, LogOut, ChevronRight, Tag, Users, Settings, KeyRound, X, BarChart3,
} from 'lucide-react';
import { cn } from '../lib/utils';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';

const mainNav = [
    { label: 'Dashboard',   href: '/dashboard', icon: LayoutDashboard },
    { label: 'Form Orders', href: '/orders',     icon: ClipboardList },
    { label: 'Invoices',    href: '/invoices',   icon: FileText },
];

const adminNav = [
    { label: 'Reports',    href: '/reports',    icon: BarChart3 },
    { label: 'Branches',   href: '/branches',   icon: Building2 },
    { label: 'Form Types', href: '/form-types', icon: Tag },
    { label: 'Users',      href: '/users',      icon: Users },
    { label: 'Settings',   href: '/settings',   icon: Settings },
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
                    ? 'bg-[#185FA5] text-white'
                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900',
            )}
        >
            <Icon size={16} />
            {item.label}
            {active && <ChevronRight size={14} className="ml-auto opacity-70" />}
        </Link>
    );
}

function ChangePasswordModal({ onClose }) {
    const [current,  setCurrent]  = useState('');
    const [password, setPassword] = useState('');
    const [confirm,  setConfirm]  = useState('');
    const [saving,   setSaving]   = useState(false);
    const [errors,   setErrors]   = useState({});

    function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        router.post('/profile/password', {
            current_password:      current,
            password:              password,
            password_confirmation: confirm,
        }, {
            onSuccess: () => onClose(),
            onError:   (errs) => setErrors(errs),
            onFinish:  () => setSaving(false),
        });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div className="w-full max-w-sm rounded-xl bg-white shadow-xl">
                <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h2 className="text-sm font-semibold text-gray-800">Change Password</h2>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        <X size={16} />
                    </button>
                </div>
                <form onSubmit={handleSubmit} className="space-y-4 p-5">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-gray-600">Current Password</label>
                        <Input
                            type="password"
                            value={current}
                            onChange={e => setCurrent(e.target.value)}
                            placeholder="••••••••"
                            required
                            autoFocus
                        />
                        {errors.current_password && (
                            <p className="mt-1 text-xs text-red-500">{errors.current_password}</p>
                        )}
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-gray-600">New Password</label>
                        <Input
                            type="password"
                            value={password}
                            onChange={e => setPassword(e.target.value)}
                            placeholder="••••••••"
                            required
                            minLength={8}
                        />
                        {errors.password && (
                            <p className="mt-1 text-xs text-red-500">{errors.password}</p>
                        )}
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-gray-600">Confirm New Password</label>
                        <Input
                            type="password"
                            value={confirm}
                            onChange={e => setConfirm(e.target.value)}
                            placeholder="••••••••"
                            required
                        />
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={saving}>
                            {saving ? 'Saving…' : 'Update Password'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function AppLayout({ children, title }) {
    const { url, props } = usePage();
    const user = props.auth?.user;
    const [showPasswordModal, setShowPasswordModal] = useState(false);
    const [showUserMenu, setShowUserMenu]           = useState(false);

    return (
        <div className="flex h-screen bg-gray-50">
            {showPasswordModal && <ChangePasswordModal onClose={() => setShowPasswordModal(false)} />}
            {/* Sidebar */}
            <aside className="flex w-60 flex-col border-r border-gray-200 bg-white">
                {/* Logo */}
                <div className="flex h-20 items-center justify-center border-b border-gray-100 px-4 py-3">
                    <img
                        src="/images/GSACLogo.png"
                        alt="GSAC"
                        className="h-full w-auto object-contain"
                    />
                </div>

                <nav className="flex-1 px-3 py-3 space-y-0.5">
                    {mainNav.map((item) => (
                        <NavItem key={item.href} item={item} current={url} />
                    ))}

                    {user?.role === 'admin' && (
                        <>
                            <div className="pt-4 pb-1 px-2">
                                <p className="text-[10px] font-semibold uppercase tracking-widest text-gray-400">
                                    Management
                                </p>
                            </div>
                            {adminNav.map((item) => (
                                <NavItem key={item.href} item={item} current={url} />
                            ))}
                        </>
                    )}
                </nav>

                <div className="border-t border-gray-100 p-3 relative">
                    {/* User menu popover */}
                    {showUserMenu && (
                        <>
                            <div className="fixed inset-0 z-10" onClick={() => setShowUserMenu(false)} />
                            <div className="absolute bottom-full left-3 right-3 mb-2 z-20 rounded-lg border border-gray-200 bg-white shadow-lg overflow-hidden">
                                <button
                                    onClick={() => { setShowUserMenu(false); setShowPasswordModal(true); }}
                                    className="flex w-full items-center gap-2.5 px-4 py-2.5 text-xs text-gray-700 hover:bg-gray-50 transition-colors"
                                >
                                    <KeyRound size={13} /> Change Password
                                </button>
                                <div className="h-px bg-gray-100" />
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    className="flex w-full items-center gap-2.5 px-4 py-2.5 text-xs text-red-600 hover:bg-red-50 transition-colors"
                                >
                                    <LogOut size={13} /> Logout
                                </Link>
                            </div>
                        </>
                    )}

                    {/* Clickable user row */}
                    <button
                        onClick={() => setShowUserMenu(v => !v)}
                        className="flex w-full items-center gap-2 rounded-lg px-2 py-2 hover:bg-gray-50 transition-colors"
                    >
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#185FA5] text-xs font-bold text-white uppercase">
                            {user?.name?.charAt(0) ?? 'U'}
                        </div>
                        <div className="min-w-0 flex-1 text-left">
                            <p className="truncate text-xs font-semibold text-gray-800">{user?.name}</p>
                            <p className="truncate text-[10px] text-gray-400 capitalize">{user?.role?.replace('_', ' ')}</p>
                        </div>
                        <ChevronRight size={13} className={cn('text-gray-400 transition-transform', showUserMenu && '-rotate-90')} />
                    </button>
                </div>
            </aside>

            {/* Main */}
            <div className="flex flex-1 flex-col overflow-hidden">
                <header className="flex h-16 items-center border-b border-gray-200 bg-white px-6">
                    <h1 className="text-lg font-semibold text-gray-900">{title}</h1>
                </header>

                <main className="flex-1 overflow-auto p-6">
                    {children}
                </main>
            </div>
        </div>
    );
}
