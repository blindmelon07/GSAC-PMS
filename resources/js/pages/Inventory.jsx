import { useState } from 'react';
import { router, usePage, Link } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import { Card, CardContent } from '../components/ui/card';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../components/ui/table';
import { formatPeso } from '../lib/utils';
import { Plus, Settings2, History, AlertTriangle } from 'lucide-react';

const TYPE_LABELS = {
    restock:           { label: 'Restock',      color: 'bg-green-100 text-green-700' },
    adjustment:        { label: 'Adjustment',   color: 'bg-blue-100 text-blue-700' },
    order_fulfillment: { label: 'Fulfillment',  color: 'bg-purple-100 text-purple-700' },
};

function RestockModal({ inventory, onClose }) {
    const [quantity, setQuantity] = useState('');
    const [notes, setNotes]       = useState('');
    const [saving, setSaving]     = useState(false);

    function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        router.post(`/inventory/${inventory.id}/restock`, { quantity: Number(quantity), notes }, {
            onSuccess: () => onClose(),
            onFinish:  () => setSaving(false),
        });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div className="w-full max-w-sm rounded-xl bg-white shadow-xl">
                <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h2 className="text-sm font-semibold text-gray-800">
                        Restock — {inventory.product?.name}
                    </h2>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
                </div>
                <form onSubmit={handleSubmit} className="space-y-4 p-5">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-gray-600">
                            Quantity to Add (current: {inventory.quantity_on_hand})
                        </label>
                        <Input type="number" value={quantity} onChange={e => setQuantity(e.target.value)}
                            min={1} required autoFocus placeholder="e.g. 50" />
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-gray-600">Notes (optional)</label>
                        <Input value={notes} onChange={e => setNotes(e.target.value)} placeholder="e.g. Received from supplier" />
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={saving}>Add Stock</Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function AdjustModal({ inventory, onClose }) {
    const [qty, setQty]           = useState(inventory.quantity_on_hand);
    const [reorderLevel, setRL]   = useState(inventory.reorder_level);
    const [reorderQty, setRQ]     = useState(inventory.reorder_quantity);
    const [notes, setNotes]       = useState('');
    const [saving, setSaving]     = useState(false);

    function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        router.patch(`/inventory/${inventory.id}/adjust`, {
            quantity_on_hand: Number(qty),
            reorder_level:    Number(reorderLevel),
            reorder_quantity: Number(reorderQty),
            notes,
        }, {
            onSuccess: () => onClose(),
            onFinish:  () => setSaving(false),
        });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div className="w-full max-w-sm rounded-xl bg-white shadow-xl">
                <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h2 className="text-sm font-semibold text-gray-800">
                        Adjust — {inventory.product?.name}
                    </h2>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
                </div>
                <form onSubmit={handleSubmit} className="space-y-3 p-5">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-gray-600">Quantity on Hand</label>
                        <Input type="number" value={qty} onChange={e => setQty(e.target.value)} min={0} required />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-600">Reorder Level</label>
                            <Input type="number" value={reorderLevel} onChange={e => setRL(e.target.value)} min={0} />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-600">Reorder Qty</label>
                            <Input type="number" value={reorderQty} onChange={e => setRQ(e.target.value)} min={0} />
                        </div>
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-gray-600">Notes (optional)</label>
                        <Input value={notes} onChange={e => setNotes(e.target.value)} placeholder="Reason for adjustment…" />
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={saving}>Save Changes</Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function Inventory({ inventories, lowStockCount }) {
    const { props } = usePage();
    const flash = props.flash ?? {};

    const [restockTarget, setRestockTarget] = useState(null);
    const [adjustTarget, setAdjustTarget]   = useState(null);

    const categoryColor = {
        paper:   'bg-blue-50 text-blue-700',
        writing: 'bg-purple-50 text-purple-700',
        filing:  'bg-amber-50 text-amber-700',
        general: 'bg-gray-100 text-gray-600',
    };

    return (
        <AppLayout title="Inventory">
            {restockTarget && <RestockModal inventory={restockTarget} onClose={() => setRestockTarget(null)} />}
            {adjustTarget  && <AdjustModal  inventory={adjustTarget}  onClose={() => setAdjustTarget(null)} />}

            {flash.success && (
                <div className="mb-4 rounded-md bg-green-50 px-4 py-2 text-sm text-green-700 border border-green-200">
                    {flash.success}
                </div>
            )}

            {lowStockCount > 0 && (
                <div className="mb-4 flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-700">
                    <AlertTriangle size={15} />
                    {lowStockCount} product{lowStockCount > 1 ? 's are' : ' is'} below reorder level.
                </div>
            )}

            <Card>
                <CardContent className="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Product</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead>Unit Price</TableHead>
                                <TableHead className="text-center">On Hand</TableHead>
                                <TableHead className="text-center">Reorder Level</TableHead>
                                <TableHead className="text-center">Reorder Qty</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {inventories.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={8} className="py-10 text-center text-gray-400">
                                        No inventory records found.
                                    </TableCell>
                                </TableRow>
                            )}
                            {inventories.map(inv => {
                                const low = inv.reorder_level > 0 && inv.quantity_on_hand <= inv.reorder_level;
                                return (
                                    <TableRow key={inv.id} className={low ? 'bg-amber-50/50' : ''}>
                                        <TableCell>
                                            <div className="font-medium text-sm">{inv.product?.name}</div>
                                            <div className="text-[11px] font-mono text-gray-400">{inv.product?.code}</div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge className={`capitalize ${categoryColor[inv.product?.category] ?? ''}`}>
                                                {inv.product?.category}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-sm font-semibold text-green-700">
                                            {formatPeso(inv.product?.unit_price)}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <span className={`text-base font-bold ${low ? 'text-amber-600' : 'text-gray-800'}`}>
                                                {inv.quantity_on_hand}
                                            </span>
                                            <span className="ml-1 text-xs text-gray-400">{inv.product?.unit_label}</span>
                                        </TableCell>
                                        <TableCell className="text-center text-sm text-gray-500">
                                            {inv.reorder_level || '—'}
                                        </TableCell>
                                        <TableCell className="text-center text-sm text-gray-500">
                                            {inv.reorder_quantity || '—'}
                                        </TableCell>
                                        <TableCell>
                                            {low
                                                ? <Badge className="bg-amber-100 text-amber-700 gap-1"><AlertTriangle size={10} /> Low Stock</Badge>
                                                : inv.quantity_on_hand === 0
                                                    ? <Badge className="bg-red-100 text-red-700">Out of Stock</Badge>
                                                    : <Badge className="bg-green-100 text-green-700">In Stock</Badge>
                                            }
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex gap-1">
                                                <Button size="sm" className="h-7 px-2 text-xs"
                                                    onClick={() => setRestockTarget(inv)}>
                                                    <Plus size={12} /> Restock
                                                </Button>
                                                <Button size="sm" variant="outline" className="h-7 px-2 text-xs"
                                                    onClick={() => setAdjustTarget(inv)}>
                                                    <Settings2 size={12} /> Adjust
                                                </Button>
                                                <Link href={`/inventory/${inv.id}/movements`}>
                                                    <Button size="sm" variant="outline" className="h-7 px-2 text-xs">
                                                        <History size={12} /> Log
                                                    </Button>
                                                </Link>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
