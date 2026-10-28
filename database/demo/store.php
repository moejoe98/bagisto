<?php

/*
|--------------------------------------------------------------------------
| Its Apple Demo Store
|--------------------------------------------------------------------------
|
| The catalog, customers and content `php artisan its-apple:demo-store` seeds. Product images
| live beside this file in products/{url_key}.webp. Demo records are recognisable by the SKU
| prefix and the customer email domain below, which is how `--purge` finds them again.
|
*/

return [

    'sku_prefix' => 'IA-DEMO-',

    'email_domain' => 'demo.itsapple.test',

    'customer_password' => 'ItsApple-demo-2026',

    'coupon' => [
        'name' => 'Demo: Welcome 10% off',
        'code' => 'WELCOME10',
        'discount' => 10,
    ],

    'categories' => [
        'cases-covers' => ['name' => 'Cases & Covers', 'description' => 'Colourful, protective cases for every iPhone.', 'logo' => 'its-apple-blob-case-iphone-16-pro'],
        'chargers-cables' => ['name' => 'Chargers & Cables', 'description' => 'Fast chargers and tough braided cables.', 'logo' => '20w-usb-c-fast-charger'],
        'screen-protectors' => ['name' => 'Screen Protectors', 'description' => 'Tempered glass that keeps every screen spotless.', 'logo' => 'tempered-glass-screen-protector-iphone-16-pro'],
        'airpods-audio' => ['name' => 'AirPods & Audio', 'description' => 'Cases and earbuds for your music on the go.', 'logo' => 'its-apple-buds-wireless-earbuds'],
        'power-banks' => ['name' => 'Power Banks', 'description' => 'Portable power for long days out.', 'logo' => 'magsafe-power-bank-5000mah'],
        'watch-bands' => ['name' => 'Watch Bands', 'description' => 'Bands to match every outfit and workout.', 'logo' => 'sport-band-orange-45mm'],
    ],

    'products' => [
        ['url_key' => 'its-apple-blob-case-iphone-16-pro', 'category' => 'cases-covers', 'name' => 'Its Apple Blob Case for iPhone 16 Pro', 'price' => 29, 'special_price' => 24, 'featured' => 1, 'new' => 1, 'short' => 'Our signature colourful case with MagSafe and raised camera edges.'],
        ['url_key' => 'clear-magsafe-case-iphone-16', 'category' => 'cases-covers', 'name' => 'Clear MagSafe Case for iPhone 16', 'price' => 25, 'featured' => 1, 'new' => 0, 'short' => 'Crystal-clear, anti-yellowing case with a built-in MagSafe ring.'],
        ['url_key' => 'silicone-case-lavender-iphone-16-pro-max', 'category' => 'cases-covers', 'name' => 'Silicone Case Lavender for iPhone 16 Pro Max', 'price' => 22, 'featured' => 0, 'new' => 1, 'short' => 'Soft-touch silicone with a microfibre lining.'],
        ['url_key' => 'leather-wallet-case-tan-iphone-15', 'category' => 'cases-covers', 'name' => 'Leather Wallet Case Tan for iPhone 15', 'price' => 39, 'featured' => 0, 'new' => 0, 'short' => 'Genuine leather case with a slot for two cards.'],
        ['url_key' => '20w-usb-c-fast-charger', 'category' => 'chargers-cables', 'name' => '20W USB-C Fast Charger', 'price' => 19, 'featured' => 1, 'new' => 0, 'short' => 'Charge an iPhone to 50% in about 30 minutes.'],
        ['url_key' => '35w-dual-usb-c-charger', 'category' => 'chargers-cables', 'name' => '35W Dual USB-C Charger', 'price' => 35, 'special_price' => 29, 'featured' => 0, 'new' => 1, 'short' => 'Charge your iPhone and AirPods at the same time.'],
        ['url_key' => 'braided-usb-c-to-lightning-cable-1m', 'category' => 'chargers-cables', 'name' => 'Braided USB-C to Lightning Cable 1m', 'price' => 15, 'featured' => 0, 'new' => 0, 'short' => 'Nylon-braided cable tested for 20,000 bends.'],
        ['url_key' => 'braided-usb-c-to-usb-c-cable-2m', 'category' => 'chargers-cables', 'name' => 'Braided USB-C to USB-C Cable 2m', 'price' => 17, 'featured' => 0, 'new' => 1, 'short' => 'Long, tangle-free 60W cable for iPhone 15 and newer.'],
        ['url_key' => 'tempered-glass-screen-protector-iphone-16-pro', 'category' => 'screen-protectors', 'name' => 'Tempered Glass Screen Protector for iPhone 16 Pro', 'price' => 12, 'featured' => 1, 'new' => 0, 'short' => '9H glass with an easy-align frame for bubble-free install.'],
        ['url_key' => 'privacy-glass-screen-protector-iphone-16-pro-max', 'category' => 'screen-protectors', 'name' => 'Privacy Glass Screen Protector for iPhone 16 Pro Max', 'price' => 16, 'featured' => 0, 'new' => 1, 'short' => 'Keeps your screen readable only from straight ahead.'],
        ['url_key' => 'camera-lens-protector-iphone-16-pro', 'category' => 'screen-protectors', 'name' => 'Camera Lens Protector for iPhone 16 Pro', 'price' => 10, 'featured' => 0, 'new' => 0, 'short' => 'Sapphire-coated rings that guard every lens.'],
        ['url_key' => 'matte-anti-glare-protector-ipad-air', 'category' => 'screen-protectors', 'name' => 'Matte Anti-Glare Protector for iPad Air', 'price' => 18, 'featured' => 0, 'new' => 0, 'short' => 'Paper-like matte finish, made for drawing and reading.'],
        ['url_key' => 'silicone-airpods-pro-case-orange', 'category' => 'airpods-audio', 'name' => 'Silicone AirPods Pro Case Orange', 'price' => 14, 'featured' => 0, 'new' => 1, 'short' => 'Shock-absorbing case that keeps wireless charging working.'],
        ['url_key' => 'its-apple-buds-wireless-earbuds', 'category' => 'airpods-audio', 'name' => 'Its Apple Buds Wireless Earbuds', 'price' => 59, 'special_price' => 49, 'featured' => 1, 'new' => 1, 'short' => 'Bluetooth 5.3 earbuds with 30 hours of total battery.'],
        ['url_key' => 'clear-airpods-case-with-keychain', 'category' => 'airpods-audio', 'name' => 'Clear AirPods Case with Keychain', 'price' => 12, 'featured' => 0, 'new' => 0, 'short' => 'Slim clear case with a detachable metal keyring.'],
        ['url_key' => 'usb-c-wired-earphones', 'category' => 'airpods-audio', 'name' => 'USB-C Wired Earphones', 'price' => 19, 'featured' => 0, 'new' => 0, 'short' => 'Plug-and-play earphones with an in-line mic and remote.'],
        ['url_key' => 'magsafe-power-bank-5000mah', 'category' => 'power-banks', 'name' => 'MagSafe Power Bank 5000mAh', 'price' => 45, 'featured' => 1, 'new' => 1, 'short' => 'Snaps onto your iPhone and charges it wirelessly.'],
        ['url_key' => 'power-bank-10000mah-20w', 'category' => 'power-banks', 'name' => 'Power Bank 10000mAh 20W', 'price' => 35, 'featured' => 0, 'new' => 0, 'short' => 'Two full iPhone charges in a pocket-size bank.'],
        ['url_key' => 'power-bank-20000mah-pd', 'category' => 'power-banks', 'name' => 'Power Bank 20000mAh PD', 'price' => 49, 'special_price' => 42, 'featured' => 0, 'new' => 0, 'short' => 'Enough power for a whole weekend, with 30W fast charging.'],
        ['url_key' => '3-in-1-wireless-charging-stand', 'category' => 'power-banks', 'name' => '3-in-1 Wireless Charging Stand', 'price' => 79, 'featured' => 1, 'new' => 1, 'short' => 'Charge iPhone, Apple Watch and AirPods together.'],
        ['url_key' => 'sport-band-orange-45mm', 'category' => 'watch-bands', 'name' => 'Sport Band Orange 45mm', 'price' => 19, 'featured' => 0, 'new' => 0, 'short' => 'Sweat-proof fluoroelastomer band with a pin-and-tuck clasp.'],
        ['url_key' => 'sport-loop-lavender-41mm', 'category' => 'watch-bands', 'name' => 'Sport Loop Lavender 41mm', 'price' => 22, 'featured' => 0, 'new' => 1, 'short' => 'Breathable woven loop with a hook-and-loop fastener.'],
        ['url_key' => 'milanese-loop-silver-45mm', 'category' => 'watch-bands', 'name' => 'Milanese Loop Silver 45mm', 'price' => 39, 'featured' => 1, 'new' => 0, 'short' => 'Stainless steel mesh with a magnetic closure.'],
        ['url_key' => 'braided-solo-loop-pink-41mm', 'category' => 'watch-bands', 'name' => 'Braided Solo Loop Pink 41mm', 'price' => 29, 'featured' => 0, 'new' => 0, 'short' => 'Stretchy braided band with no clasp at all.'],
    ],

    'customers' => [
        ['first_name' => 'Layla', 'last_name' => 'Haddad', 'gender' => 'Female', 'city' => 'Dubai', 'state' => 'Dubai', 'newsletter' => 1],
        ['first_name' => 'Omar', 'last_name' => 'Khalil', 'gender' => 'Male', 'city' => 'Abu Dhabi', 'state' => 'Abu Dhabi', 'newsletter' => 0],
        ['first_name' => 'Sara', 'last_name' => 'Mansour', 'gender' => 'Female', 'city' => 'Sharjah', 'state' => 'Sharjah', 'newsletter' => 1],
        ['first_name' => 'Yousef', 'last_name' => 'Nasser', 'gender' => 'Male', 'city' => 'Dubai', 'state' => 'Dubai', 'newsletter' => 1],
        ['first_name' => 'Maya', 'last_name' => 'Fares', 'gender' => 'Female', 'city' => 'Ajman', 'state' => 'Ajman', 'newsletter' => 0],
        ['first_name' => 'Karim', 'last_name' => 'Saleh', 'gender' => 'Male', 'city' => 'Al Ain', 'state' => 'Abu Dhabi', 'newsletter' => 0],
        ['first_name' => 'Nour', 'last_name' => 'Aziz', 'gender' => 'Female', 'city' => 'Dubai', 'state' => 'Dubai', 'newsletter' => 1],
        ['first_name' => 'Hadi', 'last_name' => 'Rahal', 'gender' => 'Male', 'city' => 'Ras Al Khaimah', 'state' => 'Ras Al Khaimah', 'newsletter' => 0],
        ['first_name' => 'Rania', 'last_name' => 'Tamer', 'gender' => 'Female', 'city' => 'Fujairah', 'state' => 'Fujairah', 'newsletter' => 1],
        ['first_name' => 'Ziad', 'last_name' => 'Hamdan', 'gender' => 'Male', 'city' => 'Dubai', 'state' => 'Dubai', 'newsletter' => 0],
    ],

    'reviews' => [
        [5, 'Love the colours', 'Exactly like the pictures and it fits perfectly. The MagSafe is strong.'],
        [5, 'Fast delivery', 'Arrived the next day and works great. Will order again.'],
        [4, 'Good value', 'Solid quality for the price. Packaging could be better.'],
        [5, 'Highly recommend', 'Charges my phone really fast and does not get hot.'],
        [4, 'Nice and sturdy', 'Feels premium in the hand. The buttons are clicky.'],
        [3, 'Okay', 'Does the job, but the colour is slightly lighter than shown.'],
        [5, 'Perfect fit', 'Installed in a minute with the frame, no bubbles at all.'],
        [4, 'Comfortable', 'Wore it all day at the gym and it stayed comfortable.'],
    ],

    'search_terms' => [
        'magsafe' => 42, 'iphone 16 case' => 37, 'charger' => 29, 'airpods case' => 21, 'screen protector' => 18,
        'power bank' => 16, 'watch band' => 11, 'usb c cable' => 9, 'privacy glass' => 6, 'earbuds' => 5,
    ],

    'pages' => [
        'about-us' => ['title' => 'About Us', 'html' => '<h2>About Its Apple</h2><p>Its Apple is a mobile accessories store for people who love their Apple gear and want it to look and feel like them. We pick colourful cases, fast chargers, tough screen protectors and everyday essentials for iPhone, AirPods and Apple Watch.</p><p>Every product is tested by our team before it reaches our shelves, and we only stock accessories we would use ourselves.</p>'],
        'customer-service' => ['title' => 'Customer Service', 'html' => '<h2>Customer Service</h2><p>Our team is here to help with orders, deliveries, returns and product questions.</p><ul><li>Email: support@itsapple.example</li><li>Hours: Saturday to Thursday, 9:00 to 21:00</li></ul><p>Please include your order number so we can help you faster.</p>'],
        'return-policy' => ['title' => 'Return Policy', 'html' => '<h2>Return Policy</h2><p>You can return most items within 14 days of delivery, unused and in their original packaging. Start a return from your account under Orders, choose the item and tell us why.</p><p>Opened screen protectors and earphones cannot be returned for hygiene reasons unless they are faulty.</p>'],
        'refund-policy' => ['title' => 'Refund Policy', 'html' => '<h2>Refund Policy</h2><p>Once we receive and check your return, we refund the item price to your original payment method within 5 to 7 working days. Delivery fees are refunded only when the item was faulty or we sent the wrong product.</p>'],
        'shipping-policy' => ['title' => 'Shipping Policy', 'html' => '<h2>Shipping Policy</h2><p>Orders placed before 3 pm are dispatched the same working day. Delivery usually takes 1 to 3 working days, and you receive a tracking number by email once your order ships.</p>'],
        'terms-conditions' => ['title' => 'Terms & Conditions', 'html' => '<h2>Terms &amp; Conditions</h2><p>By placing an order with Its Apple you agree to these terms. Prices include VAT where applicable and may change without notice. We may cancel an order if a product is out of stock or priced incorrectly, and we will refund you in full.</p>'],
        'whats-new' => ['title' => "What's New", 'html' => "<h2>What's New</h2><p>New this month: the 3-in-1 Wireless Charging Stand, the Its Apple Buds and our signature Blob Case for iPhone 16 Pro. Follow us for new drops every week.</p>"],
    ],
];
