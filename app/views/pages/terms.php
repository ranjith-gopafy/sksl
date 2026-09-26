<div class="max-w-4xl mx-auto px-4 py-12">
    <div class="bg-white border border-slate-200/90 rounded-3xl p-8 md:p-12 shadow-xs">
        <span class="text-xs font-bold uppercase tracking-widest text-sky-600">Terms of Service</span>
        <h1 class="text-3xl font-extrabold text-slate-900 font-heading mt-2 mb-8">Facility Terms &amp; Conditions</h1>
        
        <div class="space-y-6 text-sm text-slate-600 leading-relaxed">
            <p>
                By booking or using any facility or service at Sara Kinetic Sports Lab (SKSL), you agree to comply with and be bound by the following terms, conditions, and safety protocols.
            </p>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">1. Facility Hours and Arrival</h2>
            <p>
                SKSL operates daily from <strong>6:00 AM to 10:00 PM IST</strong>. Athletes must arrive at least <strong>10 minutes prior</strong> to their scheduled session to check in, complete safety verification, and prepare. Late arrivals cannot be granted extended time if it conflicts with subsequent booked sessions.
            </p>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">2. Health and Safety Declaration</h2>
            <p>
                Recovery modalities such as Ice Baths (cold plunge), Hot Baths, Sauna, and Steam induce intense physiological thermal responses. By booking a session, you certify that:
            </p>
            <ul class="list-disc pl-5 space-y-1.5 text-slate-600">
                <li>You do not suffer from uncontrolled cardiovascular disease, severe hypertension, Raynaud's syndrome, epilepsy, or open wounds.</li>
                <li>You are not pregnant (for cold plunge and high-heat sauna protocols).</li>
                <li>You are not under the influence of alcohol, drugs, or impairing medications.</li>
                <li>You will immediately exit any modality and inform staff if you experience dizziness, shortness of breath, or numbness.</li>
            </ul>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">3. Hygiene and Etiquette</h2>
            <p>
                All athletes are required to shower before entering hydrotherapy pools, ice baths, or thermal areas. Clean athletic swimwear is mandatory. Towels and shower amenities are provided.
            </p>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">4. Booking Policy &amp; Single Service Rule</h2>
            <p>
                Each booking covers one designated service for the reserved slot duration. Multiple separate, non-overlapping bookings can be made for sequential sessions. All bookings must be paid in full in advance through our online platform.
                A selected slot is held for a short time while you pay; if payment is not completed the hold lapses and the slot is released. Sessions can be booked up to
                <?= (int) \App\Helpers\BookingRules::advanceDays() ?> days in advance.
            </p>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">5. Your Account</h2>
            <p>
                You must be 18 or older to create an account and provide accurate contact details so we can reach you about your session. Keep your password confidential;
                you are responsible for activity under your account. Tell us immediately if you suspect unauthorised use. We may suspend accounts used for fraud, abuse or
                automated booking.
            </p>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">6. Payments, Cancellations &amp; Refunds</h2>
            <p>
                Prices are shown in Indian Rupees inclusive of GST and a tax invoice is issued for every paid booking. Payments are processed by Razorpay; SKSL never sees your
                card or bank details. Cancellations and refunds are handled by the SKSL team as described in our
                <a href="<?= app_url('cancellation-refund') ?>" class="text-sky-600 font-semibold hover:underline">Cancellation &amp; Refund Policy</a> &mdash; contact us with your booking reference.
            </p>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">7. Communications &amp; Privacy</h2>
            <p>
                We send only the emails needed to operate your bookings and account (confirmations, invoices, password resets). How we handle your personal data is set out in our
                <a href="<?= app_url('privacy-policy') ?>" class="text-sky-600 font-semibold hover:underline">Privacy Policy</a>, which forms part of these terms.
            </p>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">8. Facility Discretion &amp; Liability</h2>
            <p>
                SKSL staff reserves the right to refuse service or terminate a session for any individual who violates safety rules, exhibits disrespectful conduct, or appears medically unfit for high-intensity thermal exposure.
                You use the modalities at your own risk having made the health declaration above; SKSL&rsquo;s liability is limited to the amount paid for the affected session except where the law does not permit such a limit.
            </p>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">9. Governing Law</h2>
            <p>
                These terms are governed by the laws of India. Courts in Bengaluru, Karnataka have exclusive jurisdiction over any dispute arising from them.
            </p>
        </div>
    </div>
</div>
