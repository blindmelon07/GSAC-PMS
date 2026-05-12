import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import { Card, CardContent } from '../components/ui/card';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../components/ui/table';
import { formatPeso } from '../lib/utils';
import { Pencil, Plus, X, Check, Trash2 } from 'lucide-react';

const CATEGORIES = ['paper', 'writing', 'filing', 'general'];

const EMPTY_FORM = {
    code: '', name: '', description: '',
    category: 'general',
    unit_price: '',
    unit_label: 'piece',
    minimum_order: 1, maximum_order: '',
    customizations: [],
    is_active: true,
};

const EMPTY_CUSTOMIZATION = { key: '', label: '', type: 'select', options: '' };

function CustomizationEditor({ customizations = [], onChange }) {
    function addField() {
        onChange([...customizations, { ...EMPTY_CUSTOMIZATION }]);
    }

    function removeField(i) {
        onChange(customizations.filter((_, idx) => idx !== i));
    }

    function updateField(i, key, val) {
        onChange(customizations.map((c, idx) => idx === i ? { ...c, [key]: val } : c));
    }

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-gray-600">Customization Fields</span>
                <Button type="button" size="sm" variant="outline" className="h-6 px-2 text-xs" onClick={addField}>
                    <Plus size={11} /> Add Field
                </Button>
            </div>
            {customizations.length === 0 && (
                <p className="text-xs text-gray-400 italic">No customization fields. Click "Add Field" to allow buyers to choose options.</p>
            )}
            {customizations.map((c, i) => (
                <div key={i} className="grid grid-cols-4 gap-2 rounded border border-gray-100 bg-gray-50 p-2">
                    <div>
                        <label className="mb-0.5 block text-[10px] text-gray-500">Key (internal)</label>
                        <Input value={c.key} onChange={e => updateField(i, 'key', e.target.value)}
                            placeholder="e.g. color" className="h-6 text-xs" />
                    </div>
                    <div>
                        <label className="mb-0.5 block text-[10px] text-gray-500">Label (shown to user)</label>
                        <Input value={c.label} onChange={e => updateField(i, 'label', e.target.value)}
                            placeholder="e.g. Color" className="h-6 text-xs" />
                    </div>
                    <div>
                        <label className="mb-0.5 block text-[10px] text-gray-500">Options (comma-separated)</label>
                        <Input value={Array.isArray(c.options) ? c.options.join(', ') : c.options}
                            onChange={e => updateField(i, 'options', e.target.value)}
                            placeholder="e.g. blue, red, black" className="h-6 text-xs" />
                    </div>
                    <div className="flex items-end">
                        <Button type="button" size="sm" variant="outline" className="h-6 px-2 text-xs text-red-500"
                            onClick={() => removeField(i)}>
                            <Trash2 size={11} />
                        </Button>
                    </div>
                </div>
            ))}
        </div>
    );
}

function normalizeCustomizations(raw) {
    return (raw ?? []).map(c => ({
        ...c,
        type: 'select',
        options: Array.isArray(c.options)
            ? c.options
            : String(c.options).split(',').map(s => s.trim()).filter(Boolean),
    }));
}

export default function Products({ products }) {
    const { props } = usePage();
    const flash = props.flash ?? {};

    const [editingId, setEditingId]   = useState(null);
    const [editData, setEditData]     = useState({});
    const [showCreate, setShowCreate] = useState(false);
    const [createData, setCreateData] = useState(EMPTY_FORM);
    const [submitting, setSubmitting] = useState(false);

    function startEdit(p) {
        setEditingId(p.id);
        setEditData({ ...p, maximum_order: p.maximum_order ?? '', customizations: p.customizations ?? [] });
        setShowCreate(false);
    }

    function cancelEdit() { setEditingId(null); setEditData({}); }

    function saveEdit(e) {
        e.preventDefault();
        setSubmitting(true);
        router.patch(`/products/${editingId}`, {
            ...editData,
            customizations: normalizeCustomizations(editData.customizations),
        }, {
            onSuccess: () => { setEditingId(null); setEditData({}); },
            onFinish:  () => setSubmitting(false),
        });
    }

    function saveCreate(e) {
        e.preventDefault();
        setSubmitting(true);
        router.post('/products', {
            ...createData,
            customizations: normalizeCustomizations(createData.customizations),
        }, {
            onSuccess: () => { setShowCreate(false); setCreateData(EMPTY_FORM); },
            onFinish:  () => setSubmitting(false),
        });
    }

    const setEdit   = (k, v) => setEditData(prev => ({ ...prev, [k]: v }));
    const setCreate = (k, v) => setCreateData(prev => ({ ...prev, [k]: v }));

    const categoryColor = {
        paper:   'bg-blue-50 text-blue-700',
        writing: 'bg-purple-50 text-purple-700',
        filing:  'bg-amber-50 text-amber-700',
        general: 'bg-gray-100 text-gray-600',
    };

    return (
        <AppLayout title="Office Supply Products">
            {flash.success && (
                <div className="mb-4 rounded-md bg-green-50 px-4 py-2 text-sm text-green-700 border border-green-200">
                    {flash.success}
                </div>
            )}

            <div className="mb-4 flex justify-end">
                <Button onClick={() => { setShowCreate(!showCreate); setEditingId(null); }}>
                    <Plus size={14} /> New Product
                </Button>
            </div>

            {showCreate && (
                <Card className="mb-4 border-[#185FA5]/30">
                    <CardContent className="p-5">
                        <h2 className="mb-4 text-sm font-semibold text-gray-800">New Office Supply Product</h2>
                        <form onSubmit={saveCreate} className="space-y-3">
                            <div className="grid grid-cols-3 gap-3">
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Code</label>
                                    <Input value={createData.code} onChange={e => setCreate('code', e.target.value)}
                                        placeholder="e.g. PAP-001" maxLength={20} required />
                                </div>
                                <div className="col-span-2">
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Name</label>
                                    <Input value={createData.name} onChange={e => setCreate('name', e.target.value)}
                                        placeholder="Product name" required />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Category</label>
                                    <select value={createData.category} onChange={e => setCreate('category', e.target.value)}
                                        className="h-9 w-full rounded border border-gray-200 px-2 text-sm capitalize">
                                        {CATEGORIES.map(c => <option key={c} value={c}>{c}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Unit Price (₱)</label>
                                    <Input type="number" value={createData.unit_price}
                                        onChange={e => setCreate('unit_price', e.target.value)}
                                        placeholder="0.00" min={0} step="0.01" required />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Unit Label</label>
                                    <Input value={createData.unit_label} onChange={e => setCreate('unit_label', e.target.value)}
                                        placeholder="piece, ream, box…" required />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Min Order</label>
                                    <Input type="number" value={createData.minimum_order}
                                        onChange={e => setCreate('minimum_order', e.target.value)} min={1} required />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Max Order</label>
                                    <Input type="number" value={createData.maximum_order}
                                        onChange={e => setCreate('maximum_order', e.target.value)} min={1} placeholder="No limit" />
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-600">Description</label>
                                <Input value={createData.description} onChange={e => setCreate('description', e.target.value)}
                                    placeholder="Optional description…" />
                            </div>
                            <CustomizationEditor
                                customizations={createData.customizations}
                                onChange={v => setCreate('customizations', v)}
                            />
                            <div className="flex items-center gap-2">
                                <input type="checkbox" id="create-active" checked={createData.is_active}
                                    onChange={e => setCreate('is_active', e.target.checked)}
                                    className="h-4 w-4 rounded border-gray-300" />
                                <label htmlFor="create-active" className="text-xs font-medium text-gray-600">Active</label>
                            </div>
                            <div className="flex justify-end gap-2">
                                <Button type="button" variant="outline" onClick={() => setShowCreate(false)}>Cancel</Button>
                                <Button type="submit" disabled={submitting}>Create Product</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardContent className="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Code</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead>Price</TableHead>
                                <TableHead>Label</TableHead>
                                <TableHead>Min</TableHead>
                                <TableHead>Customizable</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {products.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={9} className="py-10 text-center text-gray-400">
                                        No products found.
                                    </TableCell>
                                </TableRow>
                            )}
                            {products.map((p) =>
                                editingId === p.id ? (
                                    <TableRow key={p.id} className="bg-blue-50">
                                        <TableCell className="font-mono text-xs text-gray-500">{p.code}</TableCell>
                                        <TableCell colSpan={7}>
                                            <form onSubmit={saveEdit} className="space-y-2 py-1">
                                                <div className="grid grid-cols-4 gap-2">
                                                    <Input value={editData.name} onChange={e => setEdit('name', e.target.value)}
                                                        placeholder="Name" className="h-7 text-xs" required />
                                                    <select value={editData.category} onChange={e => setEdit('category', e.target.value)}
                                                        className="h-7 rounded border border-gray-200 px-1 text-xs capitalize">
                                                        {CATEGORIES.map(c => <option key={c} value={c}>{c}</option>)}
                                                    </select>
                                                    <Input type="number" value={editData.unit_price}
                                                        onChange={e => setEdit('unit_price', e.target.value)}
                                                        placeholder="Price" className="h-7 text-xs" min={0} step="0.01" required />
                                                    <Input value={editData.unit_label} onChange={e => setEdit('unit_label', e.target.value)}
                                                        placeholder="Label" className="h-7 text-xs" required />
                                                </div>
                                                <CustomizationEditor
                                                    customizations={editData.customizations ?? []}
                                                    onChange={v => setEdit('customizations', v)}
                                                />
                                                <div className="flex items-center gap-3">
                                                    <select value={editData.is_active ? '1' : '0'}
                                                        onChange={e => setEdit('is_active', e.target.value === '1')}
                                                        className="h-7 rounded border border-gray-200 px-1 text-xs">
                                                        <option value="1">Active</option>
                                                        <option value="0">Inactive</option>
                                                    </select>
                                                    <div className="flex gap-1">
                                                        <Button type="submit" size="sm" className="h-7 px-2 text-xs" disabled={submitting}>
                                                            <Check size={12} /> Save
                                                        </Button>
                                                        <Button type="button" size="sm" variant="outline" className="h-7 px-2 text-xs" onClick={cancelEdit}>
                                                            <X size={12} />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </form>
                                        </TableCell>
                                        <TableCell />
                                    </TableRow>
                                ) : (
                                    <TableRow key={p.id} className={p.deleted_at ? 'opacity-50' : ''}>
                                        <TableCell className="font-mono text-xs">{p.code}</TableCell>
                                        <TableCell className="font-medium">{p.name}</TableCell>
                                        <TableCell>
                                            <Badge className={`capitalize ${categoryColor[p.category] ?? ''}`}>{p.category}</Badge>
                                        </TableCell>
                                        <TableCell className="font-semibold text-green-700">{formatPeso(p.unit_price)}</TableCell>
                                        <TableCell className="text-xs text-gray-500">{p.unit_label}</TableCell>
                                        <TableCell className="text-xs">{p.minimum_order}</TableCell>
                                        <TableCell>
                                            {p.customizations?.length > 0
                                                ? <Badge className="bg-purple-50 text-purple-700">{p.customizations.length} field{p.customizations.length > 1 ? 's' : ''}</Badge>
                                                : <span className="text-xs text-gray-400">None</span>
                                            }
                                        </TableCell>
                                        <TableCell>
                                            <Badge className={p.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}>
                                                {p.is_active ? 'Active' : 'Inactive'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Button size="sm" variant="outline" className="h-7 px-2 text-xs" onClick={() => startEdit(p)}>
                                                <Pencil size={12} /> Edit
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                )
                            )}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
