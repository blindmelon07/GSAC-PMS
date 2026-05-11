import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';

export default function Login() {
    const { props } = usePage();
    const errors = props.errors ?? {};

    const [email, setEmail]       = useState('');
    const [password, setPassword] = useState('');
    const [loading, setLoading]   = useState(false);

    function submit(e) {
        e.preventDefault();
        setLoading(true);
        router.post('/login', { email, password }, {
            onFinish: () => setLoading(false),
        });
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50">
            <div className="w-full max-w-sm">
                {/* Logo */}
                <div className="mb-8 flex flex-col items-center">
                    <img
                        src="/images/GSACLogo.png"
                        alt="GSAC"
                        className="h-20 w-auto object-contain"
                    />
                    <p className="mt-3 text-sm text-gray-500">Branch Form Request System</p>
                </div>

                <div className="rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-gray-700">Email</label>
                            <Input
                                type="email"
                                autoComplete="email"
                                value={email}
                                onChange={e => setEmail(e.target.value)}
                                placeholder="admin@formflow.ph"
                                required
                            />
                            {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-gray-700">Password</label>
                            <Input
                                type="password"
                                autoComplete="current-password"
                                value={password}
                                onChange={e => setPassword(e.target.value)}
                                placeholder="••••••••"
                                required
                            />
                        </div>
                        <Button type="submit" className="w-full" disabled={loading}>
                            {loading ? 'Signing in…' : 'Sign In'}
                        </Button>
                    </form>

                    <div className="mt-6 rounded-lg bg-gray-50 p-3 text-xs text-gray-500">
                        <p className="font-semibold text-gray-700 mb-1">Demo credentials</p>
                        <p>Admin: <span className="font-mono">admin@formflow.ph</span></p>
                        <p>Manager: <span className="font-mono">manager.br_001@formflow.ph</span></p>
                        <p>Staff: <span className="font-mono">staff.br_001@formflow.ph</span></p>
                        <p className="mt-1">Password: <span className="font-mono">password</span></p>
                    </div>
                </div>
            </div>
        </div>
    );
}
