<?php

/*
|--------------------------------------------------------------------------
| Its Apple Store Content
|--------------------------------------------------------------------------
|
| The real store content `php artisan its-apple:store-content` applies: homepage carousels,
| CMS page text and checkout settings. Unlike the demo store it is meant to stay, so it has
| no purge. Banner images live beside this file in banners/{file}.webp.
|
*/

return [

    'support_email' => 'support@itsapple.example',

    'leftover_product_skus' => ['312041'],

    'payment_methods' => [
        'enabled' => ['cashondelivery'],
        'disabled' => ['moneytransfer', 'stripe', 'razorpay', 'payu', 'phonepe', 'paypal_standard', 'paypal_smart_button', 'payglocal'],
    ],

    'banners' => [
        ['file' => 'vibe-cases', 'title' => 'Accessories that match your vibe', 'link' => 'cases-covers'],
        ['file' => 'magsafe-power', 'title' => 'Snap on. Power up.', 'link' => 'power-banks'],
        ['file' => 'drop-proof-glass', 'title' => 'Drop-proof your screen', 'link' => 'screen-protectors'],
    ],

    'carousels' => [
        ['type' => 'category_carousel', 'name' => 'Category Carousel', 'sort_order' => 3, 'options' => ['filters' => ['parent_id' => 1, 'sort' => 'asc', 'limit' => 10]]],
        ['type' => 'product_carousel', 'name' => 'New Arrivals', 'sort_order' => 4, 'options' => ['title' => 'New Arrivals', 'filters' => ['new' => 1, 'sort' => 'created_at-desc', 'limit' => 10]]],
        ['type' => 'product_carousel', 'name' => 'Featured Picks', 'sort_order' => 7, 'options' => ['title' => 'Featured Picks', 'filters' => ['featured' => 1, 'sort' => 'name-asc', 'limit' => 10]]],
    ],

    'replaced_static_sections' => ['Hero', 'Shop by Category'],

    'pages' => [
        'terms-of-use' => [
            'title' => 'Terms of Use',
            'html' => '<h2>Terms of Use</h2>'
                .'<p>These terms apply when you use the Its Apple website or mobile app. By browsing, creating an account or placing an order, you agree to them.</p>'
                .'<h3>Your account</h3><p>Keep your password private and tell us straight away if you think someone else is using your account. You are responsible for orders placed from it.</p>'
                .'<h3>Using the store</h3><p>Please do not misuse the store: no attempts to break or overload it, no automated scraping, and no fake orders or reviews. We may suspend accounts that do.</p>'
                .'<h3>Product information</h3><p>We work to keep descriptions, photos and prices accurate. Colours can look slightly different on screen. If we find a mistake on an order, we will contact you before it ships.</p>'
                .'<h3>Reviews</h3><p>Reviews must be honest and about the product. We may remove reviews that are offensive, off-topic or include personal details.</p>'
                .'<h3>Changes</h3><p>We may update these terms. The version on this page applies to your next visit or order.</p>'
                .'<p>Questions? Email {support_email}.</p>',
        ],
        'payment-policy' => [
            'title' => 'Payment Policy',
            'html' => '<h2>Payment Policy</h2>'
                .'<p>All prices are shown in US dollars and include VAT where it applies.</p>'
                .'<h3>How you can pay</h3><p>We currently accept <strong>cash on delivery</strong>: you pay the courier when your order arrives. Please have the exact amount ready where you can. We will update this page when more payment options are added.</p>'
                .'<h3>Coupons</h3><p>One coupon can be used per order. Coupons cannot be exchanged for cash.</p>'
                .'<h3>Refunds</h3><p>Approved refunds are paid back as described in our Refund Policy.</p>'
                .'<p>Questions about a payment? Email {support_email}.</p>',
        ],
        'privacy-policy' => [
            'title' => 'Privacy Policy',
            'html' => '<h2>Privacy Policy</h2>'
                .'<p>This policy explains what personal information Its Apple collects through its website and mobile app, why, and the choices you have.</p>'
                .'<h3>What we collect</h3><ul>'
                .'<li><strong>Account details:</strong> your name, email address, phone number and password (stored encrypted).</li>'
                .'<li><strong>Order details:</strong> delivery and billing addresses, the products you order, and your order and return history.</li>'
                .'<li><strong>Messages and files:</strong> what you send us about orders and returns, including photos you attach.</li>'
                .'<li><strong>Reviews</strong> you write, with the name you choose to show.</li>'
                .'<li><strong>Technical data:</strong> device and app version, and basic usage information needed to keep the store working and secure.</li>'
                .'</ul>'
                .'<h3>How we use it</h3><p>To process and deliver your orders, handle returns and refunds, provide customer support, keep your account secure, and, if you subscribe, send you our newsletter. We do not sell your personal information.</p>'
                .'<h3>Who we share it with</h3><p>Only with the services that help us run the store: delivery companies (to bring your order), our hosting provider, and email or notification services. Each receives only what it needs for that job.</p>'
                .'<h3>How long we keep it</h3><p>We keep your account while it is open and order records for as long as the law requires for tax and accounting. You can delete your account from your profile at any time.</p>'
                .'<h3>Your choices</h3><p>You can view and update your details in your account, unsubscribe from the newsletter at any time, and ask us for a copy of your data or to delete it.</p>'
                .'<h3>Children</h3><p>The store is not intended for children under 13, and we do not knowingly collect their information.</p>'
                .'<h3>Changes</h3><p>We will post any changes on this page and update the date below.</p>'
                .'<p>Contact: {support_email}. Last updated: {date}.</p>',
        ],
    ],
];
