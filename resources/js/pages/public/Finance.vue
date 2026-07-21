<script setup lang="ts">
import EnquiryForm from '@/components/public/EnquiryForm.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import PublicLayout from '@/layouts/public/PublicLayout.vue';
import type { FinanceEstimate } from '@/types/public';
import { computed, reactive } from 'vue';

defineProps<{
    defaultEstimate: FinanceEstimate;
    config: { interest_rate: number; tenure_months: number; down_payment_pct: number };
}>();

const inputs = reactive({
    price: 500000,
    down_payment_pct: 15,
    tenure_months: 60,
    interest_rate: 11.5,
});

const result = computed<FinanceEstimate>(() => {
    const down = Math.round(inputs.price * (inputs.down_payment_pct / 100));
    const loan = Math.max(inputs.price - down, 0);
    const r = inputs.interest_rate / 12 / 100;
    const n = inputs.tenure_months;
    const emi = r > 0 ? (loan * r * (1 + r) ** n) / ((1 + r) ** n - 1) : loan / n;
    return {
        loan_amount: loan,
        down_payment: down,
        emi: Math.round(emi),
        tenure_months: n,
        interest_rate: inputs.interest_rate,
        total_payable: Math.round(emi * n + down),
    };
});

function money(v: number): string {
    return '₹' + Number(v).toLocaleString('en-IN');
}
</script>

<template>
    <SeoHead
        title="Car Finance & EMI Calculator — Car4Sales"
        description="Calculate your car loan EMI and apply for finance. Quick approvals and attractive interest rates on pre-owned cars."
    />

    <PublicLayout>
        <section class="bg-brand-maroon py-12 text-white">
            <div class="mx-auto max-w-4xl px-4 text-center">
                <h1 class="text-3xl font-extrabold md:text-4xl">Finance Assistance</h1>
                <p class="mx-auto mt-2 max-w-xl text-white/80">
                    Drive home your dream car with easy EMIs. Use the calculator below and apply in minutes.
                </p>
            </div>
        </section>

        <div class="mx-auto grid max-w-6xl gap-6 px-4 py-10 lg:grid-cols-2">
            <!-- Calculator -->
            <div class="rounded-2xl border bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-bold text-neutral-900">EMI Calculator</h2>
                <div class="space-y-5">
                    <div>
                        <div class="flex justify-between text-sm">
                            <span class="text-neutral-600">Car Price</span><span class="font-semibold">{{ money(inputs.price) }}</span>
                        </div>
                        <input
                            v-model.number="inputs.price"
                            type="range"
                            min="100000"
                            max="3000000"
                            step="25000"
                            class="mt-1 w-full accent-brand-maroon"
                        />
                    </div>
                    <div>
                        <div class="flex justify-between text-sm">
                            <span class="text-neutral-600">Down Payment ({{ inputs.down_payment_pct }}%)</span
                            ><span class="font-semibold">{{ money(result.down_payment) }}</span>
                        </div>
                        <input
                            v-model.number="inputs.down_payment_pct"
                            type="range"
                            min="0"
                            max="60"
                            step="1"
                            class="mt-1 w-full accent-brand-maroon"
                        />
                    </div>
                    <div>
                        <div class="flex justify-between text-sm">
                            <span class="text-neutral-600">Tenure</span><span class="font-semibold">{{ inputs.tenure_months }} months</span>
                        </div>
                        <input
                            v-model.number="inputs.tenure_months"
                            type="range"
                            min="12"
                            max="84"
                            step="6"
                            class="mt-1 w-full accent-brand-maroon"
                        />
                    </div>
                    <div>
                        <div class="flex justify-between text-sm">
                            <span class="text-neutral-600">Interest Rate</span><span class="font-semibold">{{ inputs.interest_rate }}%</span>
                        </div>
                        <input
                            v-model.number="inputs.interest_rate"
                            type="range"
                            min="7"
                            max="20"
                            step="0.25"
                            class="mt-1 w-full accent-brand-maroon"
                        />
                    </div>
                </div>

                <div class="mt-6 rounded-xl bg-brand-yellow/10 p-5 text-center">
                    <p class="text-sm text-neutral-600">Your Monthly EMI</p>
                    <p class="text-3xl font-extrabold text-brand-maroon">{{ money(result.emi) }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-sm text-neutral-600">
                        <div>
                            <p class="font-semibold text-neutral-900">{{ money(result.loan_amount) }}</p>
                            <p>Loan amount</p>
                        </div>
                        <div>
                            <p class="font-semibold text-neutral-900">{{ money(result.total_payable) }}</p>
                            <p>Total payable</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Finance enquiry -->
            <div class="rounded-2xl border bg-white p-6 shadow-sm">
                <EnquiryForm type="finance" heading="Apply for Finance" submit-label="Apply Now" purpose="enquiry" />
            </div>
        </div>
    </PublicLayout>
</template>
