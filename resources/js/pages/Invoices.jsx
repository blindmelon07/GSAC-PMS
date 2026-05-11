import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Input, Select } from '../components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../components/ui/table';
import { formatPeso, statusColor } from '../lib/utils';
import { Download, Eye, CheckCircle, PlusCircle } from 'lucide-react';

const STATUS_OPTS = ['', 'draft', 'sent', 'paid', 'overdue'];

export default function Invoices({ invoices, billableSummary, isAdmin, branches }) {
    const { props } = usePage();
    const flash = props.flash ?? {};
    const [filterStatus, setFilterStatus] = useState('');
    const [showGenerate, setShowGenerate] = useState(false);

    // Generate form state
    const [branchId, setBranchId]       = useState('');
    const [periodStart, setPeriodStart] = useState('');
    const [periodEnd, setPeriodEnd]     = useState('');
    const [dueDays, setDueDays]         = useState('30');
    const [notes, setNotes]             = useState('');
    const [generating, setGenerating]   = useState(false);

    function submitGenerate(e) {
        e.preventDefault();
        setGenerating(true);
        router.post('/invoices/generate', {
            branch_id: Number(branchId),
            period_start: periodStart,
            period_end: periodEnd,
            due_days: Number(dueDays),
            notes,
        }, { onFinish: () => { setGenerating(false); setShowGenerate(false); } });
    }

    const data = invoices.data ?? [];

    return (
        <AppLayout title="Invoices">
            {/* Flash messages */}
            {flash.success && (
                <div className="mb-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    <span className="font-medium">✓</span> {flash.success}
                </div>
            )}
            {flash.error && (
                <div className="mb-4 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <span className="font-medium">✕</span> {flash.error}
                </div>
            )}

            {/* Billable summary — admin only */}
            {isAdmin && (billableSummary ?? []).length > 0 && (
                <Card className="mb-4 border-amber-200 bg-amber-50">
                    <CardHeader><CardTitle className="text-amber-700 text-sm">Branches with Unbilled Delivered Orders</CardTitle></CardHeader>
                    <CardContent className="p-4 pt-0">
                        <div className="flex flex-wrap gap-2">
                            {(billableSummary ?? []).map((b) => (
                                <div key={b.id} className="rounded-lg border border-amber-200 bg-white px-3 py-2 text-xs">
                                    <p className="font-semibold text-gray-800">{b.name}</p>
                                    <p className="text-gray-500">{b.delivered_orders_count} orders · {formatPeso(b.billable_subtotal)}</p>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Header row */}
            <div className="mb-4 flex items-center gap-2">
                <Select value={filterStatus} onChange={(e) => {
                    setFilterStatus(e.target.value);
                    router.get('/invoices', { status: e.target.value }, { preserveState: true, replace: true });
                }} className="w-40">
                    {STATUS_OPTS.map(s => <option key={s} value={s}>{s || 'All statuses'}</option>)}
                </Select>
                {isAdmin && (
                    <Button className="ml-auto" onClick={() => setShowGenerate(!showGenerate)}>
                        <PlusCircle size={14} /> Generate Invoice
                    </Button>
                )}
            </div>

            {/* Generate form */}
            {showGenerate && isAdmin && (
                <Card className="mb-4 border-[#185FA5]/30">
                    <CardContent className="p-5">
                        <h2 className="mb-4 text-sm font-semibold">Generate Invoice</h2>
                        <form onSubmit={submitGenerate} className="grid grid-cols-2 gap-3 lg:grid-cols-3">
                            <div className="col-span-2 lg:col-span-1">
                                <label className="mb-1 block text-xs font-medium text-gray-600">Branch</label>
                                <Select value={branchId} onChange={e => setBranchId(e.target.value)} required>
                                    <option value="">Select branch…</option>
                                    {(branches ?? []).filter(b => !b.is_main_branch).map(b => (
                                        <option key={b.id} value={b.id}>{b.name}</option>
                                    ))}
                                </Select>
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-600">Period Start</label>
                                <Input type="date" value={periodStart} onChange={e => setPeriodStart(e.target.value)} required />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-600">Period End</label>
                                <Input type="date" value={periodEnd} onChange={e => setPeriodEnd(e.target.value)} required />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-600">Due Days</label>
                                <Input type="number" value={dueDays} onChange={e => setDueDays(e.target.value)} min={1} max={90} />
                            </div>
                            <div className="col-span-2 lg:col-span-2">
                                <label className="mb-1 block text-xs font-medium text-gray-600">Notes</label>
                                <Input value={notes} onChange={e => setNotes(e.target.value)} placeholder="Optional…" />
                            </div>
                            <div className="col-span-2 flex justify-end gap-2 lg:col-span-3">
                                <Button type="button" variant="outline" onClick={() => setShowGenerate(false)}>Cancel</Button>
                                <Button type="submit" disabled={generating}>
                                    {generating ? 'Generating…' : 'Generate'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}

            {/* Table */}
            <Card>
                <CardContent className="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Invoice #</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead>Period</TableHead>
                                <TableHead>Due Date</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Total</TableHead>
                                <TableHead>Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={7} className="py-10 text-center text-gray-400">No invoices found.</TableCell>
                                </TableRow>
                            )}
                            {data.map((inv) => (
                                <TableRow key={inv.id}>
                                    <TableCell className="font-mono text-xs">{inv.invoice_number}</TableCell>
                                    <TableCell className="font-medium">{inv.branch?.name}</TableCell>
                                    <TableCell className="text-xs text-gray-500">{inv.billing_period}</TableCell>
                                    <TableCell className="text-xs">{inv.due_date}</TableCell>
                                    <TableCell><Badge className={statusColor(inv.status)}>{inv.status}</Badge></TableCell>
                                    <TableCell className="text-right font-semibold">{formatPeso(inv.total_amount)}</TableCell>
                                    <TableCell>
                                        <div className="flex gap-1">
                                            <a href={`/invoices/${inv.id}/preview`} target="_blank" rel="noreferrer">
                                                <Button size="sm" variant="outline" className="h-7 px-2 text-xs">
                                                    <Eye size={12} /> View
                                                </Button>
                                            </a>
                                            <a href={`/invoices/${inv.id}/download`}>
                                                <Button size="sm" variant="outline" className="h-7 px-2 text-xs">
                                                    <Download size={12} /> PDF
                                                </Button>
                                            </a>
                                            {isAdmin && inv.status !== 'paid' && (
                                                <Button size="sm" onClick={() => router.patch(`/invoices/${inv.id}/mark-paid`, {}, { preserveScroll: true })}
                                                    className="h-7 px-2 text-xs">
                                                    <CheckCircle size={12} /> Mark Paid
                                                </Button>
                                            )}
                                        </div>
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
