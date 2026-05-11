import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import { Card, CardContent } from '../components/ui/card';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Input, Select } from '../components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../components/ui/table';
import { Pencil, Plus, X, Search } from 'lucide-react';

const ROLES = ['branch_staff', 'branch_manager', 'admin'];

const ROLE_LABELS = {
    branch_staff:   'Branch Staff',
    branch_manager: 'Branch Manager',
    admin:          'Admin',
};

const ROLE_COLORS = {
    branch_staff:   'bg-gray-100 text-gray-600',
    branch_manager: 'bg-blue-100 text-blue-700',
    admin:          'bg-purple-100 text-purple-700',
};

const EMPTY_FORM = {
    name: '', email: '', password: '', password_confirmation: '',
    role: 'branch_staff', branch_id: '', is_active: true,
};

/* ── Modal ── */
function UserModal({ title, data, onChange, onSubmit, onClose, submitting, errors, isCreate, branches }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
            <div className="w-full max-w-lg rounded-xl bg-white shadow-xl">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h2 className="text-sm font-semibold text-gray-800">{title}</h2>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        <X size={16} />
                    </button>
                </div>

                {/* Body */}
                <form onSubmit={onSubmit} className="p-6 space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-600">Full Name</label>
                            <Input
                                value={data.name}
                                onChange={e => onChange('name', e.target.value)}
                                placeholder="Juan dela Cruz"
                                required
                                autoFocus
                            />
                            {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-600">Email</label>
                            <Input
                                type="email"
                                value={data.email}
                                onChange={e => onChange('email', e.target.value)}
                                placeholder="user@example.com"
                                required
                            />
                            {errors.email && <p className="mt-1 text-xs text-red-500">{errors.email}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-600">
                                {isCreate ? 'Password' : 'New Password'}
                                {!isCreate && <span className="ml-1 font-normal text-gray-400">(blank = keep)</span>}
                            </label>
                            <Input
                                type="password"
                                value={data.password}
                                onChange={e => onChange('password', e.target.value)}
                                placeholder="••••••••"
                                required={isCreate}
                                minLength={8}
                            />
                            {errors.password && <p className="mt-1 text-xs text-red-500">{errors.password}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-600">Confirm Password</label>
                            <Input
                                type="password"
                                value={data.password_confirmation}
                                onChange={e => onChange('password_confirmation', e.target.value)}
                                placeholder="••••••••"
                                required={isCreate}
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-600">Role</label>
                            <Select value={data.role} onChange={e => onChange('role', e.target.value)} required>
                                {ROLES.map(r => (
                                    <option key={r} value={r}>{ROLE_LABELS[r]}</option>
                                ))}
                            </Select>
                            {errors.role && <p className="mt-1 text-xs text-red-500">{errors.role}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-600">Branch</label>
                            <Select value={data.branch_id ?? ''} onChange={e => onChange('branch_id', e.target.value || null)}>
                                <option value="">— No branch —</option>
                                {(branches ?? []).map(b => (
                                    <option key={b.id} value={b.id}>{b.name}</option>
                                ))}
                            </Select>
                            {errors.branch_id && <p className="mt-1 text-xs text-red-500">{errors.branch_id}</p>}
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="modal_is_active"
                            checked={!!data.is_active}
                            onChange={e => onChange('is_active', e.target.checked)}
                            className="h-4 w-4 rounded border-gray-300"
                        />
                        <label htmlFor="modal_is_active" className="text-xs font-medium text-gray-600">
                            Active account
                        </label>
                    </div>

                    {/* Footer */}
                    <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={submitting}>
                            {submitting ? 'Saving…' : isCreate ? 'Create User' : 'Save Changes'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function Users({ users, branches, filters = {} }) {
    const { props } = usePage();
    const flash = props.flash ?? {};

    const data        = users.data ?? [];
    const lastPage    = users.last_page ?? 1;
    const currentPage = users.current_page ?? 1;
    const total       = users.total ?? data.length;

    const [search,   setSearch]   = useState(filters.search    ?? '');
    const [role,     setRole]     = useState(filters.role      ?? '');
    const [branchId, setBranchId] = useState(filters.branch_id ?? '');
    const [status,   setStatus]   = useState(filters.status    ?? '');

    const [modal,      setModal]      = useState(null); // null | 'create' | 'edit'
    const [formData,   setFormData]   = useState(EMPTY_FORM);
    const [editingId,  setEditingId]  = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const [errors,     setErrors]     = useState({});

    const hasFilters = search || role || branchId || status;

    function applyFilters(overrides = {}) {
        router.get('/users', {
            search, role, branch_id: branchId, status, ...overrides,
        }, { preserveState: true, replace: true });
    }

    function clearFilters() {
        setSearch(''); setRole(''); setBranchId(''); setStatus('');
        router.get('/users', {}, { preserveState: true, replace: true });
    }

    function openCreate() {
        setFormData(EMPTY_FORM);
        setErrors({});
        setEditingId(null);
        setModal('create');
    }

    function openEdit(user) {
        setFormData({ ...user, password: '', password_confirmation: '' });
        setErrors({});
        setEditingId(user.id);
        setModal('edit');
    }

    function closeModal() {
        setModal(null);
        setErrors({});
    }

    function handleChange(key, val) {
        setFormData(prev => ({ ...prev, [key]: val }));
    }

    function submitCreate(e) {
        e.preventDefault();
        setSubmitting(true);
        router.post('/users', formData, {
            onSuccess: () => closeModal(),
            onError:   errs => setErrors(errs),
            onFinish:  () => setSubmitting(false),
        });
    }

    function submitEdit(e) {
        e.preventDefault();
        setSubmitting(true);
        router.patch(`/users/${editingId}`, formData, {
            onSuccess: () => closeModal(),
            onError:   errs => setErrors(errs),
            onFinish:  () => setSubmitting(false),
        });
    }

    function goToPage(page) {
        router.get('/users', {
            search, role, branch_id: branchId, status, page,
        }, { preserveState: true, replace: true });
    }

    return (
        <AppLayout title="Users">
            {/* Modal */}
            {modal === 'create' && (
                <UserModal
                    title="New User"
                    data={formData}
                    onChange={handleChange}
                    onSubmit={submitCreate}
                    onClose={closeModal}
                    submitting={submitting}
                    errors={errors}
                    isCreate
                    branches={branches}
                />
            )}
            {modal === 'edit' && (
                <UserModal
                    title={`Edit User — ${formData.name}`}
                    data={formData}
                    onChange={handleChange}
                    onSubmit={submitEdit}
                    onClose={closeModal}
                    submitting={submitting}
                    errors={errors}
                    isCreate={false}
                    branches={branches}
                />
            )}

            {flash.success && (
                <div className="mb-4 rounded-md bg-green-50 px-4 py-2 text-sm text-green-700 border border-green-200">
                    {flash.success}
                </div>
            )}

            {/* Filter bar */}
            <Card className="mb-4">
                <CardContent className="p-4">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="relative flex-1 min-w-48">
                            <Search size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" />
                            <Input
                                placeholder="Search name or email…"
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                onKeyDown={e => e.key === 'Enter' && applyFilters({ search })}
                                className="pl-8"
                            />
                        </div>
                        <Select value={role} onChange={e => { setRole(e.target.value); applyFilters({ role: e.target.value }); }} className="w-40">
                            <option value="">All roles</option>
                            {ROLES.map(r => <option key={r} value={r}>{ROLE_LABELS[r]}</option>)}
                        </Select>
                        <Select value={branchId} onChange={e => { setBranchId(e.target.value); applyFilters({ branch_id: e.target.value }); }} className="w-44">
                            <option value="">All branches</option>
                            {(branches ?? []).map(b => <option key={b.id} value={b.id}>{b.name}</option>)}
                        </Select>
                        <Select value={status} onChange={e => { setStatus(e.target.value); applyFilters({ status: e.target.value }); }} className="w-32">
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </Select>
                        {hasFilters && (
                            <Button variant="outline" onClick={clearFilters}>
                                <X size={13} /> Clear
                            </Button>
                        )}
                        <Button onClick={() => applyFilters({ search })} className="ml-auto">
                            <Search size={13} /> Search
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Count + New User */}
            <div className="mb-3 flex items-center justify-between">
                <p className="text-xs text-gray-500">{total} user{total !== 1 ? 's' : ''} found</p>
                <Button onClick={openCreate}>
                    <Plus size={14} /> New User
                </Button>
            </div>

            {/* Table */}
            <Card>
                <CardContent className="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Last Login</TableHead>
                                <TableHead>Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={7} className="py-10 text-center text-gray-400">
                                        No users found.
                                    </TableCell>
                                </TableRow>
                            )}
                            {data.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell className="font-medium">{user.name}</TableCell>
                                    <TableCell className="text-xs text-gray-500">{user.email}</TableCell>
                                    <TableCell>
                                        <Badge className={ROLE_COLORS[user.role] ?? 'bg-gray-100 text-gray-600'}>
                                            {ROLE_LABELS[user.role] ?? user.role}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {user.branch ? user.branch.name : <span className="text-gray-400 italic text-xs">—</span>}
                                    </TableCell>
                                    <TableCell>
                                        <Badge className={user.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}>
                                            {user.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-xs text-gray-400">
                                        {user.last_login_at ? new Date(user.last_login_at).toLocaleDateString() : '—'}
                                    </TableCell>
                                    <TableCell>
                                        <Button size="sm" variant="outline" className="h-7 px-2 text-xs" onClick={() => openEdit(user)}>
                                            <Pencil size={12} /> Edit
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            {/* Pagination */}
            {lastPage > 1 && (
                <div className="mt-4 flex items-center justify-between text-xs text-gray-500">
                    <span>Page {currentPage} of {lastPage}</span>
                    <div className="flex gap-1">
                        <Button
                            size="sm" variant="outline"
                            disabled={currentPage === 1}
                            onClick={() => goToPage(currentPage - 1)}
                        >
                            Previous
                        </Button>
                        {Array.from({ length: lastPage }, (_, i) => i + 1)
                            .filter(p => p === 1 || p === lastPage || Math.abs(p - currentPage) <= 1)
                            .reduce((acc, p, i, arr) => {
                                if (i > 0 && p - arr[i - 1] > 1) acc.push('…');
                                acc.push(p);
                                return acc;
                            }, [])
                            .map((p, i) =>
                                p === '…' ? (
                                    <span key={`ellipsis-${i}`} className="px-2 py-1 text-gray-400">…</span>
                                ) : (
                                    <Button
                                        key={p}
                                        size="sm"
                                        variant={p === currentPage ? 'default' : 'outline'}
                                        onClick={() => goToPage(p)}
                                    >
                                        {p}
                                    </Button>
                                )
                            )
                        }
                        <Button
                            size="sm" variant="outline"
                            disabled={currentPage === lastPage}
                            onClick={() => goToPage(currentPage + 1)}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
