import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import { Card, CardContent } from '../components/ui/card';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../components/ui/table';
import { formatPeso } from '../lib/utils';
import { Pencil, Plus, X, Check } from 'lucide-react';

const EMPTY_FORM = {
    code: '', name: '', description: '',
    unit_price: '',
    price_consumable: '', price_non_consumable: '',
    unit_label: 'piece',
    minimum_order: 1, maximum_order: '', is_active: true,
};

function PriceInput({ value, onChange, className = 'h-7 w-24 text-xs' }) {
    const safe = (value !== null && value !== undefined && value !== '' && !Number.isNaN(Number(value)))
        ? value
        : '';
    return (
        <Input
            type="number"
            value={safe}
            onChange={e => onChange(e.target.value)}
            className={className}
            min={0} step="0.01" required
        />
    );
}

export default function FormTypes({ formTypes }) {
    const { props } = usePage();
    const flash = props.flash ?? {};

    const [editingId, setEditingId]   = useState(null);
    const [editData, setEditData]     = useState({});
    const [showCreate, setShowCreate] = useState(false);
    const [createData, setCreateData] = useState(EMPTY_FORM);
    const [submitting, setSubmitting] = useState(false);

    function startEdit(ft) {
        setEditingId(ft.id);
        setEditData({
            ...ft,
            price_consumable:     ft.price_consumable     ?? '',
            price_non_consumable: ft.price_non_consumable ?? '',
            unit_price:           ft.unit_price           ?? '',
        });
        setShowCreate(false);
    }

    function cancelEdit() { setEditingId(null); setEditData({}); }

    function saveEdit(e) {
        e.preventDefault();
        setSubmitting(true);
        router.patch(`/form-types/${editingId}`, editData, {
            onSuccess: () => { setEditingId(null); setEditData({}); },
            onFinish:  () => setSubmitting(false),
        });
    }

    function saveCreate(e) {
        e.preventDefault();
        setSubmitting(true);
        router.post('/form-types', createData, {
            onSuccess: () => { setShowCreate(false); setCreateData(EMPTY_FORM); },
            onFinish:  () => setSubmitting(false),
        });
    }

    const setEdit   = (k, v) => setEditData(prev => ({ ...prev, [k]: v }));
    const setCreate = (k, v) => setCreateData(prev => ({ ...prev, [k]: v }));

    return (
        <AppLayout title="Form Types & Prices">
            {flash.success && (
                <div className="mb-4 rounded-md bg-green-50 px-4 py-2 text-sm text-green-700 border border-green-200">
                    {flash.success}
                </div>
            )}

            <div className="mb-4 flex justify-end">
                <Button onClick={() => { setShowCreate(!showCreate); setEditingId(null); }}>
                    <Plus size={14} /> New Form Type
                </Button>
            </div>

            {/* Create form */}
            {showCreate && (
                <Card className="mb-4 border-[#185FA5]/30">
                    <CardContent className="p-5">
                        <h2 className="mb-4 text-sm font-semibold text-gray-800">New Form Type</h2>
                        <form onSubmit={saveCreate} className="space-y-3">
                            <div className="grid grid-cols-3 gap-3">
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Code</label>
                                    <Input value={createData.code} onChange={e => setCreate('code', e.target.value)}
                                        placeholder="e.g. FORM-001" maxLength={20} required />
                                </div>
                                <div className="col-span-2">
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Name</label>
                                    <Input value={createData.name} onChange={e => setCreate('name', e.target.value)}
                                        placeholder="Form name" required />
                                </div>

                                {/* Pricing */}
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">
                                        Consumable Price (₱)
                                    </label>
                                    <Input type="number" value={createData.price_consumable}
                                        onChange={e => setCreate('price_consumable', e.target.value)}
                                        placeholder="0.00" min={0} step="0.01" required />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">
                                        Non-Consumable Price (₱)
                                    </label>
                                    <Input type="number" value={createData.price_non_consumable}
                                        onChange={e => setCreate('price_non_consumable', e.target.value)}
                                        placeholder="0.00" min={0} step="0.01" required />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Unit Label</label>
                                    <Input value={createData.unit_label}
                                        onChange={e => setCreate('unit_label', e.target.value)}
                                        placeholder="piece" required />
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Min Order</label>
                                    <Input type="number" value={createData.minimum_order}
                                        onChange={e => setCreate('minimum_order', e.target.value)} min={1} required />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Max Order</label>
                                    <Input type="number" value={createData.maximum_order}
                                        onChange={e => setCreate('maximum_order', e.target.value)}
                                        min={1} placeholder="No limit" />
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-600">Description</label>
                                <Input value={createData.description}
                                    onChange={e => setCreate('description', e.target.value)}
                                    placeholder="Optional description…" />
                            </div>
                            <div className="flex items-center gap-2">
                                <input type="checkbox" id="create-active" checked={createData.is_active}
                                    onChange={e => setCreate('is_active', e.target.checked)}
                                    className="h-4 w-4 rounded border-gray-300" />
                                <label htmlFor="create-active" className="text-xs font-medium text-gray-600">Active</label>
                            </div>
                            <div className="flex justify-end gap-2">
                                <Button type="button" variant="outline" onClick={() => setShowCreate(false)}>Cancel</Button>
                                <Button type="submit" disabled={submitting}>Create</Button>
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
                                <TableHead className="text-center">Consumable Price</TableHead>
                                <TableHead className="text-center">Non-Consumable Price</TableHead>
                                <TableHead>Label</TableHead>
                                <TableHead>Min</TableHead>
                                <TableHead>Max</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {formTypes.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={9} className="py-10 text-center text-gray-400">
                                        No form types found.
                                    </TableCell>
                                </TableRow>
                            )}
                            {formTypes.map((ft) =>
                                editingId === ft.id ? (
                                    <TableRow key={ft.id} className="bg-blue-50">
                                        <TableCell className="font-mono text-xs text-gray-500">{ft.code}</TableCell>
                                        <TableCell>
                                            <Input value={editData.name} onChange={e => setEdit('name', e.target.value)}
                                                className="h-7 text-xs" required />
                                        </TableCell>
                                        <TableCell>
                                            <PriceInput value={editData.price_consumable}
                                                onChange={v => setEdit('price_consumable', v)} />
                                        </TableCell>
                                        <TableCell>
                                            <PriceInput value={editData.price_non_consumable}
                                                onChange={v => setEdit('price_non_consumable', v)} />
                                        </TableCell>
                                        <TableCell>
                                            <Input value={editData.unit_label} onChange={e => setEdit('unit_label', e.target.value)}
                                                className="h-7 w-20 text-xs" required />
                                        </TableCell>
                                        <TableCell>
                                            <Input type="number" value={editData.minimum_order}
                                                onChange={e => setEdit('minimum_order', e.target.value)}
                                                className="h-7 w-14 text-xs" min={1} required />
                                        </TableCell>
                                        <TableCell>
                                            <Input type="number" value={editData.maximum_order ?? ''}
                                                onChange={e => setEdit('maximum_order', e.target.value || null)}
                                                className="h-7 w-14 text-xs" min={1} placeholder="—" />
                                        </TableCell>
                                        <TableCell>
                                            <select value={editData.is_active ? '1' : '0'}
                                                onChange={e => setEdit('is_active', e.target.value === '1')}
                                                className="h-7 rounded border border-gray-200 px-1 text-xs">
                                                <option value="1">Active</option>
                                                <option value="0">Inactive</option>
                                            </select>
                                        </TableCell>
                                        <TableCell>
                                            <form onSubmit={saveEdit} className="flex gap-1">
                                                <Button type="submit" size="sm" className="h-7 px-2 text-xs" disabled={submitting}>
                                                    <Check size={12} /> Save
                                                </Button>
                                                <Button type="button" size="sm" variant="outline" className="h-7 px-2 text-xs" onClick={cancelEdit}>
                                                    <X size={12} />
                                                </Button>
                                            </form>
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    <TableRow key={ft.id}>
                                        <TableCell className="font-mono text-xs">{ft.code}</TableCell>
                                        <TableCell className="font-medium">{ft.name}</TableCell>
                                        <TableCell className="text-center">
                                            <div className="text-xs text-gray-400 mb-0.5">Consumable</div>
                                            <div className="font-semibold text-green-700">{formatPeso(ft.price_consumable)}</div>
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <div className="text-xs text-gray-400 mb-0.5">Non-Consumable</div>
                                            <div className="font-semibold text-blue-700">{formatPeso(ft.price_non_consumable)}</div>
                                        </TableCell>
                                        <TableCell className="text-xs text-gray-500">{ft.unit_label}</TableCell>
                                        <TableCell className="text-xs">{ft.minimum_order}</TableCell>
                                        <TableCell className="text-xs">{ft.maximum_order ?? '—'}</TableCell>
                                        <TableCell>
                                            <Badge className={ft.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}>
                                                {ft.is_active ? 'Active' : 'Inactive'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Button size="sm" variant="outline" className="h-7 px-2 text-xs" onClick={() => startEdit(ft)}>
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
