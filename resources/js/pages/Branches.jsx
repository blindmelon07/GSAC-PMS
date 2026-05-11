import AppLayout from '../layouts/AppLayout';
import { Card, CardContent } from '../components/ui/card';
import { Badge } from '../components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../components/ui/table';
import { formatPeso } from '../lib/utils';
import { MapPin, Phone, Mail, Users } from 'lucide-react';

export default function Branches({ branches }) {
    return (
        <AppLayout title="Branches">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {(branches ?? []).map((branch) => (
                    <Card key={branch.id} className="flex flex-col">
                        <CardContent className="p-4">
                            <div className="mb-3 flex items-start justify-between">
                                <div>
                                    <p className="text-xs font-mono font-semibold text-[#185FA5]">{branch.code}</p>
                                    <p className="mt-0.5 text-sm font-semibold text-gray-900 leading-tight">{branch.name}</p>
                                </div>
                                {branch.is_main_branch
                                    ? <Badge className="bg-[#185FA5] text-white text-[10px]">Main</Badge>
                                    : branch.is_active
                                        ? <Badge className="bg-green-100 text-green-700 text-[10px]">Active</Badge>
                                        : <Badge className="bg-gray-100 text-gray-500 text-[10px]">Inactive</Badge>
                                }
                            </div>
                            <div className="space-y-1.5 text-xs text-gray-500">
                                {branch.city && (
                                    <div className="flex items-center gap-1.5">
                                        <MapPin size={11} /> {branch.city}
                                    </div>
                                )}
                                {branch.contact_phone && (
                                    <div className="flex items-center gap-1.5">
                                        <Phone size={11} /> {branch.contact_phone}
                                    </div>
                                )}
                                {branch.contact_email && (
                                    <div className="flex items-center gap-1.5">
                                        <Mail size={11} /> {branch.contact_email}
                                    </div>
                                )}
                                {branch.users_count !== undefined && (
                                    <div className="flex items-center gap-1.5">
                                        <Users size={11} /> {branch.users_count} users
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
