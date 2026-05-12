import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import { Card, CardContent } from '../components/ui/card';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Input, Select } from '../components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../components/ui/table';
import { formatPeso, statusColor, priorityColor } from '../lib/utils';
import { CheckCircle, XCircle, Truck, Plus, Search, WrenchIcon } from 'lucide-react';

const STATUS_OPTS = ['', 'pending', 'approved', 'rejected', 'in_transit', 'delivered', 'billed'];
const PRIORITY_OPTS = ['', 'low', 'normal', 'urgent'];

export default function Orders({ orders, filters, isAdmin, formTypes, printerMaintenance = {} }) {
    const { props } = usePage();
    const user = props.auth?.user;

    const [search, setSearch]     = useState(filters.search ?? '');
    const [status, setStatus]     = useState(filters.status ?? '');
    const [priority, setPriority] = useState(filters.priority ?? '');
    const [showForm, setShowForm] = useState(false);

    // New order form state
    const defaultPrinter = !printerMaintenance.consumable ? 'consumable' : !printerMaintenance.non_consumable ? 'non_consumable' : '';
    const [items, setItems] = useState([{ form_type_id: '', printer_type: defaultPrinter, quantity: '' }]);
    const [orderPriority, setOrderPriority] = useState('normal');
    const [notes, setNotes]       = useState('');
    const [submitting, setSubmitting] = useState(false);

    function applyFilters(overrides = {}) {
        router.get('/orders', { search, status, priority, ...overrides }, { preserveState: true, replace: true });
    }

    function addItem() { setItems([...items, { form_type_id: '', printer_type: defaultPrinter, quantity: '' }]); }
    function removeItem(i) { setItems(items.filter((_, idx) => idx !== i)); }
    function updateItem(i, key, val) {
        setItems(items.map((it, idx) => idx === i ? { ...it, [key]: val } : it));
    }

    function submitOrder(e) {
        e.preventDefault();
        setSubmitting(true);
        router.post('/orders', {
            priority: orderPriority,
            notes,
            items: items.map(it => ({
                form_type_id: Number(it.form_type_id),
                printer_type: it.printer_type,
                quantity:     Number(it.quantity),
            })),
        }, {
            onFinish: () => { setSubmitting(false); setShowForm(false); setItems([{ form_type_id: '', quantity: '' }]); setNotes(''); },
        });
    }

    function action(id, verb) {
        router.patch(`/orders/${id}/${verb}`, {}, { preserveScroll: true });
    }

    const data = orders.data ?? [];

    return (
        <AppLayout title="Form Orders">
            {/* Filters row */}
            <div className="mb-4 flex flex-wrap items-center gap-2">
                <div className="relative flex-1 min-w-48">
                    <Search size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" />
                    <Input
                        placeholder="Search reference…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && applyFilters({ search })}
                        className="pl-8"
                    />
                </div>
                <Select value={status} onChange={(e) => { setStatus(e.target.value); applyFilters({ status: e.target.value }); }} className="w-36">
                    {STATUS_OPTS.map(s => <option key={s} value={s}>{s || 'All statuses'}</option>)}
                </Select>
                <Select value={priority} onChange={(e) => { setPriority(e.target.value); applyFilters({ priority: e.target.value }); }} className="w-36">
                    {PRIORITY_OPTS.map(p => <option key={p} value={p}>{p || 'All priorities'}</option>)}
                </Select>
                <div className="ml-auto">
                    {!isAdmin && (
                        <Button
                            onClick={() => setShowForm(!showForm)}
                            disabled={printerMaintenance.consumable && printerMaintenance.non_consumable}
                            title={printerMaintenance.consumable && printerMaintenance.non_consumable ? 'All printers are under maintenance' : undefined}
                        >
                            <Plus size={14} /> New Order
                        </Button>
                    )}
                </div>
            </div>

            {/* Printer maintenance banner */}
            {!isAdmin && (printerMaintenance.consumable || printerMaintenance.non_consumable) && (
                <div className={`mb-4 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm ${
                    printerMaintenance.consumable && printerMaintenance.non_consumable
                        ? 'border-red-200 bg-red-50 text-red-800'
                        : 'border-amber-200 bg-amber-50 text-amber-800'
                }`}>
                    <WrenchIcon size={16} className="mt-0.5 shrink-0" />
                    <div>
                        <p className="font-semibold">
                            {printerMaintenance.consumable && printerMaintenance.non_consumable
                                ? 'All Printers Under Maintenance — Orders Unavailable'
                                : 'Printer Maintenance Notice'}
                        </p>
                        <ul className="mt-0.5 list-disc list-inside text-xs space-y-0.5 opacity-80">
                            {printerMaintenance.consumable     && <li>Consumable printer is under maintenance — hidden from order form.</li>}
                            {printerMaintenance.non_consumable && <li>Non-consumable printer is under maintenance — hidden from order form.</li>}
                        </ul>
                    </div>
                </div>
            )}

            {/* New order form */}
            {showForm && !isAdmin && (
                <Card className="mb-4 border-[#185FA5]/30">
                    <CardContent className="p-5">
                        <h2 className="mb-4 text-sm font-semibold text-gray-800">New Form Order</h2>
                        <form onSubmit={submitOrder} className="space-y-4">
                            <div className="flex gap-3">
                                <div className="flex-1">
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Priority</label>
                                    <Select value={orderPriority} onChange={e => setOrderPriority(e.target.value)}>
                                        {PRIORITY_OPTS.filter(Boolean).map(p => <option key={p}>{p}</option>)}
                                    </Select>
                                </div>
                                <div className="flex-1">
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Notes</label>
                                    <Input value={notes} onChange={e => setNotes(e.target.value)} placeholder="Optional notes…" />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <label className="text-xs font-medium text-gray-600">Items</label>
                                {items.map((item, i) => {
                                    const ft = (formTypes ?? []).find(f => String(f.id) === String(item.form_type_id));
                                    const unitPrice = ft
                                        ? (item.printer_type === 'non_consumable' ? ft.price_non_consumable : ft.price_consumable)
                                        : null;
                                    return (
                                        <div key={i} className="flex gap-2 items-center">
                                            <Select
                                                className="flex-1"
                                                value={item.form_type_id}
                                                onChange={e => updateItem(i, 'form_type_id', e.target.value)}
                                                required
                                            >
                                                <option value="">Select form type…</option>
                                                {(formTypes ?? []).map(ft => (
                                                    <option key={ft.id} value={ft.id}>{ft.name}</option>
                                                ))}
                                            </Select>
                                            <Select
                                                className="w-44"
                                                value={item.printer_type}
                                                onChange={e => updateItem(i, 'printer_type', e.target.value)}
                                                required
                                                disabled={!defaultPrinter}
                                            >
                                                {!printerMaintenance.consumable && (
                                                    <option value="consumable">Consumable</option>
                                                )}
                                                {!printerMaintenance.non_consumable && (
                                                    <option value="non_consumable">Non-Consumable</option>
                                                )}
                                            </Select>
                                            {unitPrice !== null && (
                                                <span className="text-xs text-gray-500 whitespace-nowrap">₱{Number(unitPrice).toFixed(2)}/ea</span>
                                            )}
                                            <Input
                                                type="number"
                                                className="w-24"
                                                placeholder="Qty"
                                                value={item.quantity}
                                                onChange={e => updateItem(i, 'quantity', e.target.value)}
                                                required min={1}
                                            />
                                            {items.length > 1 && (
                                                <Button type="button" variant="ghost" size="sm" onClick={() => removeItem(i)}>✕</Button>
                                            )}
                                        </div>
                                    );
                                })}
                                {items.length < 10 && (
                                    <Button type="button" variant="outline" size="sm" onClick={addItem}>+ Add item</Button>
                                )}
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button type="button" variant="outline" onClick={() => setShowForm(false)}>Cancel</Button>
                                <Button type="submit" disabled={submitting || !defaultPrinter}>
                                    {submitting ? 'Submitting…' : 'Submit Order'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}

            {/* Orders table */}
            <Card>
                <CardContent className="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Reference</TableHead>
                                {isAdmin && <TableHead>Branch</TableHead>}
                                <TableHead>Priority</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Total</TableHead>
                                <TableHead>Date</TableHead>
                                {isAdmin && <TableHead>Actions</TableHead>}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={7} className="py-10 text-center text-gray-400">No orders found.</TableCell>
                                </TableRow>
                            )}
                            {data.map((order) => (
                                <TableRow key={order.id}>
                                    <TableCell className="font-mono text-xs">{order.reference_number}</TableCell>
                                    {isAdmin && <TableCell className="max-w-[160px] truncate font-medium">{order.branch?.name}</TableCell>}
                                    <TableCell><Badge className={priorityColor(order.priority)}>{order.priority}</Badge></TableCell>
                                    <TableCell><Badge className={statusColor(order.status)}>{order.status.replace('_', ' ')}</Badge></TableCell>
                                    <TableCell className="text-right font-semibold">{formatPeso(order.total_amount)}</TableCell>
                                    <TableCell className="text-xs text-gray-500">
                                        {new Date(order.created_at).toLocaleDateString()}
                                    </TableCell>
                                    {isAdmin && (
                                        <TableCell>
                                            <div className="flex gap-1">
                                                {order.status === 'pending' && (
                                                    <>
                                                        <Button size="sm" onClick={() => action(order.id, 'approve')}
                                                            className="h-7 px-2 text-xs">
                                                            <CheckCircle size={12} /> Approve
                                                        </Button>
                                                        <Button size="sm" variant="destructive" onClick={() => action(order.id, 'reject')}
                                                            className="h-7 px-2 text-xs">
                                                            <XCircle size={12} /> Reject
                                                        </Button>
                                                    </>
                                                )}
                                                {(order.status === 'approved' || order.status === 'in_transit') && (
                                                    <Button size="sm" variant="secondary" onClick={() => action(order.id, 'deliver')}
                                                        className="h-7 px-2 text-xs">
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

            {/* Pagination */}
            {orders.last_page > 1 && (
                <div className="mt-4 flex justify-center gap-1">
                    {Array.from({ length: orders.last_page }, (_, i) => i + 1).map((page) => (
                        <Button
                            key={page}
                            size="sm"
                            variant={page === orders.current_page ? 'default' : 'outline'}
                            onClick={() => router.get('/orders', { ...filters, page }, { preserveState: true })}
                        >
                            {page}
                        </Button>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
