<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import type { PublicSite } from '@/types/public';
import { Link, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Menu, MessageCircle, Phone, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const page = usePage<{ site: PublicSite; flash: { enquiry_success: string | null } }>();
const site = computed(() => page.props.site);

const nav = [
    { label: 'Home', href: '/' },
    { label: 'Buy Car', href: '/cars' },
    { label: 'Sell Your Car', href: '/sell-your-car' },
    { label: 'Finance', href: '/finance' },
    { label: 'Branches', href: '/branches' },
    { label: 'About', href: '/about' },
    { label: 'Contact', href: '/contact' },
];

const mobileOpen = ref(false);
const currentPath = computed(() => new URL(page.url, 'http://x').pathname);

const toast = ref<string | null>(null);
watch(
    () => page.props.flash?.enquiry_success,
    (msg) => {
        if (msg) {
            toast.value = msg as string;
            setTimeout(() => (toast.value = null), 6000);
        }
    },
    { immediate: true },
);

const year = new Date().getFullYear();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white text-neutral-900">
        <!-- Top bar -->
        <div class="bg-brand-maroon text-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-1.5 text-xs">
                <span class="hidden sm:inline">{{ site.tagline }} · Quality checked pre-owned cars</span>
                <a :href="`tel:${site.phone}`" class="flex items-center gap-1 font-medium">
                    <Phone class="size-3.5" /> {{ site.phone }}
                </a>
            </div>
        </div>

        <!-- Header -->
        <header class="sticky top-0 z-40 border-b bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
                <Link href="/" class="flex items-center gap-2">
                    <span class="flex size-10 items-center justify-center rounded-lg bg-brand-yellow text-brand-maroon">
                        <AppLogoIcon class="size-7" />
                    </span>
                    <span class="text-xl font-extrabold tracking-tight text-brand-maroon">
                        Car<span class="text-brand-yellow">4</span>Sales
                    </span>
                </Link>

                <nav class="hidden items-center gap-1 lg:flex">
                    <Link
                        v-for="item in nav"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-md px-3 py-2 text-sm font-medium transition-colors"
                        :class="currentPath === item.href ? 'bg-brand-yellow/20 text-brand-maroon' : 'text-neutral-700 hover:text-brand-maroon'"
                    >
                        {{ item.label }}
                    </Link>
                    <Link href="/cars" class="ml-2 rounded-lg bg-brand-maroon px-4 py-2 text-sm font-semibold text-white hover:bg-brand-maroon/90">
                        Browse Cars
                    </Link>
                </nav>

                <button class="rounded-md p-2 lg:hidden" aria-label="Menu" @click="mobileOpen = !mobileOpen">
                    <Menu v-if="!mobileOpen" class="size-6" />
                    <X v-else class="size-6" />
                </button>
            </div>

            <!-- Mobile nav -->
            <div v-if="mobileOpen" class="border-t bg-white lg:hidden">
                <nav class="mx-auto flex max-w-7xl flex-col px-4 py-2">
                    <Link
                        v-for="item in nav"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-md px-3 py-2.5 text-sm font-medium text-neutral-700"
                        @click="mobileOpen = false"
                    >
                        {{ item.label }}
                    </Link>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="mt-12 bg-brand-maroon text-white/90">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-brand-yellow text-brand-maroon">
                            <AppLogoIcon class="size-6" />
                        </span>
                        <span class="text-lg font-extrabold text-white">Car<span class="text-brand-yellow">4</span>Sales</span>
                    </div>
                    <p class="mt-3 text-sm text-white/70">{{ site.tagline }}. Certified pre-owned cars with transparent pricing and quality checks.</p>
                </div>
                <div>
                    <h4 class="mb-3 text-sm font-semibold uppercase tracking-wide text-brand-yellow">Explore</h4>
                    <ul class="space-y-2 text-sm">
                        <li><Link href="/cars" class="hover:text-white">Buy a Car</Link></li>
                        <li><Link href="/sell-your-car" class="hover:text-white">Sell Your Car</Link></li>
                        <li><Link href="/finance" class="hover:text-white">Finance</Link></li>
                        <li><Link href="/branches" class="hover:text-white">Branches</Link></li>
                    </ul>
                </div>
                <div>
                    <h4 class="mb-3 text-sm font-semibold uppercase tracking-wide text-brand-yellow">Company</h4>
                    <ul class="space-y-2 text-sm">
                        <li><Link href="/about" class="hover:text-white">About Us</Link></li>
                        <li><Link href="/reviews" class="hover:text-white">Customer Reviews</Link></li>
                        <li><Link href="/faqs" class="hover:text-white">FAQs</Link></li>
                        <li><Link href="/contact" class="hover:text-white">Contact</Link></li>
                    </ul>
                </div>
                <div>
                    <h4 class="mb-3 text-sm font-semibold uppercase tracking-wide text-brand-yellow">Legal</h4>
                    <ul class="space-y-2 text-sm">
                        <li><Link href="/privacy-policy" class="hover:text-white">Privacy Policy</Link></li>
                        <li><Link href="/terms" class="hover:text-white">Terms &amp; Conditions</Link></li>
                        <li><Link href="/refund-policy" class="hover:text-white">Refund &amp; Cancellation</Link></li>
                        <li><Link href="/disclaimer" class="hover:text-white">Disclaimer</Link></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-white/10">
                <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-4 py-4 text-xs text-white/60 sm:flex-row">
                    <span>© {{ year }} {{ site.name }}. All rights reserved.</span>
                    <span>{{ site.email }} · {{ site.phone }}</span>
                </div>
            </div>
        </footer>

        <!-- WhatsApp float -->
        <a
            :href="`https://wa.me/${site.whatsapp}`"
            target="_blank"
            rel="noopener"
            class="fixed bottom-5 right-5 z-50 flex size-14 items-center justify-center rounded-full bg-green-500 text-white shadow-lg transition-transform hover:scale-105"
            aria-label="Chat on WhatsApp"
        >
            <MessageCircle class="size-7" />
        </a>

        <!-- Success toast -->
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150"
            leave-to-class="opacity-0"
        >
            <div v-if="toast" class="fixed bottom-24 right-5 z-50 flex max-w-sm items-start gap-2 rounded-lg border border-green-300 bg-white px-4 py-3 text-sm text-green-800 shadow-lg">
                <CheckCircle2 class="mt-0.5 size-5 shrink-0" />
                <span>{{ toast }}</span>
            </div>
        </Transition>
    </div>
</template>
