<script setup lang="ts">
import SeoHead from '@/components/public/SeoHead.vue';
import PublicLayout from '@/layouts/public/PublicLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { CheckCircle2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ otpRequired: boolean }>();

const form = useForm({
    seller_name: '',
    mobile: '',
    email: '',
    city: '',
    registration_number: '',
    make: '',
    model: '',
    variant: '',
    manufacturing_year: null as number | null,
    odometer_km: null as number | null,
    expected_price: null as number | null,
    loan_status: 'none',
    preferred_inspection_location: '',
    preferred_date: '',
    remarks: '',
    consent: false,
    otp_token: '' as string | null,
    company: '',
});

const otpStage = ref<'idle' | 'sent' | 'verified'>('idle');
const otpCode = ref('');
const otpError = ref('');
const otpBusy = ref(false);

function readXsrf(): string {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}
async function otpFetch(url: string, body: object) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': readXsrf(), Accept: 'application/json' },
        body: JSON.stringify(body),
    });
    return { ok: res.ok, body: await res.json().catch(() => ({})) };
}
async function requestOtp() {
    otpError.value = '';
    if (!/^[0-9]{10}$/.test(form.mobile)) { otpError.value = 'Enter a valid 10-digit mobile number.'; return; }
    otpBusy.value = true;
    const { ok, body } = await otpFetch('/enquiries/otp/request', { mobile: form.mobile, purpose: 'sell_car' });
    otpBusy.value = false;
    if (ok) otpStage.value = 'sent';
    else otpError.value = body.message ?? 'Could not send OTP.';
}
async function verifyOtp() {
    otpError.value = '';
    otpBusy.value = true;
    const { ok, body } = await otpFetch('/enquiries/otp/verify', { mobile: form.mobile, code: otpCode.value, purpose: 'sell_car' });
    otpBusy.value = false;
    if (ok && body.otp_token) { form.otp_token = body.otp_token; otpStage.value = 'verified'; }
    else otpError.value = body.message ?? 'Invalid OTP.';
}

const canSubmit = computed(() => form.consent && (!props.otpRequired || otpStage.value === 'verified'));

function submit() {
    form.post('/sell-your-car', {
        preserveScroll: true,
        onSuccess: () => { form.reset(); otpStage.value = 'idle'; otpCode.value = ''; },
    });
}
</script>

<template>
    <SeoHead title="Sell Your Car — Car4Sales" description="Sell your car at the best price. Free inspection, instant quote and hassle-free paperwork." />

    <PublicLayout>
        <section class="bg-gradient-to-br from-brand-yellow to-brand-gold py-12 text-brand-maroon">
            <div class="mx-auto max-w-4xl px-4 text-center">
                <h1 class="text-3xl font-extrabold md:text-4xl">Sell Your Car</h1>
                <p class="mx-auto mt-2 max-w-xl">Get the best price with a free inspection and instant quote. Fill in your details and our team will call you.</p>
            </div>
        </section>

        <div class="mx-auto max-w-3xl px-4 py-10">
            <form class="grid gap-4 rounded-2xl border bg-white p-6 shadow-sm" @submit.prevent="submit">
                <input v-model="form.company" type="text" tabindex="-1" class="hidden" aria-hidden="true" />

                <h2 class="text-lg font-bold text-neutral-900">Your Details</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><input v-model="form.seller_name" placeholder="Full name *" required class="w-full rounded-lg border px-3 py-2.5 text-sm" /><p v-if="form.errors.seller_name" class="mt-1 text-xs text-brand-red">{{ form.errors.seller_name }}</p></div>
                    <div><input v-model="form.mobile" placeholder="Mobile (10 digits) *" required maxlength="10" inputmode="numeric" class="w-full rounded-lg border px-3 py-2.5 text-sm" /><p v-if="form.errors.mobile" class="mt-1 text-xs text-brand-red">{{ form.errors.mobile }}</p></div>
                    <input v-model="form.email" type="email" placeholder="Email" class="w-full rounded-lg border px-3 py-2.5 text-sm" />
                    <input v-model="form.city" placeholder="City" class="w-full rounded-lg border px-3 py-2.5 text-sm" />
                </div>

                <h2 class="mt-2 text-lg font-bold text-neutral-900">Car Details</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <input v-model="form.registration_number" placeholder="Registration number" class="w-full rounded-lg border px-3 py-2.5 text-sm" />
                    <input v-model.number="form.manufacturing_year" type="number" placeholder="Mfg. year" class="w-full rounded-lg border px-3 py-2.5 text-sm" />
                    <input v-model="form.make" placeholder="Make (e.g. Maruti)" class="w-full rounded-lg border px-3 py-2.5 text-sm" />
                    <input v-model="form.model" placeholder="Model (e.g. Swift)" class="w-full rounded-lg border px-3 py-2.5 text-sm" />
                    <input v-model="form.variant" placeholder="Variant" class="w-full rounded-lg border px-3 py-2.5 text-sm" />
                    <input v-model.number="form.odometer_km" type="number" placeholder="Odometer (km)" class="w-full rounded-lg border px-3 py-2.5 text-sm" />
                    <input v-model.number="form.expected_price" type="number" placeholder="Expected price (₹)" class="w-full rounded-lg border px-3 py-2.5 text-sm" />
                    <select v-model="form.loan_status" class="w-full rounded-lg border px-3 py-2.5 text-sm">
                        <option value="none">No active loan</option>
                        <option value="active">Loan active</option>
                        <option value="closed_pending_noc">Loan closed, NOC pending</option>
                    </select>
                </div>

                <h2 class="mt-2 text-lg font-bold text-neutral-900">Inspection Preference</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <input v-model="form.preferred_inspection_location" placeholder="Preferred location" class="w-full rounded-lg border px-3 py-2.5 text-sm" />
                    <input v-model="form.preferred_date" type="date" class="w-full rounded-lg border px-3 py-2.5 text-sm" />
                </div>
                <textarea v-model="form.remarks" rows="2" placeholder="Anything else we should know?" class="w-full rounded-lg border px-3 py-2.5 text-sm" />

                <!-- OTP -->
                <div v-if="otpRequired && otpStage !== 'verified'" class="rounded-lg border border-dashed bg-neutral-50 p-3">
                    <div v-if="otpStage === 'idle'" class="flex items-center justify-between gap-2">
                        <span class="text-xs text-neutral-600">Verify your mobile number to submit.</span>
                        <button type="button" :disabled="otpBusy" class="rounded-md bg-brand-maroon px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50" @click="requestOtp">Send OTP</button>
                    </div>
                    <div v-else class="flex items-center gap-2">
                        <input v-model="otpCode" placeholder="6-digit OTP" maxlength="6" inputmode="numeric" class="w-32 rounded-lg border px-3 py-2 text-sm" />
                        <button type="button" :disabled="otpBusy" class="rounded-md bg-brand-maroon px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50" @click="verifyOtp">Verify</button>
                    </div>
                    <p v-if="otpError" class="mt-1 text-xs text-brand-red">{{ otpError }}</p>
                </div>
                <p v-else-if="otpRequired && otpStage === 'verified'" class="flex items-center gap-1 text-xs font-medium text-green-600"><CheckCircle2 class="size-4" /> Mobile verified</p>

                <label class="flex items-start gap-2 text-xs text-neutral-600">
                    <input v-model="form.consent" type="checkbox" class="mt-0.5" required />
                    <span>I agree to be contacted regarding selling my car and accept the privacy policy.</span>
                </label>
                <p v-if="form.errors.consent" class="text-xs text-brand-red">{{ form.errors.consent }}</p>
                <p v-if="form.errors.otp_token" class="text-xs text-brand-red">{{ form.errors.otp_token }}</p>

                <button type="submit" :disabled="form.processing || !canSubmit" class="rounded-lg bg-brand-maroon px-5 py-3 text-sm font-bold text-white disabled:opacity-50">
                    Get My Quote
                </button>
            </form>
        </div>
    </PublicLayout>
</template>
