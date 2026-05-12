import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import { Card, CardContent } from '../components/ui/card';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../components/ui/table';
import { formatPeso, statusColor, priorityColor } from '../lib/utils';
import { CheckCircle, XCircle, Truck, Plus, Trash2 } from 'lucide-react';

const PRIORITIES = ['low', 'normal', 'urgent'];

function CustomizationFields({ product, values, onChange }) {
    if (!product?.customizations?.length) return null;

    return (
        <div className="mt-1 space-y-1 rounded bg-gray-50 p-2">
            {product.customizations.map(field => (
                <div key={field.key} className="flex items-center gap-2">
                    <label className="min-w-16 text-[10px] font-medium text-gray-500">{field.label}</label>
                    {field.type === 'select' ? (
                        <select
                            value={values?.[field.key] ?? ''}
                            onChange={e => onChange({ ...values, [field.key]: e.target.value })}
                            className="h-6 flex-1 rounded border border-gray-200 px-1 text-xs"
                        >
                            <option value="">— select —</option>
                            {field.options.map(opt => <option key={opt} value={opt}>{opt}</option>)}
                        </select>
                    ) : (
                        <Input
                            value={values?.[field.key] ?? ''}
                            onChange={e => onChange({ ...values, [field.key]: e.target.value })}
                            className="h-6 flex-1 text-xs"
                        />
                    )}
                </div>
            ))}
        </div>
    );
}

export default function ProductOrders({ orders, products }) {
    const { props } = usePage();
    const flash     = props.flash ?? {};
    const user      = props.auth?.user;
    const isAdmin   = user?.role === 'admin';

    const [showForm, setShowForm]     = useState(false);
    const [priority, setPriority]     = useState('normal');
    const [notes, setNotes]           = useState('');
    const [neededBy, setNeededBy]     = useState('');
    const [submitting, setSubmitting] = useState(false);

    const [items, setItems] = useState([{ product_id: '', quantity: 1, customizations: {} }]);

    function addItem() {
        setItems([...items, { product_id: '', quantity: 1, customizations: {} }]);
    }

    function removeItem(i) {
        setItems(items.filter((_, idx) => idx !== i));
    }

    function updateItem(i, key, val) {
        setItems(items.map((it, idx) => idx === i ? { ...it, [key]: val } : it));
    }

    function updateCustomizations(i, val) {
        setItems(items.map((it, idx) => idx === i ? { ...it, customizations: val } : it));
    }

    function getProduct(id) {
        return products.find(p => String(p.id) === String(id));
    }

    function submitOrder(e) {
        e.preventDefault();
        setSubmitting(true);
        router.post('/product-orders', {
            priority,
            notes: notes || null,
            needed_by: neededBy || null,
            items: items.map(it => ({
                product_id:     Number(it.product_id),
                quantity:        Number(it.quantity),
                customizations:  Object.keys(it.customizations).length ? it.customizations : null,
            })),
        }, {
            onSuccess: () => { setShowForm(false); setItems([{ product_id: '', quantity: 1, customizations: {} }]); setNotes(''); setNeededBy(''); },
            onFinish:  () => setSubmitting(false),
        });
    }

    function action(id, verb, extra = {}) {
        router.patch(`/product-orders/${id}/${verb}`, extra, { preserveScroll: true });
    }

    const data = orders.data ?? [];

    return (
        <AppLayout title="Office Supply Orders">
            {flash.success && (
                <div className="mb-4 rounded-md bg-green-50 px-4 py-2 text-sm text-green-700 border border-green-200">
                    {flash.success}
                </div>
            )}

            <div className="mb-4 flex justify-end">
                <Button onClick={() => setShowForm(!showForm)}>
                    <Plus size={14} /> New Supply Order
                </Button>
            </div>

            {showForm && (
                <Card className="mb-4 border-[#185FA5]/30">
                    <CardContent className="p-5">
                        <h2 className="mb-4 text-sm font-semibold text-gray-800">New Office Supply Order</h2>
                        <form onSubmit={submitOrder} className="space-y-4">
                            <div className="grid grid-cols-3 gap-3">
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Priority</label>
                                    <select value={priority} onChange={e => setPriority(e.target.value)}
                                        className="h-9 w-full rounded border border-gray-200 px-2 text-sm capitalize">
                                        {PRIORITIES.map(p => <option key={p} value={p}>{p}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Needed By (optional)</label>
                                    <Input type="date" value={neededBy} onChange={e => setNeededBy(e.target.value)} />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Notes (optional)</label>
                                    <Input value={notes} onChange={e => setNotes(e.target.value)} placeholder="Any special instructions…" />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-medium text-gray-600">Items</span>
                                    <Button type="button" size="sm" variant="outline" className="h-7 px-2 text-xs" onClick={addItem}>
                                        <Plus size={12} /> Add Item
                                    </Button>
                                </div>
                                {items.map((item, i) => {
                                    const selectedProduct = getProduct(item.product_id);
                                    return (
                                        <div key={i} className="rounded border border-gray-100 bg-gray-50 p-2 space-y-1">
                                            <div className="flex gap-2">
                                                <select
                                                    value={item.product_id}
                                                    onChange={e => updateItem(i, 'product_id', e.target.value)}
                                                    className="h-8 flex-1 rounded border border-gray-200 px-2 text-sm"
                                                    required
                                                >
                                                    <option value="">— select product —</option>
                                                    {products.map(p => (
                                                        <option key={p.id} value={p.id}>
                                                            [{p.code}] {p.name} — {formatPeso(p.unit_price)} / {p.unit_label}
                                                        </option>
                                                    ))}
                                                </select>
                                                <Input
                                                    type="number"
                                                    value={item.quantity}
                                                    onChange={e => updateItem(i, 'quantity', e.target.value)}
                                                    className="h-8 w-24 text-sm"
                                                    min={1}
                                                    placeholder="Qty"
                                                    required
                                                />
                                                {items.length > 1 && (
                                                    <Button type="button" size="sm" variant="outline"
                                                        className="h-8 px-2 text-xs text-red-500"
                                                        onClick={() => removeItem(i)}>
                                                        <Trash2 size={12} />
                                                    </Button>
                                                )}
                                            </div>
                                            <CustomizationFields
                                                product={selectedProduct}
                                                values={item.customizations}
                                                onChange={val => updateCustomizations(i, val)}
                                            />
                                            {selectedProduct && (
                                                <p className="text-[10px] text-gray-400">
                                                    Line total: {formatPeso(selectedProduct.unit_price * (item.quantity || 0))}
                                                </p>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button type="button" variant="outline" onClick={() => setShowForm(false)}>Cancel</Button>
                                <Button type="submit" disabled={submitting}>Submit Order</Button>
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
                                <TableHead>Reference</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead>Requested By</TableHead>
                                <TableHead>Priority</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Items</TableHead>
                                <TableHead>Total</TableHead>
                                {isAdmin && <TableHead>Actions</TableHead>}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={isAdmin ? 8 : 7} className="py-10 text-center text-gray-400">
                                        No supply orders found.
                                    </TableCell>
                                </TableRow>
                            )}
                            {data.map(order => (
                                <TableRow key={order.id}>
                                    <TableCell className="font-mono text-xs font-semibold">{order.reference_number}</TableCell>
                                    <TableCell className="text-xs">{order.branch?.name ?? '—'}</TableCell>
                                    <TableCell className="text-xs">{order.requester?.name ?? '—'}</TableCell>
                                    <TableCell>
                                        <Badge className={`capitalize ${priorityColor(order.priority)}`}>{order.priority}</Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge className={`capitalize ${statusColor(order.status)}`}>{order.status}</Badge>
                                    </TableCell>
                                    <TableCell className="text-xs text-gray-500">
                                        {order.items?.length ?? 0} item{(order.items?.length ?? 0) !== 1 ? 's' : ''}
                                    </TableCell>
                                    <TableCell className="font-semibold text-green-700">{formatPeso(order.total_amount)}</TableCell>
                                    {isAdmin && (
                                        <TableCell>
                                            <div className="flex gap-1">
                                                {order.status === 'pending' && (
                                                    <>
                                                        <Button size="sm" className="h-7 px-2 text-xs bg-green-600 hover:bg-green-700"
                                                            onClick={() => action(order.id, 'approve')}>
                                                            <CheckCircle size={12} /> Approve
                                                        </Button>
                                                        <Button size="sm" variant="outline" className="h-7 px-2 text-xs text-red-500"
                                                            onClick={() => {
                                                                const reason = prompt('Rejection reason (optional):') ?? '';
                                                                action(order.id, 'reject', { rejection_reason: reason });
                                                            }}>
                                                            <XCircle size={12} /> Reject
                                                        </Button>
                                                    </>
                                                )}
                                                {order.status === 'approved' && (
                                                    <Button size="sm" className="h-7 px-2 text-xs bg-blue-600 hover:bg-blue-700"
                                                        onClick={() => action(order.id, 'deliver')}>
                                                        <Truck size={12} /> Deliver
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
