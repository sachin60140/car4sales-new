<?php

namespace Database\Seeders;

use App\Domain\PublicWebsite\Models\Faq;
use App\Domain\PublicWebsite\Models\Page;
use App\Domain\PublicWebsite\Models\Testimonial;
use Illuminate\Database\Seeder;

/**
 * Baseline public-website content: legal pages, FAQs and testimonials. Idempotent
 * (updateOrCreate) so it can run as part of the standard seed.
 */
class PublicContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->pages();
        $this->faqs();
        $this->testimonials();
    }

    private function pages(): void
    {
        $pages = [
            ['about', 'About Us', '<p>Car4Sales is a multi-branch pre-owned car dealership committed to quality, transparency and customer satisfaction.</p>'],
            ['privacy-policy', 'Privacy Policy', '<p>We respect your privacy. This policy explains how we collect, use and protect your personal information when you use our website and services. We collect only the information necessary to respond to your enquiries and process transactions, and we never sell your data to third parties.</p><p>By submitting an enquiry you consent to being contacted by our team regarding your request.</p>'],
            ['terms', 'Terms and Conditions', '<p>By using this website you agree to these terms. Vehicle availability, pricing and specifications are subject to change without notice. All sales are subject to inspection and documentation verification. Images are for reference and may differ from the actual vehicle.</p>'],
            ['refund-policy', 'Refund and Cancellation Policy', '<p>Booking amounts are refundable subject to the terms agreed at the time of booking. Cancellation and refund requests are processed after due verification. Please refer to your booking agreement for specific terms applicable to your transaction.</p>'],
            ['disclaimer', 'Disclaimer', '<p>The information on this website is provided in good faith for general information only. While we strive for accuracy, Car4Sales makes no warranties regarding completeness or reliability. Vehicle details are indicative; buyers are advised to physically inspect vehicles and verify documents before purchase.</p>'],
        ];

        foreach ($pages as [$slug, $title, $body]) {
            Page::query()->updateOrCreate(
                ['slug' => $slug],
                ['title' => $title, 'body' => $body, 'meta_description' => strip_tags($title).' — Car4Sales', 'is_published' => true],
            );
        }
    }

    private function faqs(): void
    {
        $faqs = [
            ['Buying', 'Are the cars inspected before sale?', 'Yes. Every car undergoes a comprehensive multi-point inspection covering engine, transmission, suspension, electricals and structure before it is listed for sale.'],
            ['Buying', 'Can I take a test drive?', 'Absolutely. You can request a test drive from any vehicle page or by contacting your nearest branch.'],
            ['Buying', 'Do you provide a warranty?', 'Selected cars come with a warranty. Please check the specific vehicle details or ask our team for warranty options.'],
            ['Selling', 'How do I sell my car?', 'Fill in the Sell Your Car form with your car details. Our team will schedule a free inspection and give you an instant quote.'],
            ['Selling', 'How is the price decided?', 'The price is based on the car condition, market value, mileage, ownership and documentation after a physical inspection.'],
            ['Finance', 'Do you offer car finance?', 'Yes, we partner with leading lenders to offer attractive loan options with quick approvals. Use our EMI calculator to estimate your monthly payments.'],
            ['Finance', 'What documents are needed for a loan?', 'Typically identity proof, address proof, income proof and bank statements. Our finance team will guide you through the exact requirements.'],
            ['General', 'Are the vehicle documents genuine?', 'Yes. We verify RC, insurance and all papers, and complete RC transfer as part of the sale.'],
        ];

        $order = 0;
        foreach ($faqs as [$category, $question, $answer]) {
            Faq::query()->updateOrCreate(
                ['question' => $question],
                ['category' => $category, 'answer' => $answer, 'sort_order' => $order++, 'is_active' => true],
            );
        }
    }

    private function testimonials(): void
    {
        $items = [
            ['Rahul Verma', 'Lucknow', 5, 'Smooth buying experience. The car was exactly as described and the paperwork was handled end to end.'],
            ['Priya Singh', 'Kanpur', 5, 'Sold my old car at a great price. The inspection was quick and payment was instant.'],
            ['Amit Sharma', 'Varanasi', 4, 'Good selection of cars and transparent pricing. The finance team made the loan process easy.'],
            ['Neha Gupta', 'Prayagraj', 5, 'Loved the transparency. No hidden charges and the team was very helpful throughout.'],
            ['Suresh Kumar', 'Lucknow', 5, 'Quality-checked car delivered on time. Highly recommend Car4Sales for pre-owned cars.'],
            ['Anjali Mishra', 'Agra', 4, 'Test drive was easy to book and the staff answered all my questions patiently.'],
        ];

        $order = 0;
        foreach ($items as [$name, $city, $rating, $message]) {
            Testimonial::query()->updateOrCreate(
                ['customer_name' => $name, 'message' => $message],
                ['city' => $city, 'rating' => $rating, 'is_approved' => true, 'is_featured' => $order < 3, 'sort_order' => $order++],
            );
        }
    }
}
