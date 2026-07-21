<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    bookingTerms: string;
    defaultBookingTerms: string;
    canUpdate: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Settings', href: '/admin/settings' },
];

const form = useForm({
    booking_terms: props.bookingTerms ?? '',
});

function save() {
    form.patch('/admin/settings', { preserveScroll: true });
}
function resetToDefault() {
    form.booking_terms = props.defaultBookingTerms;
}
</script>

<template>
    <Head title="Settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">Settings</h1>
                <p class="text-sm text-muted-foreground">Editable content used across the application.</p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Booking slip — Terms &amp; Conditions</CardTitle>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Printed on the customer booking slip. Enter <strong>one clause per line</strong>; blank lines are ignored. Leave empty to use
                        the built-in default.
                    </p>
                </CardHeader>
                <CardContent class="grid gap-3">
                    <div class="grid gap-1.5">
                        <Label for="booking_terms">Clauses</Label>
                        <textarea
                            id="booking_terms"
                            v-model="form.booking_terms"
                            :disabled="!canUpdate"
                            rows="16"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                            placeholder="One clause per line…"
                        />
                        <p v-if="form.errors.booking_terms" class="text-xs text-brand-red">{{ form.errors.booking_terms }}</p>
                        <p class="text-xs text-muted-foreground">{{ form.booking_terms.split('\n').filter((l) => l.trim()).length }} clause(s)</p>
                    </div>
                    <div v-if="canUpdate" class="flex items-center gap-2">
                        <Button :disabled="form.processing" @click="save">Save</Button>
                        <Button type="button" variant="outline" @click="resetToDefault">Reset to default</Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
