<script setup lang="ts">
import EnquiryForm from '@/components/public/EnquiryForm.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import VehicleCard from '@/components/public/VehicleCard.vue';
import PublicLayout from '@/layouts/public/PublicLayout.vue';
import type { VehicleCardData } from '@/types/public';
import { Link, router } from '@inertiajs/vue3';
import { BadgeCheck, Banknote, Car, HandCoins, Search, ShieldCheck, Wrench } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    featured: VehicleCardData[];
    recent: VehicleCardData[];
    brands: { make: string; total: number }[];
    budgetBands: { label: string; min?: number; max?: number }[];
    branches: { id: number; name: string; slug: string; city: string | null; address: string | null; phone: string | null }[];
    testimonials: { id: number; customer_name: string; city: string | null; rating: number; message: string }[];
    faqs: { id: number; question: string; answer: string }[];
    stats: { in_stock: number; branches: number };
}>();

const search = ref('');
function doSearch() {
    router.get('/cars', search.value ? { search: search.value } : {});
}
function byBudget(band: { min?: number; max?: number }) {
    const q: Record<string, number> = {};
    if (band.min) q.price_min = band.min;
    if (band.max) q.price_max = band.max;
    router.get('/cars', q);
}

const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'AutoDealer',
    name: 'Car4Sales',
    description: 'Certified pre-owned car dealership',
    url: typeof window !== 'undefined' ? window.location.origin : '',
};

const why = [
    { icon: BadgeCheck, title: 'Quality Checked', text: 'Every car passes a rigorous multi-point inspection.' },
    { icon: ShieldCheck, title: 'Transparent Pricing', text: 'No hidden charges — the price you see is the price you pay.' },
    { icon: HandCoins, title: 'Easy Finance', text: 'Attractive loan options with quick approvals.' },
    { icon: Wrench, title: 'After-sales Support', text: 'Serviced, refurbished and ready to drive.' },
];
</script>

<template>
    <SeoHead
        title="Car4Sales — Certified Pre-owned Cars"
        description="Buy and sell quality pre-owned cars with transparent pricing, quality checks and easy finance. Browse our latest stock across branches."
        :json-ld="jsonLd"
    />

    <PublicLayout>
        <!-- Hero -->
        <section class="relative overflow-hidden bg-gradient-to-br from-brand-maroon to-[hsl(0_62%_15%)] text-white">
            <Car class="pointer-events-none absolute -right-10 top-6 size-80 text-brand-yellow/10" />
            <div class="mx-auto max-w-7xl px-4 py-16 md:py-24">
                <p class="font-semibold text-brand-yellow">India's trusted pre-owned car dealership</p>
                <h1 class="mt-2 max-w-2xl text-4xl font-extrabold leading-tight md:text-5xl">
                    Find your perfect <span class="text-brand-yellow">pre-owned car</span>
                </h1>
                <p class="mt-3 max-w-xl text-white/80">
                    {{ stats.in_stock }}+ quality-checked cars across {{ stats.branches }} branches. Inspected, refurbished and ready to drive.
                </p>

                <div class="mt-6 flex max-w-xl overflow-hidden rounded-xl bg-white p-1.5 shadow-lg">
                    <input
                        v-model="search"
                        placeholder="Search by make, model or variant…"
                        class="flex-1 px-3 text-sm text-neutral-900 outline-none"
                        @keyup.enter="doSearch"
                    />
                    <button
                        class="flex items-center gap-1 rounded-lg bg-brand-yellow px-4 py-2.5 text-sm font-bold text-brand-maroon"
                        @click="doSearch"
                    >
                        <Search class="size-4" /> Search
                    </button>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Link
                        v-for="brand in brands.slice(0, 6)"
                        :key="brand.make"
                        :href="`/cars?make=${encodeURIComponent(brand.make)}`"
                        class="rounded-full bg-white/10 px-3 py-1 text-sm backdrop-blur hover:bg-white/20"
                    >
                        {{ brand.make }}
                    </Link>
                </div>
            </div>
        </section>

        <!-- Browse by budget -->
        <section class="mx-auto max-w-7xl px-4 py-10">
            <h2 class="mb-4 text-xl font-bold text-neutral-900">Browse by Budget</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <button
                    v-for="band in budgetBands"
                    :key="band.label"
                    class="rounded-xl border bg-white p-4 text-left transition-colors hover:border-brand-yellow hover:bg-brand-yellow/5"
                    @click="byBudget(band)"
                >
                    <Banknote class="mb-2 size-6 text-brand-orange" />
                    <span class="text-sm font-semibold text-neutral-800">{{ band.label }}</span>
                </button>
            </div>
        </section>

        <!-- Featured -->
        <section v-if="featured.length" class="mx-auto max-w-7xl px-4 py-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-neutral-900">Featured Cars</h2>
                <Link href="/cars" class="text-sm font-semibold text-brand-maroon hover:underline">View all →</Link>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <VehicleCard v-for="v in featured" :key="v.id" :vehicle="v" />
            </div>
        </section>

        <!-- Recently added -->
        <section v-if="recent.length" class="mx-auto max-w-7xl px-4 py-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-neutral-900">Recently Added</h2>
                <Link href="/cars?sort=year_desc" class="text-sm font-semibold text-brand-maroon hover:underline">View all →</Link>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <VehicleCard v-for="v in recent" :key="v.id" :vehicle="v" />
            </div>
        </section>

        <!-- Why choose us -->
        <section class="bg-neutral-50 py-12">
            <div class="mx-auto max-w-7xl px-4">
                <h2 class="mb-8 text-center text-2xl font-bold text-neutral-900">Why Choose Car4Sales</h2>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="item in why" :key="item.title" class="rounded-xl bg-white p-6 text-center shadow-sm">
                        <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-brand-yellow/20 text-brand-maroon">
                            <component :is="item.icon" class="size-6" />
                        </div>
                        <h3 class="font-semibold text-neutral-900">{{ item.title }}</h3>
                        <p class="mt-1 text-sm text-neutral-600">{{ item.text }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sell / Finance CTA -->
        <section class="mx-auto grid max-w-7xl gap-4 px-4 py-12 lg:grid-cols-2">
            <div class="flex flex-col justify-between rounded-2xl bg-gradient-to-br from-brand-yellow to-brand-gold p-8 text-brand-maroon">
                <div>
                    <h3 class="text-2xl font-extrabold">Sell Your Car</h3>
                    <p class="mt-2 max-w-sm">Get the best price for your car with a free inspection and instant quote. Hassle-free paperwork.</p>
                </div>
                <Link href="/sell-your-car" class="mt-4 w-fit rounded-lg bg-brand-maroon px-5 py-2.5 text-sm font-bold text-white">Get a Quote</Link>
            </div>
            <div class="flex flex-col justify-between rounded-2xl bg-brand-maroon p-8 text-white">
                <div>
                    <h3 class="text-2xl font-extrabold">Finance Assistance</h3>
                    <p class="mt-2 max-w-sm text-white/80">
                        Drive home your dream car with easy EMIs. Quick approvals and attractive interest rates.
                    </p>
                </div>
                <Link href="/finance" class="mt-4 w-fit rounded-lg bg-brand-yellow px-5 py-2.5 text-sm font-bold text-brand-maroon"
                    >Calculate EMI</Link
                >
            </div>
        </section>

        <!-- Testimonials -->
        <section v-if="testimonials.length" class="bg-neutral-50 py-12">
            <div class="mx-auto max-w-7xl px-4">
                <h2 class="mb-8 text-center text-2xl font-bold text-neutral-900">What Our Customers Say</h2>
                <div class="grid gap-4 md:grid-cols-3">
                    <div v-for="t in testimonials" :key="t.id" class="rounded-xl bg-white p-6 shadow-sm">
                        <div class="flex gap-0.5 text-brand-yellow">
                            <span v-for="n in 5" :key="n">{{ n <= t.rating ? '★' : '☆' }}</span>
                        </div>
                        <p class="mt-3 text-sm text-neutral-700">"{{ t.message }}"</p>
                        <p class="mt-3 text-sm font-semibold text-neutral-900">
                            {{ t.customer_name }}<span v-if="t.city" class="font-normal text-neutral-500">, {{ t.city }}</span>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Branches + Contact -->
        <section class="mx-auto grid max-w-7xl gap-8 px-4 py-12 lg:grid-cols-2">
            <div>
                <h2 class="mb-4 text-xl font-bold text-neutral-900">Our Branches</h2>
                <div class="grid gap-3">
                    <Link
                        v-for="b in branches"
                        :key="b.id"
                        :href="`/branches/${b.slug}`"
                        class="rounded-xl border bg-white p-4 hover:border-brand-yellow"
                    >
                        <p class="font-semibold text-neutral-900">{{ b.name }}</p>
                        <p class="text-sm text-neutral-500">
                            {{ b.address ?? b.city }}<span v-if="b.phone"> · {{ b.phone }}</span>
                        </p>
                    </Link>
                </div>
            </div>
            <div class="rounded-2xl border bg-white p-6 shadow-sm">
                <EnquiryForm type="callback" heading="Request a Callback" submit-label="Request Callback" />
            </div>
        </section>

        <!-- FAQ -->
        <section v-if="faqs.length" class="mx-auto max-w-4xl px-4 pb-16">
            <h2 class="mb-6 text-center text-2xl font-bold text-neutral-900">Frequently Asked Questions</h2>
            <div class="divide-y rounded-xl border bg-white">
                <details v-for="f in faqs" :key="f.id" class="group px-5 py-4">
                    <summary class="flex cursor-pointer items-center justify-between font-medium text-neutral-900">
                        {{ f.question }}
                        <span class="text-brand-orange transition-transform group-open:rotate-45">+</span>
                    </summary>
                    <p class="mt-2 text-sm text-neutral-600">{{ f.answer }}</p>
                </details>
            </div>
        </section>
    </PublicLayout>
</template>
