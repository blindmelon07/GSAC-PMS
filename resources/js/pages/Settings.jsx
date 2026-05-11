import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import { Card, CardContent } from '../components/ui/card';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';

function SettingRow({ label, description, value, onChange, suffix = '%', min = 0, max = 100, step = '0.01' }) {
    return (
        <div className="flex items-start justify-between gap-8 py-5 border-b border-gray-100 last:border-0">
            <div className="flex-1">
                <p className="text-sm font-semibold text-gray-800">{label}</p>
                {description && <p className="mt-0.5 text-xs text-gray-400">{description}</p>}
            </div>
            <div className="flex items-center gap-2 w-40 shrink-0">
                <Input
                    type="number"
                    value={value}
                    onChange={e => onChange(e.target.value)}
                    min={min}
                    max={max}
                    step={step}
                    className="text-right"
                />
                <span className="text-sm font-medium text-gray-500 w-4">{suffix}</span>
            </div>
        </div>
    );
}

export default function Settings({ settings }) {
    const { props } = usePage();
    const flash = props.flash ?? {};

    const byKey = Object.fromEntries((settings ?? []).map(s => [s.key, s.value]));

    const [vatRate,      setVatRate]      = useState(byKey.vat_rate      ?? '12.00');
    const [discountRate, setDiscountRate] = useState(byKey.discount_rate ?? '0.00');
    const [submitting,   setSubmitting]   = useState(false);

    function handleSubmit(e) {
        e.preventDefault();
        setSubmitting(true);
        router.post('/settings', {
            vat_rate:      vatRate,
            discount_rate: discountRate,
        }, {
            onFinish: () => setSubmitting(false),
        });
    }

    const exampleSubtotal  = 1000;
    const discount         = parseFloat(discountRate) || 0;
    const vat              = parseFloat(vatRate) || 0;
    const discountAmt      = exampleSubtotal * (discount / 100);
    const taxableAmt       = exampleSubtotal - discountAmt;
    const taxAmt           = taxableAmt * (vat / 100);
    const total            = taxableAmt + taxAmt;

    return (
        <AppLayout title="Settings">
            {flash.success && (
                <div className="mb-4 rounded-md bg-green-50 px-4 py-2 text-sm text-green-700 border border-green-200">
                    {flash.success}
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-3">
                {/* Settings form */}
                <div className="lg:col-span-2">
                    <Card>
                        <CardContent className="p-6">
                            <h2 className="mb-1 text-sm font-semibold text-gray-800">Tax & Discount</h2>
                            <p className="mb-5 text-xs text-gray-400">
                                These rates apply to all new orders and generated invoices.
                                Existing records are not affected.
                            </p>
                            <form onSubmit={handleSubmit}>
                                <SettingRow
                                    label="VAT Rate"
                                    description="Value-added tax applied to order subtotals and invoices."
                                    value={vatRate}
                                    onChange={setVatRate}
                                />
                                <SettingRow
                                    label="Discount Rate"
                                    description="Global discount applied to invoice subtotals before VAT is computed."
                                    value={discountRate}
                                    onChange={setDiscountRate}
                                />
                                <div className="mt-5 flex justify-end">
                                    <Button type="submit" disabled={submitting}>
                                        {submitting ? 'Saving…' : 'Save Settings'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                {/* Live preview */}
                <div>
                    <Card className="border-[#185FA5]/20 bg-blue-50/40">
                        <CardContent className="p-5">
                            <h3 className="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Preview (₱{exampleSubtotal.toLocaleString()} subtotal)
                            </h3>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between text-gray-600">
                                    <span>Subtotal</span>
                                    <span>₱{exampleSubtotal.toFixed(2)}</span>
                                </div>
                                {discountAmt > 0 && (
                                    <div className="flex justify-between text-red-600">
                                        <span>Discount ({discount}%)</span>
                                        <span>− ₱{discountAmt.toFixed(2)}</span>
                                    </div>
                                )}
                                <div className="flex justify-between text-gray-600">
                                    <span>After Discount</span>
                                    <span>₱{taxableAmt.toFixed(2)}</span>
                                </div>
                                <div className="flex justify-between text-gray-600">
                                    <span>VAT ({vat}%)</span>
                                    <span>+ ₱{taxAmt.toFixed(2)}</span>
                                </div>
                                <div className="flex justify-between border-t border-[#185FA5]/20 pt-2 font-semibold text-gray-900">
                                    <span>Total</span>
                                    <span>₱{total.toFixed(2)}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
