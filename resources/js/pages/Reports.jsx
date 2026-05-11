import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import { Card, CardContent } from '../components/ui/card';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Input, Select } from '../components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../components/ui/table';
import { formatPeso, statusColor } from '../lib/utils';
import { BarChart3, FileText, Building2, Tag, Search } from 'lucide-react';

const REPORT_TYPES = [
    { key: 'orders',     label: 'Orders',           icon: BarChart3  },
    { key: 'invoices',   label: 'Invoices',          icon: FileText   },
    { key: 'branches',   label: 'Branch Summary',    icon: Building2  },
    { key: 'form-types', label: 'Form Types Usage',  icon: Tag        },
];

function SummaryCard({ label, value, sub, color = 'text-[#185FA5]' }) {
    return (
        <Card>
            <CardContent className="p-4">
                <p className="text-xs font-medium text-gray-500">{label}</p>
                <p className={`mt-1 text-xl font-bold ${color}`}>{value}</p>
                {sub && <p className="text-[10px] text-gray-400 mt-0.5">{sub}</p>}
            </CardContent>
        </Card>
    );
}

/* ── Orders table ── */
function OrdersTable({ data }) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Reference</TableHead>
                    <TableHead>Branch</TableHead>
                    <TableHead>Requester</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Priority</TableHead>
                    <TableHead className="text-right">Subtotal</TableHead>
                    <TableHead className="text-right">VAT</TableHead>
                    <TableHead className="text-right">Total</TableHead>
                    <TableHead>Date</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {data.length === 0 && <EmptyRow cols={9} />}
                {data.map((r, i) => (
                    <TableRow key={i}>
                        <TableCell className="font-mono text-xs">{r.reference}</TableCell>
                        <TableCell>{r.branch}</TableCell>
                        <TableCell className="text-xs text-gray-500">{r.requester ?? '—'}</TableCell>
                        <TableCell><Badge className={statusColor(r.status)}>{r.status.replace('_', ' ')}</Badge></TableCell>
                        <TableCell className="capitalize text-xs">{r.priority}</TableCell>
                        <TableCell className="text-right text-xs">{formatPeso(r.subtotal)}</TableCell>
                        <TableCell className="text-right text-xs text-gray-500">{formatPeso(r.tax_amount)}</TableCell>
                        <TableCell className="text-right font-semibold">{formatPeso(r.total)}</TableCell>
                        <TableCell className="text-xs text-gray-400">{r.date}</TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

/* ── Invoices table ── */
function InvoicesTable({ data }) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Invoice #</TableHead>
                    <TableHead>Branch</TableHead>
                    <TableHead>Period</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Subtotal</TableHead>
                    <TableHead className="text-right">Discount</TableHead>
                    <TableHead className="text-right">VAT</TableHead>
                    <TableHead className="text-right">Total</TableHead>
                    <TableHead>Due</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {data.length === 0 && <EmptyRow cols={9} />}
                {data.map((r, i) => (
                    <TableRow key={i}>
                        <TableCell className="font-mono text-xs">{r.invoice_number}</TableCell>
                        <TableCell>{r.branch}</TableCell>
                        <TableCell className="text-xs text-gray-500">{r.billing_period}</TableCell>
                        <TableCell><Badge className={statusColor(r.status)}>{r.status}</Badge></TableCell>
                        <TableCell className="text-right text-xs">{formatPeso(r.subtotal)}</TableCell>
                        <TableCell className="text-right text-xs text-red-500">
                            {r.discount_amount > 0 ? `− ${formatPeso(r.discount_amount)} (${r.discount_rate}%)` : '—'}
                        </TableCell>
                        <TableCell className="text-right text-xs text-gray-500">{formatPeso(r.tax_amount)}</TableCell>
                        <TableCell className="text-right font-semibold">{formatPeso(r.total)}</TableCell>
                        <TableCell className="text-xs text-gray-400">{r.due_date}</TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

/* ── Branch summary table ── */
function BranchTable({ data }) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Branch</TableHead>
                    <TableHead>Code</TableHead>
                    <TableHead className="text-right">Total Orders</TableHead>
                    <TableHead className="text-right">Pending</TableHead>
                    <TableHead className="text-right">Delivered</TableHead>
                    <TableHead className="text-right">Billed</TableHead>
                    <TableHead className="text-right">Total Amount</TableHead>
                    <TableHead>Status</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {data.length === 0 && <EmptyRow cols={8} />}
                {data.map((r, i) => (
                    <TableRow key={i}>
                        <TableCell className="font-medium">{r.branch}</TableCell>
                        <TableCell className="font-mono text-xs">{r.code}</TableCell>
                        <TableCell className="text-right font-semibold">{r.total_orders}</TableCell>
                        <TableCell className="text-right text-amber-600">{r.pending_orders}</TableCell>
                        <TableCell className="text-right text-blue-600">{r.delivered_orders}</TableCell>
                        <TableCell className="text-right text-green-600">{r.billed_orders}</TableCell>
                        <TableCell className="text-right font-semibold">{formatPeso(r.total_amount)}</TableCell>
                        <TableCell>
                            <Badge className={r.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}>
                                {r.is_active ? 'Active' : 'Inactive'}
                            </Badge>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

/* ── Form types usage table ── */
function FormTypesTable({ data }) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Form Type</TableHead>
                    <TableHead>Code</TableHead>
                    <TableHead className="text-right">Times Ordered</TableHead>
                    <TableHead className="text-right">Total Qty</TableHead>
                    <TableHead className="text-right">Avg Unit Price</TableHead>
                    <TableHead className="text-right">Total Amount</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {data.length === 0 && <EmptyRow cols={6} />}
                {data.map((r, i) => (
                    <TableRow key={i}>
                        <TableCell className="font-medium">{r.form_type}</TableCell>
                        <TableCell className="font-mono text-xs">{r.code}</TableCell>
                        <TableCell className="text-right">{r.times_ordered}</TableCell>
                        <TableCell className="text-right font-semibold">{r.total_quantity.toLocaleString()}</TableCell>
                        <TableCell className="text-right text-xs text-gray-500">{formatPeso(r.avg_price)}</TableCell>
                        <TableCell className="text-right font-semibold text-green-700">{formatPeso(r.total_amount)}</TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function EmptyRow({ cols }) {
    return (
        <TableRow>
            <TableCell colSpan={cols} className="py-10 text-center text-gray-400">
                No data found for the selected period.
            </TableCell>
        </TableRow>
    );
}

/* ── Summary cards per report type ── */
function SummaryCards({ type, summary }) {
    if (type === 'orders') return (
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <SummaryCard label="Total Orders"  value={summary.count} />
            <SummaryCard label="Total Amount"  value={formatPeso(summary.total_amount)} color="text-green-600" />
            <SummaryCard label="Total VAT"     value={formatPeso(summary.total_tax)} color="text-gray-700" />
            <SummaryCard label="Pending"       value={summary.by_status?.pending ?? 0} color="text-amber-600" sub="orders" />
        </div>
    );

    if (type === 'invoices') return (
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <SummaryCard label="Total Invoices"  value={summary.count} />
            <SummaryCard label="Total Amount"    value={formatPeso(summary.total_amount)} color="text-green-600" />
            <SummaryCard label="Total Discount"  value={formatPeso(summary.total_discount)} color="text-red-500" />
            <SummaryCard label="Total VAT"       value={formatPeso(summary.total_tax)} color="text-gray-700" />
        </div>
    );

    if (type === 'branches') return (
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
            <SummaryCard label="Branches"      value={summary.count} />
            <SummaryCard label="Total Orders"  value={summary.total_orders} />
            <SummaryCard label="Total Amount"  value={formatPeso(summary.total_amount)} color="text-green-600" />
        </div>
    );

    if (type === 'form-types') return (
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
            <SummaryCard label="Form Types Used"  value={summary.count} />
            <SummaryCard label="Total Quantity"   value={summary.total_quantity?.toLocaleString()} />
            <SummaryCard label="Total Amount"     value={formatPeso(summary.total_amount)} color="text-green-600" />
        </div>
    );

    return null;
}

export default function Reports({ type, from, to, branchId, results, summary, branches }) {
    const [fromDate,       setFromDate]       = useState(from      ?? '');
    const [toDate,         setToDate]         = useState(to        ?? '');
    const [selectedBranch, setSelectedBranch] = useState(branchId ?? '');

    function applyFilters(overrides = {}) {
        const params = {
            type,
            from:      fromDate,
            to:        toDate,
            ...overrides,
        };
        if (selectedBranch && !overrides.hasOwnProperty('branch_id')) {
            params.branch_id = selectedBranch;
        }
        router.get('/reports', params);
    }

    function switchType(key) {
        router.get('/reports', {
            type:      key,
            from:      fromDate,
            to:        toDate,
            branch_id: selectedBranch || undefined,
        });
    }

    const showBranchFilter = type !== 'branches';

    return (
        <AppLayout title="Reports">
            {/* Report type tabs */}
            <div className="mb-5 flex gap-2 flex-wrap">
                {REPORT_TYPES.map(({ key, label, icon: Icon }) => (
                    <button
                        key={key}
                        onClick={() => switchType(key)}
                        className={[
                            'flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition-colors',
                            type === key
                                ? 'border-[#185FA5] bg-[#185FA5] text-white shadow-sm'
                                : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50',
                        ].join(' ')}
                    >
                        <Icon size={14} /> {label}
                    </button>
                ))}
            </div>

            {/* Filters */}
            <Card className="mb-5">
                <CardContent className="p-4">
                    <div className="flex flex-wrap items-end gap-3">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-600">From</label>
                            <Input
                                type="date"
                                value={fromDate}
                                onChange={e => setFromDate(e.target.value)}
                                className="w-36"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-600">To</label>
                            <Input
                                type="date"
                                value={toDate}
                                onChange={e => setToDate(e.target.value)}
                                className="w-36"
                            />
                        </div>
                        {showBranchFilter && (
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-600">Branch</label>
                                <Select
                                    value={selectedBranch}
                                    onChange={e => setSelectedBranch(e.target.value)}
                                    className="w-48"
                                >
                                    <option value="">All branches</option>
                                    {(branches ?? []).map(b => (
                                        <option key={b.id} value={b.id}>{b.name}</option>
                                    ))}
                                </Select>
                            </div>
                        )}
                        <Button
                            onClick={() => applyFilters()}
                            className="ml-auto"
                        >
                            <Search size={14} /> Run Report
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Summary cards */}
            <div className="mb-5">
                <SummaryCards type={type} summary={summary ?? {}} />
            </div>

            {/* Results table */}
            <Card>
                <CardContent className="p-0">
                    {type === 'orders'     && <OrdersTable    data={results ?? []} />}
                    {type === 'invoices'   && <InvoicesTable  data={results ?? []} />}
                    {type === 'branches'   && <BranchTable    data={results ?? []} />}
                    {type === 'form-types' && <FormTypesTable data={results ?? []} />}
                </CardContent>
            </Card>
        </AppLayout>
    );
}
