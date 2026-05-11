import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import { Card, CardContent } from '../components/ui/card';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { MapPin, Phone, Mail, Users, Pencil, Plus, X, Check } from 'lucide-react';

const EMPTY_FORM = {
    code: '', name: '', address: '', city: '',
    contact_person: '', contact_email: '', contact_phone: '',
    is_active: true,
};

function EditForm({ data, onChange, onSubmit, onCancel, submitting, isCreate }) {
    function field(key, label, extra = {}) {
        return (
            <div>
                <label className="mb-1 block text-xs font-medium text-gray-600">{label}</label>
                <Input
                    value={data[key] ?? ''}
                    onChange={e => onChange(key, e.target.value)}
                    {...extra}
                />
            </div>
        );
    }

    return (
        <form onSubmit={onSubmit} className="space-y-3">
            <div className="grid grid-cols-2 gap-3">
                {isCreate && field('code', 'Code', { placeholder: 'e.g. BR-001', maxLength: 20, required: true })}
                {field('name', 'Name', { placeholder: 'Branch name', required: true })}
                {field('city', 'City', { placeholder: 'City' })}
            </div>
            {field('address', 'Address', { placeholder: 'Full address' })}
            <div className="grid grid-cols-2 gap-3">
                {field('contact_person', 'Contact Person', { placeholder: 'Name' })}
                {field('contact_phone', 'Contact Phone', { placeholder: '+63…' })}
            </div>
            {field('contact_email', 'Contact Email', { type: 'email', placeholder: 'email@example.com' })}
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
                    Active
                </label>
            </div>
            <div className="flex justify-end gap-2 pt-1">
                <Button type="button" variant="outline" size="sm" onClick={onCancel}>
                    <X size={12} /> Cancel
                </Button>
                <Button type="submit" size="sm" disabled={submitting}>
                    <Check size={12} /> {isCreate ? 'Create' : 'Save'}
                </Button>
            </div>
        </form>
    );
}

export default function Branches({ branches, isAdmin }) {
    const { props } = usePage();
    const flash = props.flash ?? {};

    const [editingId, setEditingId]   = useState(null);
    const [editData, setEditData]     = useState({});
    const [showCreate, setShowCreate] = useState(false);
    const [createData, setCreateData] = useState(EMPTY_FORM);
    const [submitting, setSubmitting] = useState(false);

    function startEdit(branch) {
        setEditingId(branch.id);
        setEditData({ ...branch });
        setShowCreate(false);
    }

    function cancelEdit() {
        setEditingId(null);
        setEditData({});
    }

    function saveEdit(e) {
        e.preventDefault();
        setSubmitting(true);
        router.patch(`/branches/${editingId}`, editData, {
            onSuccess: () => { setEditingId(null); setEditData({}); },
            onFinish:  () => setSubmitting(false),
        });
    }

    function saveCreate(e) {
        e.preventDefault();
        setSubmitting(true);
        router.post('/branches', createData, {
            onSuccess: () => { setShowCreate(false); setCreateData(EMPTY_FORM); },
            onFinish:  () => setSubmitting(false),
        });
    }

    return (
        <AppLayout title="Branches">
            {flash.success && (
                <div className="mb-4 rounded-md bg-green-50 px-4 py-2 text-sm text-green-700 border border-green-200">
                    {flash.success}
                </div>
            )}

            {isAdmin && (
                <div className="mb-4 flex justify-end">
                    <Button onClick={() => { setShowCreate(!showCreate); setEditingId(null); }}>
                        <Plus size={14} /> New Branch
                    </Button>
                </div>
            )}

            {/* Create form */}
            {showCreate && isAdmin && (
                <Card className="mb-4 border-[#185FA5]/30">
                    <CardContent className="p-5">
                        <h2 className="mb-4 text-sm font-semibold text-gray-800">New Branch</h2>
                        <EditForm
                            data={createData}
                            onChange={(key, val) => setCreateData(prev => ({ ...prev, [key]: val }))}
                            onSubmit={saveCreate}
                            onCancel={() => setShowCreate(false)}
                            submitting={submitting}
                            isCreate
                        />
                    </CardContent>
                </Card>
            )}

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {(branches ?? []).map((branch) => (
                    <Card key={branch.id} className="flex flex-col">
                        <CardContent className="p-4">
                            {editingId === branch.id ? (
                                <>
                                    <div className="mb-3 flex items-center justify-between">
                                        <p className="text-xs font-mono font-semibold text-[#185FA5]">{branch.code}</p>
                                        <span className="text-xs text-gray-400">Editing</span>
                                    </div>
                                    <EditForm
                                        data={editData}
                                        onChange={(key, val) => setEditData(prev => ({ ...prev, [key]: val }))}
                                        onSubmit={saveEdit}
                                        onCancel={cancelEdit}
                                        submitting={submitting}
                                        isCreate={false}
                                    />
                                </>
                            ) : (
                                <>
                                    <div className="mb-3 flex items-start justify-between">
                                        <div>
                                            <p className="text-xs font-mono font-semibold text-[#185FA5]">{branch.code}</p>
                                            <p className="mt-0.5 text-sm font-semibold text-gray-900 leading-tight">{branch.name}</p>
                                        </div>
                                        {branch.is_main_branch
                                            ? <Badge className="bg-[#185FA5] text-white text-[10px]">Main</Badge>
                                            : branch.is_active
                                                ? <Badge className="bg-green-100 text-green-700 text-[10px]">Active</Badge>
                                                : <Badge className="bg-gray-100 text-gray-500 text-[10px]">Inactive</Badge>
                                        }
                                    </div>
                                    <div className="space-y-1.5 text-xs text-gray-500">
                                        {branch.city && (
                                            <div className="flex items-center gap-1.5">
                                                <MapPin size={11} /> {branch.city}
                                            </div>
                                        )}
                                        {branch.contact_phone && (
                                            <div className="flex items-center gap-1.5">
                                                <Phone size={11} /> {branch.contact_phone}
                                            </div>
                                        )}
                                        {branch.contact_email && (
                                            <div className="flex items-center gap-1.5">
                                                <Mail size={11} /> {branch.contact_email}
                                            </div>
                                        )}
                                        {branch.users_count !== undefined && (
                                            <div className="flex items-center gap-1.5">
                                                <Users size={11} /> {branch.users_count} users
                                            </div>
                                        )}
                                    </div>
                                    {isAdmin && !branch.is_main_branch && (
                                        <div className="mt-3 flex justify-end">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="h-7 px-2 text-xs"
                                                onClick={() => startEdit(branch)}
                                            >
                                                <Pencil size={12} /> Edit
                                            </Button>
                                        </div>
                                    )}
                                </>
                            )}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
