<script setup lang="ts">
import EnquiryForm from '@/components/public/EnquiryForm.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import PublicLayout from '@/layouts/public/PublicLayout.vue';
import type { PublicSite } from '@/types/public';
import { usePage } from '@inertiajs/vue3';
import { Mail, MapPin, MessageCircle, Phone } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{ branches: { id: number; name: string; city: string | null }[] }>();

const site = computed(() => usePage<{ site: PublicSite }>().props.site);
</script>

<template>
    <SeoHead
        title="Contact Us — Car4Sales"
        description="Get in touch with Car4Sales. Call, email or send us a message and our team will respond promptly."
    />

    <PublicLayout>
        <section class="bg-brand-maroon py-12 text-white">
            <div class="mx-auto max-w-4xl px-4 text-center">
                <h1 class="text-3xl font-extrabold md:text-4xl">Contact Us</h1>
                <p class="mt-2 text-white/80">We'd love to hear from you. Reach out any time.</p>
            </div>
        </section>

        <div class="mx-auto grid max-w-6xl gap-6 px-4 py-10 lg:grid-cols-2">
            <div class="space-y-4">
                <div class="rounded-xl border bg-white p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex size-10 items-center justify-center rounded-full bg-brand-yellow/20 text-brand-maroon"
                            ><Phone class="size-5"
                        /></span>
                        <div>
                            <p class="text-sm text-neutral-500">Call us</p>
                            <a :href="`tel:${site.phone}`" class="font-semibold text-neutral-900">{{ site.phone }}</a>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border bg-white p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex size-10 items-center justify-center rounded-full bg-brand-yellow/20 text-brand-maroon"
                            ><Mail class="size-5"
                        /></span>
                        <div>
                            <p class="text-sm text-neutral-500">Email us</p>
                            <a :href="`mailto:${site.email}`" class="font-semibold text-neutral-900">{{ site.email }}</a>
                        </div>
                    </div>
                </div>
                <a
                    :href="`https://wa.me/${site.whatsapp}`"
                    target="_blank"
                    rel="noopener"
                    class="flex items-center gap-3 rounded-xl border bg-white p-5 hover:border-green-400"
                >
                    <span class="flex size-10 items-center justify-center rounded-full bg-green-100 text-green-600"
                        ><MessageCircle class="size-5"
                    /></span>
                    <div>
                        <p class="text-sm text-neutral-500">WhatsApp</p>
                        <p class="font-semibold text-neutral-900">Chat with us</p>
                    </div>
                </a>
                <div class="rounded-xl border bg-white p-5">
                    <p class="mb-2 flex items-center gap-2 font-semibold text-neutral-900"><MapPin class="size-4 text-brand-orange" /> Branches</p>
                    <ul class="space-y-1 text-sm text-neutral-600">
                        <li v-for="b in branches" :key="b.id">
                            {{ b.name }}<span v-if="b.city">, {{ b.city }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="rounded-2xl border bg-white p-6 shadow-sm">
                <EnquiryForm type="contact" heading="Send us a message" submit-label="Send Message" purpose="enquiry" />
            </div>
        </div>
    </PublicLayout>
</template>
