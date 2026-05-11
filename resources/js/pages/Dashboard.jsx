import AppLayout from '../layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Badge } from '../components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../components/ui/table';
import { formatPeso, statusColor, priorityColor } from '../lib/utils';
import { ClipboardList, Clock, DollarSign, Building2, AlertTriangle } from 'lucide-react';

function StatCard({ icon: Icon, label, value, sub, color = 'text-[#185FA5]' }) {
    return (
        <Card>
            <CardContent className="flex items-center gap-4 p-6">
                <div className={`rounded-xl bg-blue-50 p-3 ${color}`}>
                    <Icon size={22} />
                </div>
                <div>
                    <p className="text-xs font-medium text-gray-500">{label}</p>
                    <p className="text-2xl font-bold text-gray-900">{value}</p>
                    {sub && <p className="text-xs text-gray-400">{sub}</p>}
                </div>
            </CardContent>
        </Card>
    );
}

export default function Dashboard({ stats, isAdmin }) {
    const totals = stats.totals ?? {};
    const recentOrders = stats.recent_orders ?? [];
    const urgentPending = stats.urgent_pending ?? [];

    return (
        <AppLayout title="Dashboard">
            {/* Stat cards */}
            <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard icon={ClipboardList} label="Pending Orders"     value={totals.pending_count ?? 0} />
                <StatCard icon={Clock}         label="Orders Today"        value={totals.orders_today ?? totals.orders_this_month ?? 0} />
                <StatCard icon={DollarSign}    label="Billed YTD"          value={formatPeso(totals.total_billed_ytd ?? totals.total_billed ?? 0)} color="text-green-600" />
                {isAdmin
                    ? <StatCard icon={Building2} label="Active Branches" value={totals.active_branches ?? 0} />
                    : <StatCard icon={ClipboardList} label="Delivered" value={totals.delivered_count ?? 0} />
                }
            </div>

            <div className={`grid gap-6 ${isAdmin && urgentPending.length ? 'lg:grid-cols-3' : ''}`}>
                {/* Recent orders */}
                <Card className={isAdmin && urgentPending.length ? 'lg:col-span-2' : ''}>
                    <CardHeader>
                        <CardTitle>Recent Orders</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Reference</TableHead>
                                    {isAdmin && <TableHead>Branch</TableHead>}
                                    <TableHead>Priority</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Total</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recentOrders.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-center text-gray-400 py-8">
                                            No orders yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {recentOrders.map((order) => (
                                    <TableRow key={order.id}>
                                        <TableCell className="font-mono text-xs text-gray-600">{order.reference_number}</TableCell>
                                        {isAdmin && <TableCell className="font-medium">{order.branch?.name}</TableCell>}
                                        <TableCell>
                                            <Badge className={priorityColor(order.priority)}>{order.priority}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge className={statusColor(order.status)}>{order.status.replace('_', ' ')}</Badge>
                                        </TableCell>
                                        <TableCell className="text-right font-semibold">{formatPeso(order.total_amount)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {/* Urgent pending — admin only */}
                {isAdmin && urgentPending.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-red-600">
                                <AlertTriangle size={16} /> Urgent Pending
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 p-4 pt-0">
                            {urgentPending.map((order) => (
                                <div key={order.id} className="rounded-lg border border-red-100 bg-red-50 p-3">
                                    <p className="text-xs font-mono text-gray-500">{order.reference_number}</p>
                                    <p className="text-sm font-semibold text-gray-900">{order.branch?.name}</p>
                                    <p className="text-xs text-gray-500">{formatPeso(order.total_amount)}</p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
