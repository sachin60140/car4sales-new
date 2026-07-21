<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Car } from 'lucide-vue-next';

const page = usePage<SharedData>();
</script>

<template>
    <Head title="Welcome" />

    <div class="flex min-h-screen flex-col items-center justify-center gap-6 bg-background p-6 text-foreground">
        <div class="flex items-center gap-3">
            <div class="flex size-12 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                <Car class="size-7" />
            </div>
            <h1 class="text-3xl font-bold tracking-tight">Car4Sales</h1>
        </div>

        <p class="max-w-md text-center text-muted-foreground">
            Multi-branch pre-owned car dealership management platform. The public website launches in a later phase — employees can sign in to the
            management panel below.
        </p>

        <div class="flex gap-3">
            <Button v-if="page.props.auth.user" as-child>
                <Link href="/dashboard">Go to Dashboard</Link>
            </Button>
            <Button v-else as-child>
                <Link :href="route('login')">Employee Login</Link>
            </Button>
        </div>
    </div>
</template>
