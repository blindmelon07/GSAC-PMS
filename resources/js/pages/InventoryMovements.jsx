import { Link } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import { Card, CardContent } from '../components/ui/card';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../components/ui/table';
import { ArrowLeft, TrendingUp, TrendingDown } from 'lucide-react';

const TYPE_META = {
    restock:           { label: 'Restock',     color: 'bg-green-100 text-green-700' },
    adjustment:        { label: 'Adjustment',  color: 'bg-blue-100 text-blue-700' },
    order_fulfillment: { label: 'Fulfillment', color: 'bg-purple-100 text-purple-700' },
};

export default function InventoryMovements({ inventory, movements }) {
    const data = movements.data ?? [];

    return (
        <AppLayout title={`Movement Log — ${inventory.product?.name}`}>
            <div className="mb-4 flex items-center gap-3">
                <Link href="/inventory">
                    <Button variant="outline" size="sm" className="h-8 gap-1.5">
                        <ArrowLeft size={13} /> Back to Inventory
                    </Button>
                </Link>
                <div>
                    <span className="text-sm font-medium text-gray-700">{inventory.product?.name}</span>
                    <span className="ml-2 font-mono text-xs text-gray-400">{inventory.product?.code}</span>
                </div>
                <Badge className="bg-gray-100 text-gray-700 ml-auto">
                    Current stock: <strong className="ml-1">{inventory.quantity_on_hand}</strong>
                </Badge>
            </div>

            <Card>
                <CardContent className="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead className="text-center">Change</TableHead>
                                <TableHead className="text-center">Before</TableHead>
                                <TableHead className="text-center">After</TableHead>
                                <TableHead>Reference</TableHead>
                                <TableHead>Notes</TableHead>
                                <TableHead>Performed By</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={8} className="py-10 text-center text-gray-400">
                                        No movements recorded yet.
                                    </TableCell>
                                </TableRow>
                            )}
                            {data.map(m => {
                                const meta = TYPE_META[m.type] ?? { label: m.type, color: 'bg-gray-100 text-gray-600' };
                                const isPositive = m.quantity_change > 0;
                                return (
                                    <TableRow key={m.id}>
                                        <TableCell className="text-xs text-gray-500 whitespace-nowrap">
                                            {new Date(m.created_at).toLocaleString('en-PH', {
                                                month: 'short', day: 'numeric',
                                                hour: '2-digit', minute: '2-digit',
                                            })}
                                        </TableCell>
                                        <TableCell>
                                            <Badge className={meta.color}>{meta.label}</Badge>
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <span className={`flex items-center justify-center gap-1 font-bold ${isPositive ? 'text-green-600' : 'text-red-600'}`}>
                                                {isPositive
                                                    ? <TrendingUp size={13} />
                                                    : <TrendingDown size={13} />
                                                }
                                                {isPositive ? '+' : ''}{m.quantity_change}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-center text-sm text-gray-500">{m.quantity_before}</TableCell>
                                        <TableCell className="text-center text-sm font-semibold text-gray-800">{m.quantity_after}</TableCell>
                                        <TableCell className="font-mono text-xs text-gray-500">{m.reference ?? '—'}</TableCell>
                                        <TableCell className="text-xs text-gray-500 max-w-48 truncate">{m.notes ?? '—'}</TableCell>
                                        <TableCell className="text-xs text-gray-600">{m.performer?.name ?? '—'}</TableCell>
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
