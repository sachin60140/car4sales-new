<script setup lang="ts">
import type { PublicSite } from '@/types/public';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    type: string;
    vehicleId?: number;
    heading?: string;
    submitLabel?: string;
    purpose?: string;
}>();

const page = usePage<{ site: PublicSite }>();
const otpRequired = computed(() => page.props.site.otp_required);
const purpose = computed(() => props.purpose ?? props.type);

const form = useForm({
    type: props.type,
    name: '',
    mobile: '',
    email: '',
    city: '',
    message: '',
    consent: false,
    vehicle_id: props.vehicleId ?? null,
    otp_token: '' as string | null,
    company: '', // honeypot
});

// --- OTP ---
const otpStage = ref<'idle' | 'sent' | 'verified'>('idle');
const otpCode = ref('');
const otpError = ref('');
const otpBusy = ref(false);

function readXsrf(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function requestOtp() {
    otpError.value = '';
    if (!/^[0-9]{10}$/.test(form.mobile)) {
        otpError.value = 'Enter a valid 10-digit mobile number first.';
        return;
    }
    otpBusy.value = true;
    try {
        const res = await fetch('/enquiries/otp/request', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': readXsrf(), Accept: 'application/json' },
            body: JSON.stringify({ mobile: form.mobile, purpose: purpose.value }),
        });
        if (res.ok) {
            otpStage.value = 'sent';
        } else {
            const body = await res.json().catch(() => ({}));
            otpError.value = body.message ?? 'Could not send OTP.';
        }
    } finally {
        otpBusy.value = false;
    }
}

async function verifyOtp() {
    otpError.value = '';
    otpBusy.value = true;
    try {
        const res = await fetch('/enquiries/otp/verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': readXsrf(), Accept: 'application/json' },
            body: JSON.stringify({ mobile: form.mobile, code: otpCode.value, purpose: purpose.value }),
        });
        const body = await res.json().catch(() => ({}));
        if (res.ok && body.otp_token) {
            form.otp_token = body.otp_token;
            otpStage.value = 'verified';
        } else {
            otpError.value = body.message ?? 'Invalid OTP.';
        }
    } finally {
        otpBusy.value = false;
    }
}

const canSubmit = computed(() => form.consent && (!otpRequired.value || otpStage.value === 'verified'));

function submit() {
    form.post('/enquiries', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            otpStage.value = 'idle';
            otpCode.value = '';
        },
    });
}
</script>

<template>
    <form class="grid gap-3" @submit.prevent="submit">
        <h3 v-if="heading" class="text-lg font-semibold text-neutral-900">{{ heading }}</h3>

        <input v-model="form.company" type="text" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true" />

        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <input v-model="form.name" placeholder="Your name *" required class="w-full rounded-lg border px-3 py-2.5 text-sm" />
                <p v-if="form.errors.name" class="mt-1 text-xs text-brand-red">{{ form.errors.name }}</p>
            </div>
            <div>
                <input
                    v-model="form.mobile"
                    placeholder="Mobile (10 digits) *"
                    required
                    maxlength="10"
                    inputmode="numeric"
                    class="w-full rounded-lg border px-3 py-2.5 text-sm"
                />
                <p v-if="form.errors.mobile" class="mt-1 text-xs text-brand-red">{{ form.errors.mobile }}</p>
            </div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <input v-model="form.email" type="email" placeholder="Email (optional)" class="w-full rounded-lg border px-3 py-2.5 text-sm" />
            <input v-model="form.city" placeholder="City" class="w-full rounded-lg border px-3 py-2.5 text-sm" />
        </div>
        <textarea v-model="form.message" rows="3" placeholder="Message (optional)" class="w-full rounded-lg border px-3 py-2.5 text-sm" />

        <!-- OTP -->
        <div v-if="otpRequired && otpStage !== 'verified'" class="rounded-lg border border-dashed bg-neutral-50 p-3">
            <div v-if="otpStage === 'idle'" class="flex items-center justify-between gap-2">
                <span class="text-xs text-neutral-600">Verify your mobile number to submit.</span>
                <button
                    type="button"
                    :disabled="otpBusy"
                    class="rounded-md bg-brand-maroon px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                    @click="requestOtp"
                >
                    Send OTP
                </button>
            </div>
            <div v-else class="flex items-center gap-2">
                <input
                    v-model="otpCode"
                    placeholder="6-digit OTP"
                    maxlength="6"
                    inputmode="numeric"
                    class="w-32 rounded-lg border px-3 py-2 text-sm"
                />
                <button
                    type="button"
                    :disabled="otpBusy"
                    class="rounded-md bg-brand-maroon px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                    @click="verifyOtp"
                >
                    Verify
                </button>
                <button type="button" class="text-xs text-neutral-500 underline" @click="requestOtp">Resend</button>
            </div>
            <p v-if="otpError" class="mt-1 text-xs text-brand-red">{{ otpError }}</p>
        </div>
        <p v-else-if="otpRequired && otpStage === 'verified'" class="text-xs font-medium text-green-600">✓ Mobile verified</p>

        <label class="flex items-start gap-2 text-xs text-neutral-600">
            <input v-model="form.consent" type="checkbox" class="mt-0.5" required />
            <span>I agree to be contacted by {{ page.props.site.name }} regarding my enquiry and accept the privacy policy.</span>
        </label>
        <p v-if="form.errors.consent" class="text-xs text-brand-red">{{ form.errors.consent }}</p>
        <p v-if="form.errors.otp_token" class="text-xs text-brand-red">{{ form.errors.otp_token }}</p>

        <button
            type="submit"
            :disabled="form.processing || !canSubmit"
            class="rounded-lg bg-brand-yellow px-5 py-2.5 text-sm font-bold text-brand-maroon transition-colors hover:bg-brand-yellow/90 disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{ submitLabel ?? 'Submit Enquiry' }}
        </button>
    </form>
</template>
