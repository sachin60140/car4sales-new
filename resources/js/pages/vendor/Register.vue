<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    company_name: '',
    phone: '',
    city: '',
    gst_number: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/vendor/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Vendor Registration" />

    <div class="flex min-h-screen items-center justify-center bg-muted/30 p-4">
        <div class="w-full max-w-lg rounded-2xl border border-sidebar-border/70 bg-background p-6 shadow-sm sm:p-8">
            <div class="mb-6 flex items-center gap-3">
                <span class="flex size-11 items-center justify-center rounded-xl bg-brand-yellow text-brand-maroon ring-1 ring-black/5">
                    <AppLogoIcon class="size-7" />
                </span>
                <div>
                    <h1 class="text-lg font-bold">Become a Sourcing Partner</h1>
                    <p class="text-sm text-muted-foreground">Register to list vehicles you want to sell to us.</p>
                </div>
            </div>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="name">Your Name</Label>
                        <Input id="name" v-model="form.name" required autofocus />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="company">Company / Dealership</Label>
                        <Input id="company" v-model="form.company_name" placeholder="Optional" />
                        <InputError :message="form.errors.company_name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="phone">Phone</Label>
                        <Input id="phone" v-model="form.phone" required />
                        <InputError :message="form.errors.phone" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="city">City</Label>
                        <Input id="city" v-model="form.city" placeholder="Optional" />
                        <InputError :message="form.errors.city" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="gst">GST Number</Label>
                        <Input id="gst" v-model="form.gst_number" placeholder="Optional" />
                        <InputError :message="form.errors.gst_number" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="email">Email</Label>
                        <Input id="email" v-model="form.email" type="email" required />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="password">Password</Label>
                        <Input id="password" v-model="form.password" type="password" required />
                        <InputError :message="form.errors.password" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="password_confirmation">Confirm Password</Label>
                        <Input id="password_confirmation" v-model="form.password_confirmation" type="password" required />
                    </div>
                </div>

                <p class="rounded-lg bg-muted/60 px-3 py-2 text-xs text-muted-foreground">
                    Your account will be reviewed and activated by our team before you can submit vehicles.
                </p>

                <Button type="submit" :disabled="form.processing" class="w-full">Create Vendor Account</Button>

                <p class="text-center text-sm text-muted-foreground">
                    Already registered? <Link href="/login" class="font-medium text-brand-red hover:underline">Sign in</Link>
                </p>
            </form>
        </div>
    </div>
</template>
