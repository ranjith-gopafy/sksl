<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Search-engine metadata for the public site.
 *
 * Single source of truth for which routes may be indexed, their default
 * meta descriptions, the sitemap and the LocalBusiness structured data.
 * Controllers may still override the description by setting $metaDescription
 * before requiring the layout.
 */
final class Seo
{
    public const DEFAULT_DESCRIPTION = 'Sara Kinetic Sports Lab (SKSL) is a sports recovery facility in Bengaluru offering ice baths, sauna, steam, hot baths, hydrotherapy and endless-pool sessions. Book a slot online and pay securely.';

    /**
     * Public, indexable routes with their descriptions and sitemap weights.
     *
     * @var array<string, array{description: string, priority: string, changefreq: string}>
     */
    private const PUBLIC_ROUTES = [
        '/' => [
            'description' => self::DEFAULT_DESCRIPTION,
            'priority'    => '1.0',
            'changefreq'  => 'weekly',
        ],
        '/services' => [
            'description' => 'Recovery modalities and GST-inclusive pricing at Sara Kinetic Sports Lab, Bengaluru: ice bath, sauna, steam, hot bath, hydrotherapy, endless pool, lap pool, cycle, treadmill and walker sessions.',
            'priority'    => '0.9',
            'changefreq'  => 'weekly',
        ],
        '/contact' => [
            'description' => 'Visit Sara Kinetic Sports Lab in Bengaluru. Facility address, opening hours, phone and email for bookings, cancellations and support.',
            'priority'    => '0.7',
            'changefreq'  => 'monthly',
        ],
        '/privacy-policy' => [
            'description' => 'How Sara Kinetic Sports Lab collects, uses and protects your personal data when you book a recovery session.',
            'priority'    => '0.3',
            'changefreq'  => 'yearly',
        ],
        '/terms' => [
            'description' => 'Terms of service for booking and attending recovery sessions at Sara Kinetic Sports Lab.',
            'priority'    => '0.3',
            'changefreq'  => 'yearly',
        ],
        '/cancellation-refund' => [
            'description' => 'Cancellation and refund policy for Sara Kinetic Sports Lab bookings. Contact the facility with your booking reference to cancel or request a refund.',
            'priority'    => '0.3',
            'changefreq'  => 'yearly',
        ],
        '/register' => [
            'description' => 'Create your Sara Kinetic Sports Lab account to book recovery sessions online.',
            'priority'    => '0.4',
            'changefreq'  => 'yearly',
        ],
        '/login' => [
            'description' => 'Sign in to your Sara Kinetic Sports Lab account to book and manage recovery sessions.',
            'priority'    => '0.4',
            'changefreq'  => 'yearly',
        ],
    ];

    public static function isIndexable(string $path): bool
    {
        return isset(self::PUBLIC_ROUTES[$path]);
    }

    /** Pages that describe the business itself carry the LocalBusiness JSON-LD. */
    public static function emitsStructuredData(string $path): bool
    {
        return in_array($path, ['/', '/services', '/contact'], true);
    }

    public static function descriptionFor(string $path): string
    {
        return self::PUBLIC_ROUTES[$path]['description'] ?? self::DEFAULT_DESCRIPTION;
    }

    /** Absolute canonical URL for an application-relative path ("/", "/services"). */
    public static function canonicalUrl(string $path): string
    {
        return app_url(ltrim($path, '/'));
    }

    /** @return list<array{loc: string, priority: string, changefreq: string}> */
    public static function sitemapEntries(): array
    {
        $entries = [];
        foreach (self::PUBLIC_ROUTES as $path => $meta) {
            // Account pages are crawlable but add nothing to search results.
            if ($path === '/register' || $path === '/login') {
                continue;
            }
            $entries[] = [
                'loc'        => self::canonicalUrl($path),
                'priority'   => $meta['priority'],
                'changefreq' => $meta['changefreq'],
            ];
        }
        return $entries;
    }

    /** Paths that must never be crawled (private, transactional or admin). */
    public static function disallowedPaths(): array
    {
        return ['/admin', '/api', '/booking', '/booking-confirmation', '/bookings', '/my-bookings', '/profile', '/health', '/forgot-password', '/reset-password'];
    }

    /**
     * schema.org LocalBusiness (SportsActivityLocation) built from config('business').
     * Only fields that are actually configured are emitted.
     *
     * @return array<string, mixed>
     */
    public static function localBusiness(): array
    {
        $biz      = (array) config('business', []);
        $facility = (array) config('app.facility', []);

        $data = [
            '@context' => 'https://schema.org',
            '@type'    => 'SportsActivityLocation',
            'name'     => (string) ($biz['trade_name'] ?: 'Sara Kinetic Sports Lab'),
            'url'      => app_url(''),
            'image'    => asset('images/hero-banner.jpg'),
            'logo'     => asset('images/sksl-logo.png'),
            'description' => self::DEFAULT_DESCRIPTION,
            'priceRange'  => '₹₹',
            'currenciesAccepted' => 'INR',
            'paymentAccepted'    => 'UPI, Credit Card, Debit Card, Net Banking',
            'openingHoursSpecification' => [[
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                'opens'     => (string) ($facility['open'] ?? '06:00'),
                'closes'    => (string) ($facility['close'] ?? '22:00'),
            ]],
        ];

        if (!empty($biz['legal_name']) && $biz['legal_name'] !== $data['name']) {
            $data['legalName'] = (string) $biz['legal_name'];
        }
        if (!empty($biz['phone'])) {
            $data['telephone'] = (string) $biz['phone'];
        }
        if (!empty($biz['support_email'])) {
            $data['email'] = (string) $biz['support_email'];
        }

        $address = array_filter([
            'streetAddress'   => trim(implode(', ', array_filter([(string) ($biz['address_line1'] ?? ''), (string) ($biz['address_line2'] ?? '')]))),
            'addressLocality' => (string) ($biz['city'] ?? 'Bengaluru'),
            'addressRegion'   => (string) ($biz['state_name'] ?? 'Karnataka'),
            'postalCode'      => (string) ($biz['pincode'] ?? ''),
            'addressCountry'  => 'IN',
        ], static fn ($v) => $v !== '');
        $data['address'] = ['@type' => 'PostalAddress'] + $address;

        return $data;
    }

    /** JSON-LD string safe for embedding inside a <script> element. */
    public static function localBusinessJsonLd(): string
    {
        return (string) json_encode(
            self::localBusiness(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }
}
