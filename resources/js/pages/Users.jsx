import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import { Card, CardContent } from '../components/ui/card';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Input, Select } from '../components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../components/ui/table';
import { Pencil, Plus, X, Check } from 'lucide-react';

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

export default function Users({ users, branches }) {
    const { props } = usePage();
    const flash = props.flash ?? {};

    const [editingId, setEditingId]   = useState(null);
    const [editData, setEditData]     = useState({});
    const [showCreate, setShowCreate] = useState(false);
    const [createData, setCreateData] = useState(EMPTY_FORM);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors]         = useState({});

    function startEdit(user) {
        setEditingId(user.id);
        setEditData({ ...user, password: '', password_confirmation: '' });
        setShowCreate(false);
        setErrors({});
    }

    function cancelEdit() {
        setEditingId(null);
        setEditData({});
        setErrors({});
    }

    function saveEdit(e) {
        e.preventDefault();
        setSubmitting(true);
        router.patch(`/users/${editingId}`, editData, {
            onSuccess: () => { setEditingId(null); setEditData({}); setErrors({}); },
            onError:   (errs) => setErrors(errs),
            onFinish:  () => setSubmitting(false),
        });
    }

    function saveCreate(e) {
        e.preventDefault();
        setSubmitting(true);
        router.post('/users', createData, {
            onSuccess: () => { setShowCreate(false); setCreateData(EMPTY_FORM); setErrors({}); },
            onError:   (errs) => setErrors(errs),
            onFinish:  () => setSubmitting(false),
        });
    }

    function setCreate(key, val) {
        setCreateData(prev => ({ ...prev, [key]: val }));
    }

    function setEdit(key, val) {
        setEditData(prev => ({ ...prev, [key]: val }));
    }

    function UserForm({ data, onChange, onSubmit, onCancel, isCreate }) {
        return (
            <form onSubmit={onSubmit} className="space-y-3">
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-gray-600">Full Name</label>
                        <Input
                            value={data.name}
                            onChange={e => onChange('name', e.target.value)}
                            placeholder="Juan dela Cruz"
                            required
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
                            {!isCreate && <span className="ml-1 text-gray-400">(leave blank to keep)</span>}
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
                            <option value="">— No branch (Admin) —</option>
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
                        id={`is_active_${isCreate ? 'create' : data.id}`}
                        checked={!!data.is_active}
                        onChange={e => onChange('is_active', e.target.checked)}
                        className="h-4 w-4 rounded border-gray-300"
                    />
                    <label
                        htmlFor={`is_active_${isCreate ? 'create' : data.id}`}
                        className="text-xs font-medium text-gray-600"
                    >
                        Active account
                    </label>
                </div>
                <div className="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="outline" onClick={onCancel}>Cancel</Button>
                    <Button type="submit" disabled={submitting}>
                        {isCreate ? 'Create User' : 'Save Changes'}
                    </Button>
                </div>
            </form>
        );
    }

    return (
        <AppLayout title="Users">
            {flash.success && (
                <div className="mb-4 rounded-md bg-green-50 px-4 py-2 text-sm text-green-700 border border-green-200">
                    {flash.success}
                </div>
            )}

            <div className="mb-4 flex justify-end">
                <Button onClick={() => { setShowCreate(!showCreate); setEditingId(null); setErrors({}); }}>
                    <Plus size={14} /> New User
                </Button>
            </div>

            {/* Create form */}
            {showCreate && (
                <Card className="mb-4 border-[#185FA5]/30">
                    <CardContent className="p-5">
                        <h2 className="mb-4 text-sm font-semibold text-gray-800">New User</h2>
                        <UserForm
                            data={createData}
                            onChange={setCreate}
                            onSubmit={saveCreate}
                            onCancel={() => { setShowCreate(false); setErrors({}); }}
                            isCreate
                        />
                    </CardContent>
                </Card>
            )}

            {/* Edit form (shown above table when editing) */}
            {editingId && (
                <Card className="mb-4 border-amber-300">
                    <CardContent className="p-5">
                        <h2 className="mb-4 text-sm font-semibold text-gray-800">
                            Edit User — <span className="text-[#185FA5]">{editData.name}</span>
                        </h2>
                        <UserForm
                            data={editData}
                            onChange={setEdit}
                            onSubmit={saveEdit}
                            onCancel={cancelEdit}
                            isCreate={false}
                        />
                    </CardContent>
                </Card>
            )}

            {/* Users table */}
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
                            {users.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={7} className="py-10 text-center text-gray-400">
                                        No users found.
                                    </TableCell>
                                </TableRow>
                            )}
                            {users.map((user) => (
                                <TableRow
                                    key={user.id}
                                    className={editingId === user.id ? 'bg-amber-50' : ''}
                                >
                                    <TableCell className="font-medium">{user.name}</TableCell>
                                    <TableCell className="text-xs text-gray-500">{user.email}</TableCell>
                                    <TableCell>
                                        <Badge className={ROLE_COLORS[user.role] ?? 'bg-gray-100 text-gray-600'}>
                                            {ROLE_LABELS[user.role] ?? user.role}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {user.branch
                                            ? <span>{user.branch.name}</span>
                                            : <span className="text-gray-400 text-xs italic">—</span>
                                        }
                                    </TableCell>
                                    <TableCell>
                                        <Badge className={user.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}>
                                            {user.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-xs text-gray-400">
                                        {user.last_login_at
                                            ? new Date(user.last_login_at).toLocaleDateString()
                                            : '—'
                                        }
                                    </TableCell>
                                    <TableCell>
                                        {editingId === user.id ? (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="h-7 px-2 text-xs text-gray-400"
                                                onClick={cancelEdit}
                                            >
                                                <X size={12} /> Cancel
                                            </Button>
                                        ) : (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="h-7 px-2 text-xs"
                                                onClick={() => startEdit(user)}
                                            >
                                                <Pencil size={12} /> Edit
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
